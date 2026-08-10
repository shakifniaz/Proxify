<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\ClassSection;
use App\Models\Institution;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\FirebaseTokenVerifier;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register', [
            'firebaseConfig' => config('services.firebase'),
        ]);
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request, FirebaseTokenVerifier $firebase): RedirectResponse
    {
        $data = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|string|lowercase|email|max:255|unique:'.User::class,
            'phone' => 'nullable|string|max:20',
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
            'id_token' => ['required', 'string'],
            'role' => ['required', 'string', Rule::in(['admin', 'teacher', 'student'])],
            'institution_name' => ['required_if:role,admin', 'nullable', 'string', 'max:255'],
            'institution_short_name' => ['nullable', 'string', 'max:60'],
            'institution_phone' => ['nullable', 'string', 'max:30'],
            'institution_email' => ['nullable', 'email', 'max:255'],
            'institution_address' => ['nullable', 'string', 'max:255'],
            'academic_year' => ['nullable', 'string', 'max:30'],
            'teacher_code' => ['required_if:role,teacher', 'nullable', 'string', 'max:30'],
            'class_code' => ['required_if:role,student', 'nullable', 'string', 'max:30'],
        ]);

        $payload = $firebase->verify($data['id_token']);
        $firebaseEmail = strtolower((string) ($payload['email'] ?? ''));
        $firebaseUid = (string) ($payload['sub'] ?? '');

        if ($firebaseEmail !== strtolower($data['email'])) {
            throw ValidationException::withMessages(['email' => 'Firebase account does not match this email address.']);
        }

        $teacherProfile = null;
        $classSection = null;

        if ($data['role'] === 'teacher') {
            $teacherProfile = TeacherProfile::where('join_code', strtoupper(trim($data['teacher_code'] ?? '')))->first();

            if (! $teacherProfile || $teacherProfile->user_id) {
                throw ValidationException::withMessages([
                    'teacher_code' => 'Enter a valid unused teacher code from your institution admin.',
                ]);
            }
        }

        if ($data['role'] === 'student') {
            $classSection = ClassSection::where('join_code', strtoupper(trim($data['class_code'] ?? '')))->first();

            if (! $classSection) {
                throw ValidationException::withMessages([
                    'class_code' => 'Enter a valid class code from your institution admin.',
                ]);
            }
        }

        $user = DB::transaction(function () use ($data, $teacherProfile, $classSection, $firebaseUid) {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'firebase_uid' => $firebaseUid,
                'phone' => $data['phone'] ?? null,
                'password' => Hash::make(str()->random(48)),
                'role' => $data['role'],
                'institution_id' => $teacherProfile?->institution_id ?? $classSection?->institution_id,
                'teacher_profile_id' => $teacherProfile?->id,
                'class_section_id' => $classSection?->id,
            ]);

            if ($data['role'] === 'admin') {
                $institution = Institution::create([
                    'owner_user_id' => $user->id,
                    'name' => $data['institution_name'],
                    'short_name' => $data['institution_short_name'] ?? null,
                    'phone' => $data['institution_phone'] ?? $data['phone'] ?? null,
                    'email' => $data['institution_email'] ?? $data['email'],
                    'address' => $data['institution_address'] ?? null,
                    'academic_year' => $data['academic_year'] ?? null,
                ]);

                $user->update(['institution_id' => $institution->id]);
            }

            if ($teacherProfile) {
                $teacherProfile->update([
                    'user_id' => $user->id,
                    'name' => $data['name'],
                    'whatsapp_number' => $data['phone'] ?? $teacherProfile->whatsapp_number,
                ]);
            }

            return $user;
        });

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
