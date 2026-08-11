<?php

namespace Tests\Feature;

use App\Models\ClassSection;
use App\Models\Institution;
use App\Models\Routine;
use App\Models\TeacherProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SearchFeatureTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_search_includes_only_their_classroom_subjects(): void
    {
        $institution = Institution::create(['name' => 'Test School']);
        $ownSection = ClassSection::create([
            'institution_id' => $institution->id,
            'class_name' => 'Class 8',
            'section_name' => 'Section A',
            'join_code' => 'CLS-OWN',
            'subjects' => [['id' => 'math', 'name' => 'Mathematics']],
        ]);
        ClassSection::create([
            'institution_id' => $institution->id,
            'class_name' => 'Class 9',
            'section_name' => 'Section B',
            'join_code' => 'CLS-OTHER',
            'subjects' => [['id' => 'science', 'name' => 'Science']],
        ]);
        $student = User::factory()->create([
            'role' => 'student',
            'institution_id' => $institution->id,
            'class_section_id' => $ownSection->id,
        ]);

        $items = $this->actingAs($student)->getJson('/search/features')
            ->assertOk()
            ->json('items');

        $this->assertContains('Class 8 Section A - Mathematics', array_column($items, 'name'));
        $this->assertNotContains('Class 9 Section B - Science', array_column($items, 'name'));
    }

    public function test_teacher_search_includes_assigned_subject_classrooms(): void
    {
        $institution = Institution::create(['name' => 'Test School']);
        $teacherUser = User::factory()->create([
            'role' => 'teacher',
            'institution_id' => $institution->id,
            'name' => 'Nadia Khan',
        ]);
        $teacher = TeacherProfile::create([
            'institution_id' => $institution->id,
            'user_id' => $teacherUser->id,
            'name' => 'Nadia Khan',
            'join_code' => 'TCH-NADIA',
        ]);
        $teacherUser->update(['teacher_profile_id' => $teacher->id]);

        ClassSection::create([
            'institution_id' => $institution->id,
            'class_name' => 'Class 7',
            'section_name' => 'Section C',
            'join_code' => 'CLS-C',
            'subjects' => [[
                'id' => 'english',
                'name' => 'English',
                'teacherId' => (string) $teacher->id,
                'teacherName' => 'Nadia Khan',
            ]],
        ]);

        $items = $this->actingAs($teacherUser)->getJson('/search/features')
            ->assertOk()
            ->json('items');

        $this->assertContains('Class 7 Section C - English', array_column($items, 'name'));
    }

    public function test_teacher_search_includes_subjects_assigned_in_active_routine_even_without_directory_subjects(): void
    {
        $institution = Institution::create(['name' => 'Test School']);
        $teacherUser = User::factory()->create([
            'role' => 'teacher',
            'institution_id' => $institution->id,
            'name' => 'Arif Hasan',
        ]);
        $teacher = TeacherProfile::create([
            'institution_id' => $institution->id,
            'user_id' => $teacherUser->id,
            'name' => 'Arif Hasan',
            'join_code' => 'TCH-ARIF',
        ]);
        $teacherUser->update(['teacher_profile_id' => $teacher->id]);

        $section = ClassSection::create([
            'institution_id' => $institution->id,
            'class_name' => 'Class 10',
            'section_name' => 'Section B',
            'join_code' => 'CLS-TEN-B',
            'subjects' => [],
        ]);

        Routine::create([
            'institution_id' => $institution->id,
            'user_id' => $teacherUser->id,
            'name' => 'Routine',
            'status' => 'Active',
            'days' => ['Sun'],
            'periods' => [],
            'teachers' => [['id' => (string) $teacher->id, 'name' => 'Arif Hasan']],
            'classes' => [[
                'id' => 'class-10',
                'name' => 'Class 10',
                'sections' => [[
                    'id' => (string) $section->id,
                    'name' => 'Section B',
                    'subjects' => [[
                        'id' => 'physics',
                        'name' => 'Physics',
                        'teacherId' => (string) $teacher->id,
                        'teacherName' => 'Arif Hasan',
                    ]],
                ]],
            ]],
        ]);

        $items = $this->actingAs($teacherUser)->getJson('/search/features')
            ->assertOk()
            ->json('items');

        $this->assertContains('Class 10 Section B - Physics', array_column($items, 'name'));
    }

    public function test_admin_search_includes_all_classrooms(): void
    {
        $institution = Institution::create(['name' => 'Test School']);
        $admin = User::factory()->create([
            'role' => 'admin',
            'institution_id' => $institution->id,
        ]);

        foreach (['Class 6', 'Class 7', 'Class 8'] as $index => $className) {
            ClassSection::create([
                'institution_id' => $institution->id,
                'class_name' => $className,
                'section_name' => 'Section A',
                'join_code' => 'CLS-ADMIN-'.$index,
                'subjects' => [['id' => 'math-'.$index, 'name' => 'Mathematics']],
            ]);
        }

        $items = $this->actingAs($admin)->getJson('/search/features')
            ->assertOk()
            ->json('items');

        $names = array_column($items, 'name');

        $this->assertContains('Class 6 Section A', $names);
        $this->assertContains('Class 7 Section A', $names);
        $this->assertContains('Class 8 Section A', $names);
    }
}
