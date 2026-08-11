<?php

namespace Tests\Feature;

use App\Models\ClassSection;
use App\Models\Institution;
use App\Models\ProxyRun;
use App\Models\Routine;
use App\Models\TeacherProfile;
use App\Models\User;
use App\Services\WhatsAppRoutineMessenger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_settings_page_loads_administration_profile_notifications_and_cleanup_backend_data(): void
    {
        $institution = Institution::create([
            'name' => 'Test School',
            'short_name' => 'TS',
            'settings' => ['defaultNoticeVisibility' => 'Admins'],
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'institution_id' => $institution->id,
            'settings' => ['whatsappRoutineUpdates' => false],
        ]);

        $this->actingAs($admin)->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('role', 'admin')
                ->where('general.schoolName', 'Test School')
                ->where('general.shortName', 'TS')
                ->where('general.defaultNoticeVisibility', 'Admins')
                ->where('profile.email', $admin->email)
                ->where('notificationSettings.whatsappRoutineUpdates', false)
                ->has('cleanup')
            );
    }

    public function test_teacher_settings_page_loads_personal_backend_data_without_cleanup(): void
    {
        $institution = Institution::create(['name' => 'Test School']);
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'institution_id' => $institution->id,
            'phone' => '01722222222',
            'settings' => ['whatsappRoutineUpdates' => true],
        ]);

        $this->actingAs($teacher)->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('role', 'teacher')
                ->where('profile.phone', '01722222222')
                ->where('notificationSettings.whatsappRoutineUpdates', true)
                ->where('cleanup', null)
            );
    }

    public function test_student_settings_page_loads_personal_backend_data_without_institution_fallback(): void
    {
        Institution::create([
            'name' => 'Other School',
            'settings' => ['defaultNoticeVisibility' => 'Admins'],
        ]);
        $student = User::factory()->create([
            'role' => 'student',
            'institution_id' => null,
        ]);

        $this->actingAs($student)->get('/settings')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Settings/Index')
                ->where('role', 'student')
                ->where('general.schoolName', '')
                ->where('general.defaultNoticeVisibility', 'Teachers')
                ->where('cleanup', null)
            );
    }

    public function test_admin_notice_visibility_default_is_used_for_new_notices(): void
    {
        $institution = Institution::create([
            'name' => 'Test School',
            'settings' => ['defaultNoticeVisibility' => 'All'],
        ]);
        $admin = User::factory()->create([
            'role' => 'admin',
            'institution_id' => $institution->id,
        ]);

        $this->actingAs($admin)->post('/noticeboard', [
            'board' => 'institutional',
            'title' => 'Holiday',
            'message' => 'School is closed tomorrow.',
            'urgency' => 'Normal',
        ])->assertRedirect();

        $this->assertDatabaseHas('notices', [
            'title' => 'Holiday',
            'visibility' => 'All',
        ]);
    }

    public function test_user_can_update_whatsapp_routine_notifications_from_settings(): void
    {
        $user = User::factory()->create([
            'role' => 'teacher',
            'settings' => ['whatsappRoutineUpdates' => true],
        ]);

        $this->actingAs($user)->put('/settings/notifications', [
            'whatsappRoutineUpdates' => false,
        ])->assertRedirect();

        $this->assertFalse($user->fresh()->settings['whatsappRoutineUpdates']);
    }

    public function test_student_can_update_whatsapp_routine_notifications_from_settings(): void
    {
        $student = User::factory()->create([
            'role' => 'student',
            'settings' => [],
        ]);

        $this->actingAs($student)->put('/settings/notifications', [
            'whatsappRoutineUpdates' => false,
        ])->assertRedirect();

        $this->assertFalse($student->fresh()->settings['whatsappRoutineUpdates']);
    }

    public function test_admin_can_update_general_settings(): void
    {
        $institution = Institution::create(['name' => 'Old School']);
        $admin = User::factory()->create([
            'role' => 'admin',
            'institution_id' => $institution->id,
        ]);

        $this->actingAs($admin)->put('/settings/general', [
            'schoolName' => 'New School',
            'shortName' => 'NS',
            'contactPhone' => '01733333333',
            'contactEmail' => 'office@example.com',
            'address' => '123 School Road',
            'academicYear' => '2026/27',
            'defaultNoticeVisibility' => 'All',
        ])->assertRedirect();

        $institution->refresh();
        $this->assertSame('New School', $institution->name);
        $this->assertSame('NS', $institution->short_name);
        $this->assertSame('All', $institution->settings['defaultNoticeVisibility']);
    }

    public function test_teacher_and_student_cannot_update_admin_general_settings_or_cleanup_data(): void
    {
        $teacher = User::factory()->create(['role' => 'teacher']);
        $student = User::factory()->create(['role' => 'student']);

        foreach ([$teacher, $student] as $user) {
            $this->actingAs($user)->put('/settings/general', [
                'schoolName' => 'Blocked',
                'defaultNoticeVisibility' => 'All',
            ])->assertForbidden();

            $this->actingAs($user)->delete('/settings/data', [
                'target' => 'classrooms',
                'confirmation' => 'CLEAR',
            ])->assertForbidden();
        }
    }

    public function test_admin_cleanup_clears_only_selected_institution_data(): void
    {
        $institution = Institution::create(['name' => 'Test School']);
        $otherInstitution = Institution::create(['name' => 'Other School']);
        $admin = User::factory()->create([
            'role' => 'admin',
            'institution_id' => $institution->id,
        ]);
        $classroom = ClassSection::create([
            'institution_id' => $institution->id,
            'class_name' => 'Class 8',
            'section_name' => 'A',
            'join_code' => 'C-001',
            'subjects' => [],
        ]);
        $otherClassroom = ClassSection::create([
            'institution_id' => $otherInstitution->id,
            'class_name' => 'Class 9',
            'section_name' => 'B',
            'join_code' => 'C-002',
            'subjects' => [],
        ]);

        $this->actingAs($admin)->delete('/settings/data', [
            'target' => 'classrooms',
            'confirmation' => 'CLEAR',
        ])->assertRedirect();

        $this->assertDatabaseMissing('class_sections', ['id' => $classroom->id]);
        $this->assertDatabaseHas('class_sections', ['id' => $otherClassroom->id]);
    }

    public function test_profile_updates_from_settings_redirect_back_to_settings(): void
    {
        $user = User::factory()->create([
            'name' => 'Old Name',
            'email' => 'old@example.com',
            'phone' => '01700000000',
        ]);

        $this->actingAs($user)
            ->withHeaders(['referer' => 'http://localhost/settings'])
            ->patch('/profile', [
                'name' => 'New Name',
                'email' => 'new@example.com',
                'phone' => '01800000000',
            ])
            ->assertRedirect('/settings');

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
            'name' => 'New Name',
            'email' => 'new@example.com',
            'phone' => '01800000000',
        ]);
    }

    public function test_password_can_be_updated_from_settings_backend_route(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->withHeaders(['referer' => 'http://localhost/settings'])
            ->put('/password', [
                'current_password' => 'password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ])
            ->assertRedirect('/settings');

        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_whatsapp_routine_messages_skip_users_who_turn_off_whatsapp_updates(): void
    {
        $institution = Institution::create(['name' => 'Test School']);
        $user = User::factory()->create([
            'role' => 'teacher',
            'institution_id' => $institution->id,
            'settings' => ['whatsappRoutineUpdates' => false],
        ]);
        $teacher = TeacherProfile::create([
            'institution_id' => $institution->id,
            'user_id' => $user->id,
            'name' => 'Ms. Rahman',
            'whatsapp_number' => '01711111111',
            'join_code' => 'T-001',
            'status' => 'Active',
        ]);
        $routine = Routine::create([
            'user_id' => $user->id,
            'institution_id' => $institution->id,
            'name' => 'Main Routine',
            'term_label' => 'Term 1',
            'status' => 'Active',
            'days' => ['Monday'],
            'periods' => [['key' => 'p1', 'label' => 'P1', 'type' => 'class']],
            'classes' => [],
            'teachers' => [],
            'generation_rules' => [],
            'generated_grid' => [],
            'teacher_schedule' => [],
            'metrics' => [],
        ]);
        $proxyRun = ProxyRun::create([
            'routine_id' => $routine->id,
            'user_id' => $user->id,
            'name' => 'Proxy Monday',
            'day_label' => 'Monday',
            'status' => 'Approved',
            'absent_teachers' => [],
            'subject_groups' => [],
            'assignments' => [],
            'adjustments' => [],
            'proxy_generated_grid' => [],
            'proxy_teacher_schedule' => [],
            'metrics' => [],
        ]);

        $summary = app(WhatsAppRoutineMessenger::class)->sendPreparedMessages($proxyRun, [[
            'teacherProfileId' => $teacher->id,
            'teacherName' => $teacher->name,
            'whatsappNumber' => '01711111111',
            'displayNumber' => '01711111111',
            'whatsappEnabled' => false,
            'message' => 'Your schedule for the day.',
        ]]);

        $this->assertSame(1, $summary['skipped']);
        $this->assertDatabaseHas('proxy_message_logs', [
            'proxy_run_id' => $proxyRun->id,
            'teacher_profile_id' => $teacher->id,
            'status' => 'skipped',
            'error_message' => 'Teacher has turned off WhatsApp routine updates.',
        ]);
    }
}
