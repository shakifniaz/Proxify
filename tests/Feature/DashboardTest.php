<?php

namespace Tests\Feature;

use App\Models\ClassSection;
use App\Models\Institution;
use App\Models\Notice;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_receives_the_operations_dashboard(): void
    {
        [$institution, $user] = $this->userForRole('admin');

        Notice::create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'title' => 'Admin notice',
            'message' => 'Visible on the dashboard.',
            'visibility' => 'Admins',
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Dashboard')
                ->has('stats', 4)
                ->where('notices.0.title', 'Admin notice')
                ->has('routineHealth')
                ->has('exam'));
    }

    public function test_teacher_receives_schedule_exam_notice_and_classroom_context(): void
    {
        [$institution, $user] = $this->userForRole('teacher');
        $teacher = TeacherProfile::create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'name' => $user->name,
            'join_code' => 'TCH-TEST',
        ]);
        $user->update(['teacher_profile_id' => $teacher->id]);

        Notice::create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'title' => 'Teacher notice',
            'message' => 'Visible to teachers.',
            'visibility' => 'Teachers',
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('TeacherDashboard')
                ->where('teacherName', $user->name)
                ->where('urgentNotices.0.title', 'Teacher notice')
                ->where('classroomFeed.teacherName', $user->name)
                ->where('leaveStats.maximum', 12)
                ->where('leaveStats.used', 0)
                ->where('leaveStats.remaining', 12)
                ->has('todaySchedule')
                ->has('tomorrowSchedule')
                ->has('exam'));
    }

    public function test_student_receives_only_their_class_context(): void
    {
        [$institution, $user] = $this->userForRole('student');
        $section = ClassSection::create([
            'institution_id' => $institution->id,
            'class_name' => 'Class 8',
            'section_name' => 'Section A',
            'join_code' => 'CLS-TEST',
            'subjects' => [],
        ]);
        $user->update(['class_section_id' => $section->id]);

        Notice::create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'title' => 'Everyone notice',
            'message' => 'Visible to students.',
            'visibility' => 'All',
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('StudentDashboard')
                ->where('classLabel', 'Class 8 Section A')
                ->where('notices.0.title', 'Everyone notice')
                ->where('classroomFeed.sectionIds.0', (string) $section->id)
                ->has('todayRoutine')
                ->has('exam'));
    }

    private function userForRole(string $role): array
    {
        $institution = Institution::create(['name' => 'Test School']);
        $user = User::factory()->create([
            'role' => $role,
            'institution_id' => $institution->id,
        ]);

        return [$institution, $user];
    }
}
