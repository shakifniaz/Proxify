<?php

namespace App\Http\Controllers;

use App\Models\Institution;
use App\Models\TeacherProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TeacherController extends Controller
{
    public function index(Request $request): Response
    {
        $institutionId = $this->institutionId($request);

        return Inertia::render('Teachers/Index', [
            'teachers' => TeacherProfile::query()
                ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
                ->orderBy('sort_order')
                ->orderBy('name')
                ->get()
                ->map(fn (TeacherProfile $teacher) => [
                    'id' => $teacher->id,
                    'name' => $teacher->name,
                    'phone' => $teacher->whatsapp_number ?? '',
                    'initials' => $this->initials($teacher->name),
                    'avatarColor' => 'emerald',
                    'subject' => 'Set in routines',
                    'joinCode' => $teacher->join_code,
                    'linked' => (bool) $teacher->user_id,
                    'proxyLoadThisMonth' => 0,
                    'leaveUsedDays' => 0,
                    'status' => $teacher->status,
                    'role' => 'Teacher',
                ])
                ->values(),
            'subjectOptions' => [],
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $this->ensureAdmin($request);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
        ]);

        TeacherProfile::create([
            'institution_id' => $this->institutionId($request),
            'name' => $data['name'],
            'whatsapp_number' => $data['phone'] ?? null,
            'join_code' => $this->uniqueCode(TeacherProfile::class, 'TCH'),
            'sort_order' => TeacherProfile::where('institution_id', $this->institutionId($request))->count(),
        ]);

        return back()->with('success', 'Teacher added with signup code.');
    }

    public function update(Request $request, TeacherProfile $teacher): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_unless($teacher->institution_id === $this->institutionId($request), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:30'],
            'status' => ['nullable', 'string', 'max:30'],
        ]);

        $teacher->update([
            'name' => $data['name'],
            'whatsapp_number' => $data['phone'] ?? null,
            'status' => $data['status'] ?? $teacher->status,
        ]);

        if ($teacher->user) {
            $teacher->user->update([
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
            ]);
        }

        return back()->with('success', 'Teacher updated.');
    }

    public function destroy(Request $request, TeacherProfile $teacher): RedirectResponse
    {
        $this->ensureAdmin($request);
        abort_unless($teacher->institution_id === $this->institutionId($request), 403);

        $name = $teacher->name;
        $teacher->delete();

        return back()->with('success', $name.' deleted.');
    }

    private function institutionId(Request $request): int
    {
        $user = $request->user();
        $id = $user?->institution_id ?? Institution::query()->value('id');

        if (! $id && ($user?->role ?? 'admin') === 'admin') {
            $institution = Institution::create([
                'owner_user_id' => $user->id,
                'name' => 'My Institution',
                'email' => $user->email,
            ]);

            $user->update(['institution_id' => $institution->id]);
            $id = $institution->id;
        }

        abort_unless($id, 422, 'Create an institution before adding teachers.');

        return (int) $id;
    }

    private function ensureAdmin(Request $request): void
    {
        abort_unless(($request->user()?->role ?? 'admin') === 'admin', 403);
    }

    private function uniqueCode(string $model, string $prefix): string
    {
        do {
            $code = $prefix.'-'.Str::upper(Str::random(6));
        } while ($model::where('join_code', $code)->exists());

        return $code;
    }

    private function initials(string $name): string
    {
        return collect(preg_split('/\s+/', trim($name)))
            ->filter()
            ->map(fn ($part) => mb_substr($part, 0, 1))
            ->take(2)
            ->implode('');
    }
}
