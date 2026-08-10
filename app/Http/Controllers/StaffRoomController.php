<?php

namespace App\Http\Controllers;

use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class StaffRoomController extends Controller
{
    public function index(Request $request): Response
    {
        $user = $request->user();
        $role = strtolower($user?->role ?? 'admin');
        abort_unless(in_array($role, ['admin', 'teacher'], true), 403);

        $institutionId = $user?->institution_id;
        $teacherDirectory = TeacherProfile::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get()
            ->map(fn (TeacherProfile $teacher) => [
                'id' => 'teacher-'.$teacher->id,
                'laravelUserId' => $teacher->user_id,
                'teacherProfileId' => $teacher->id,
                'name' => $teacher->name,
                'role' => 'teacher',
                'subtitle' => $teacher->whatsapp_number ?: 'Teacher',
                'canDm' => true,
            ]);

        $adminDirectory = User::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->where('role', 'admin')
            ->orderBy('name')
            ->get()
            ->map(fn (User $admin) => [
                'id' => 'admin-'.$admin->id,
                'laravelUserId' => $admin->id,
                'teacherProfileId' => null,
                'name' => $admin->name,
                'role' => 'admin',
                'subtitle' => 'Admin',
                'canDm' => $role === 'admin',
            ]);

        $currentDirectoryId = $role === 'teacher'
            ? 'teacher-'.($user?->teacher_profile_id ?? $user?->id)
            : 'admin-'.$user?->id;

        return Inertia::render('StaffRoom/Index', [
            'currentUser' => [
                'id' => $currentDirectoryId,
                'laravelUserId' => $user?->id,
                'teacherProfileId' => $user?->teacher_profile_id,
                'name' => $user?->teacherProfile?->name ?? $user?->name ?? 'User',
                'role' => $role,
                'institutionId' => $institutionId ? (string) $institutionId : 'global',
            ],
            'directory' => $teacherDirectory
                ->merge($adminDirectory)
                ->unique('id')
                ->values(),
            'openChannels' => [
                ['id' => 'general', 'name' => 'General staffroom', 'subtitle' => 'Open staff discussion', 'locked' => true, 'sortOrder' => 1],
            ],
            'firebaseConfig' => [
                'apiKey' => config('services.firebase.api_key'),
                'authDomain' => config('services.firebase.auth_domain'),
                'projectId' => config('services.firebase.project_id'),
                'storageBucket' => config('services.firebase.storage_bucket'),
                'messagingSenderId' => config('services.firebase.messaging_sender_id'),
                'appId' => config('services.firebase.app_id'),
            ],
        ]);
    }
}
