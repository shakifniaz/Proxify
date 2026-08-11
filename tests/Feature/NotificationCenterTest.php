<?php

namespace Tests\Feature;

use App\Models\Institution;
use App\Models\Notice;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class NotificationCenterTest extends TestCase
{
    use RefreshDatabase;

    public function test_teacher_receives_visible_notice_notifications_and_can_mark_them_read(): void
    {
        $institution = Institution::create(['name' => 'Test School']);
        $teacher = User::factory()->create([
            'role' => 'teacher',
            'institution_id' => $institution->id,
        ]);

        Notice::create([
            'institution_id' => $institution->id,
            'user_id' => $teacher->id,
            'title' => 'Staff briefing',
            'message' => 'Bring updated lesson plans.',
            'visibility' => 'Teachers',
        ]);

        $payload = $this->actingAs($teacher)->getJson('/notifications')
            ->assertOk()
            ->assertJsonPath('items.0.title', 'Staff briefing')
            ->assertJsonPath('items.0.read', false)
            ->json();

        $this->postJson('/notifications/read', [
            'keys' => [$payload['items'][0]['key']],
        ])->assertOk();

        $this->assertDatabaseHas('notification_reads', [
            'user_id' => $teacher->id,
            'notification_key' => $payload['items'][0]['key'],
        ]);

        $this->getJson('/notifications')
            ->assertOk()
            ->assertJsonPath('items.0.read', true)
            ->assertJsonPath('unreadCount', 0);
    }

    public function test_students_do_not_receive_teacher_only_notices(): void
    {
        $institution = Institution::create(['name' => 'Test School']);
        $admin = User::factory()->create([
            'role' => 'admin',
            'institution_id' => $institution->id,
        ]);
        $student = User::factory()->create([
            'role' => 'student',
            'institution_id' => $institution->id,
        ]);

        Notice::create([
            'institution_id' => $institution->id,
            'user_id' => $admin->id,
            'title' => 'Teacher only',
            'message' => 'Not for students.',
            'visibility' => 'Teachers',
        ]);

        $this->actingAs($student)->getJson('/notifications')
            ->assertOk()
            ->assertJsonCount(0, 'items');

        $this->assertSame(0, DB::table('notification_reads')->count());
    }
}
