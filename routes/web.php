<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ClassSectionController;
use App\Http\Controllers\ExamScheduleController;
use App\Http\Controllers\LeaveRequestController;
use App\Http\Controllers\NoticeController;
use App\Http\Controllers\ProxyRunController;
use App\Http\Controllers\RoutineController;
use App\Http\Controllers\StaffRoomController;
use App\Http\Controllers\TeacherController;
use App\Models\ClassSection;
use App\Models\Routine;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
        $role = strtolower($request->user()?->role ?? 'admin');

        if ($role === 'teacher') {
            return Inertia::render('TeacherDashboard', [
                'teacherName' => $request->user()?->name ?? 'Teacher',
                'dateLabel' => now()->format('l, F j, Y'),
                'stats' => [
                    'classesToday' => 0,
                    'proxiesToday' => 0,
                    'pendingLeaveDays' => 0,
                ],
                'urgentNotices' => [],
                'proxyAssignments' => [],
                'todaySchedule' => [],
                'tomorrowSchedule' => [],
            ]);
        }

        if ($role === 'student') {
            return Inertia::render('StudentDashboard', [
                'studentName' => $request->user()?->name ?? 'Student',
                'classLabel' => '',
                'dateLabel' => now()->format('l, F j, Y'),
                'stats' => [
                    'classesToday' => 0,
                    'notices' => 0,
                    'assignments' => 0,
                ],
                'todayRoutine' => [],
                'notices' => [],
                'classroomUpdates' => [],
            ]);
        }

        return Inertia::render('Dashboard', [
            'alerts' => [],
            'routineSummary' => [
                'days' => 0,
                'classes' => 0,
                'teachers' => 0,
                'termLabel' => 'No active routine',
            ],
            'proxySummary' => [
                'absentToday' => 0,
                'assignedToday' => 0,
                'unresolvedToday' => 0,
            ],
            'weekStats' => [],
            'today' => [
                'status' => 'No proxy plan generated',
                'absentCount' => 0,
                'proxiesAssigned' => 0,
                'unresolvedPeriods' => 0,
                'ackRate' => 0,
            ],
            'monthStats' => [],
            'liveActivity' => [],
            'todaysAbsences' => [],
            'quickActions' => [],
        ]);
    })->name('dashboard');

    Route::get('/routines', [RoutineController::class, 'index'])->name('routines.index');
    Route::get('/routines/create', [RoutineController::class, 'create'])->name('routines.create');
    Route::post('/routines', [RoutineController::class, 'store'])->name('routines.store');
    Route::post('/routines/import', [RoutineController::class, 'import'])->name('routines.import');
    Route::get('/routines/{routine}', [RoutineController::class, 'show'])->name('routines.show');
    Route::post('/routines/{routine}/activate', [RoutineController::class, 'activate'])->name('routines.activate');
    Route::patch('/routines/{routine}/rename', [RoutineController::class, 'rename'])->name('routines.rename');
    Route::delete('/routines/{routine}', [RoutineController::class, 'destroy'])->name('routines.destroy');
    Route::post('/routines/{routine}/regenerate', [RoutineController::class, 'regenerate'])->name('routines.regenerate');

    Route::get('/proxy-manager', [ProxyRunController::class, 'index'])->name('proxy-manager.index');
    Route::post('/proxy-manager', [ProxyRunController::class, 'store'])->name('proxy-manager.store');
    Route::put('/proxy-manager/subject-groups', [ProxyRunController::class, 'saveSubjectGroups'])->name('proxy-manager.subject-groups.save');
    Route::get('/proxy-manager/{proxyRun}', [ProxyRunController::class, 'show'])->name('proxy-manager.show');
    Route::put('/proxy-manager/{proxyRun}/routine', [ProxyRunController::class, 'updateRoutine'])->name('proxy-manager.routine.update');
    Route::post('/proxy-manager/{proxyRun}/approve', [ProxyRunController::class, 'approve'])->name('proxy-manager.approve');

    Route::get('/exam-schedule', [ExamScheduleController::class, 'index'])->name('exam-schedule.index');
    Route::get('/exam-schedule/new', [ExamScheduleController::class, 'create'])->name('exam-schedule.create');
    Route::post('/exam-schedule', [ExamScheduleController::class, 'store'])->name('exam-schedule.store');
    Route::get('/exam-schedule/{examSchedule}', [ExamScheduleController::class, 'show'])->name('exam-schedule.show');
    Route::put('/exam-schedule/{examSchedule}', [ExamScheduleController::class, 'update'])->name('exam-schedule.update');
    Route::delete('/exam-schedule/{examSchedule}', [ExamScheduleController::class, 'destroy'])->name('exam-schedule.destroy');
    Route::post('/exam-schedule/{examSchedule}/activate', [ExamScheduleController::class, 'activate'])->name('exam-schedule.activate');

    Route::get('/leave-requests', [LeaveRequestController::class, 'index'])->name('leave-requests.index');
    Route::post('/leave-requests', [LeaveRequestController::class, 'store'])->name('leave-requests.store');
    Route::patch('/leave-requests/{leaveRequest}/status', [LeaveRequestController::class, 'updateStatus'])->name('leave-requests.status.update');
    Route::put('/leave-requests/allowances', [LeaveRequestController::class, 'updateAllowance'])->name('leave-requests.allowances.update');

    Route::get('/noticeboard', [NoticeController::class, 'index'])->name('noticeboard.index');
    Route::post('/noticeboard', [NoticeController::class, 'store'])->name('noticeboard.store');
    Route::patch('/noticeboard/{notice}', [NoticeController::class, 'update'])->name('noticeboard.update');
    Route::delete('/noticeboard/{notice}', [NoticeController::class, 'destroy'])->name('noticeboard.destroy');
    Route::post('/noticeboard/{notice}/acknowledge', [NoticeController::class, 'acknowledge'])->name('noticeboard.acknowledge');

    Route::get('/staffroom', [StaffRoomController::class, 'index'])->name('staffroom.index');

    Route::get('/staff-room', function () {
        return redirect()->route('staffroom.index');
    })->name('staff-room.index');

    Route::get('/classroom', function (\Illuminate\Http\Request $request) {
        $user = $request->user();
        $role = strtolower($user?->role ?? 'admin');
        $institutionId = $user?->institution_id;
        $routine = Routine::query()
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->where('status', 'Active')
            ->latest()
            ->first()
            ?? Routine::query()
                ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
                ->latest()
                ->first();

        $teacherProfile = $user?->teacherProfile;
        $teacherId = (string) ($user?->teacher_profile_id ?? '');
        $teacherName = $teacherProfile?->name ?? $user?->name;
        $teacherNames = collect($routine?->teachers ?? [])
            ->mapWithKeys(fn ($teacher) => [
                (string) ($teacher['id'] ?? $teacher['name'] ?? '') => $teacher['name'] ?? 'Teacher',
            ])
            ->filter();

        $directorySections = ClassSection::query()
            ->with('classTeacher:id,name')
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->orderBy('sort_order')
            ->orderBy('class_name')
            ->orderBy('section_name')
            ->get()
            ->keyBy(fn (ClassSection $section) => (string) $section->id);

        $gridBySectionId = collect($routine?->generated_grid ?? [])
            ->keyBy(fn ($section) => (string) ($section['sectionId'] ?? $section['id'] ?? ''));

        $classrooms = collect($routine?->classes ?? [])
            ->flatMap(function ($class) use ($gridBySectionId) {
                return collect($class['sections'] ?? [])->map(function ($section) use ($class, $gridBySectionId) {
                    $sectionId = (string) ($section['id'] ?? '');

                    return [
                        ...$section,
                        'className' => $class['name'] ?? $section['className'] ?? 'Class',
                        'routineClassId' => (string) ($class['id'] ?? ''),
                        'grid' => $gridBySectionId->get($sectionId, []),
                    ];
                });
            })
            ->map(function (array $section) use ($role, $teacherId, $teacherName, $teacherNames, $directorySections, $user) {
                $sectionId = (string) ($section['id'] ?? '');
                $directory = $directorySections->get($sectionId);
                $grid = $section['grid'] ?? [];

                $subjects = collect($section['subjects'] ?? [])
                    ->map(function ($subject, int $index) use ($teacherNames) {
                        $name = is_array($subject) ? ($subject['name'] ?? $subject['subject'] ?? 'Subject') : $subject;
                        $subjectTeacherId = is_array($subject) ? (string) ($subject['teacherId'] ?? $subject['teacher_id'] ?? '') : '';
                        $subjectTeacherName = is_array($subject)
                            ? ($subject['teacherName'] ?? $subject['teacher'] ?? $teacherNames->get($subjectTeacherId))
                            : null;

                        return [
                            'id' => md5($name.'-'.$subjectTeacherId.'-'.$index),
                            'name' => trim((string) $name),
                            'teacherId' => $subjectTeacherId,
                            'teacherName' => $subjectTeacherName ?: ($subjectTeacherId ? $teacherNames->get($subjectTeacherId, 'Assigned teacher') : 'Assigned teacher'),
                        ];
                    });

                $gridSubjects = collect($grid['days'] ?? [])
                    ->flatMap(fn ($dayCells) => collect($dayCells)->filter(fn ($cell) => ($cell['type'] ?? 'empty') === 'class'))
                    ->map(function ($cell) use ($teacherNames) {
                        $subjectTeacherId = (string) ($cell['teacherId'] ?? '');

                        return [
                            'id' => md5(($cell['subject'] ?? 'Subject').'-'.$subjectTeacherId),
                            'name' => trim((string) ($cell['subject'] ?? 'Subject')),
                            'teacherId' => $subjectTeacherId,
                            'teacherName' => $cell['teacherName'] ?? $teacherNames->get($subjectTeacherId, 'Assigned teacher'),
                        ];
                    });

                $subjects = $subjects
                    ->merge($gridSubjects)
                    ->filter(fn ($subject) => filled($subject['name']))
                    ->unique(fn ($subject) => strtolower($subject['name']).'|'.($subject['teacherId'] ?: strtolower($subject['teacherName'])))
                    ->values();

            $classTeacherId = (string) ($section['classTeacherId'] ?? $directory?->class_teacher_profile_id ?? '');
            $isClassTeacher = $teacherId !== '' && $classTeacherId === $teacherId;
            if ($role === 'teacher' && ! $isClassTeacher) {
                $subjects = $subjects->filter(fn ($subject) =>
                    (string) ($subject['teacherId'] ?? '') === $teacherId
                    || strcasecmp((string) ($subject['teacherName'] ?? ''), (string) $teacherName) === 0
                )->values();
            }

            return [
                'id' => $sectionId,
                'name' => $grid['label'] ?? trim(($section['className'] ?? 'Class').' '.($section['name'] ?? 'Section')),
                'className' => $section['className'] ?? $grid['className'] ?? 'Class',
                'sectionName' => $section['name'] ?? $grid['sectionName'] ?? 'Section',
                'classTeacherName' => $directory?->classTeacher?->name ?? $teacherNames->get($classTeacherId, 'Unassigned'),
                'classTeacherId' => $classTeacherId,
                'access' => $role === 'admin' ? 'All subjects' : ($isClassTeacher ? 'Class teacher' : ucfirst($role)),
                'subjects' => $subjects->map(function ($subject, int $index) use ($role, $teacherId, $teacherName, $isClassTeacher) {
                    $assignedToCurrentTeacher = (string) ($subject['teacherId'] ?? '') === $teacherId
                        || strcasecmp((string) ($subject['teacherName'] ?? ''), (string) $teacherName) === 0;

                    return [
                        ...$subject,
                        'accent' => ['#09B884', '#8BED9A', '#14B8A6', '#84CC16', '#22C55E'][$index % 5],
                        'canPost' => $role === 'admin' || ($role === 'teacher' && ($isClassTeacher || $assignedToCurrentTeacher)),
                    ];
                })->values(),
            ];
            })
            ->when($role === 'student' && $user?->class_section_id, fn ($collection) =>
                $collection->filter(fn ($classroom) => (string) $classroom['id'] === (string) $user->class_section_id)
            )
            ->filter(fn ($classroom) => $role !== 'teacher' || count($classroom['subjects']) > 0)
            ->values();

        return Inertia::render('Classroom/Index', [
            'classrooms' => $classrooms,
            'role' => $role,
            'activeRoutineName' => $routine?->name,
            'currentUser' => [
                'id' => (string) ($user?->id ?? 'guest'),
                'name' => $user?->teacherProfile?->name ?? $user?->name ?? 'User',
                'role' => $role,
                'institutionId' => $institutionId ? (string) $institutionId : 'global',
                'teacherProfileId' => $user?->teacher_profile_id ? (string) $user->teacher_profile_id : null,
                'classSectionId' => $user?->class_section_id ? (string) $user->class_section_id : null,
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
    })->name('classroom.index');

    Route::get('/classrooms', [ClassSectionController::class, 'index'])->name('classrooms.index');
    Route::post('/classrooms', [ClassSectionController::class, 'store'])->name('classrooms.store');
    Route::patch('/classrooms/{classSection}', [ClassSectionController::class, 'update'])->name('classrooms.update');
    Route::delete('/classrooms/class-group', [ClassSectionController::class, 'destroyClass'])->name('classrooms.class.destroy');
    Route::delete('/classrooms/{classSection}', [ClassSectionController::class, 'destroy'])->name('classrooms.destroy');



    Route::get('/analytics', function () {
        return Inertia::render('Analytics/Index', [
            'stats' => [
                'totalAbsences' => ['value' => 0, 'delta' => 'No data yet'],
                'proxyClasses' => ['value' => 0, 'delta' => 'No data yet'],
                'unresolved' => ['value' => 0, 'delta' => 'No data yet'],
                'ackRate' => ['value' => 0, 'delta' => 'No data yet'],
            ],
            'rangeOptions' => ['This week', 'This month', 'This term', 'Custom range'],
            'chartLabel' => 'No analytics data yet',
            'dailyAbsences' => [],
            'proxyLoad' => [],
            'heatmapDays' => [],
            'heatmap' => [],
        ]);
    })->name('analytics.index');
    
    Route::get('/teachers', [TeacherController::class, 'index'])->name('teachers.index');
    Route::post('/teachers', [TeacherController::class, 'store'])->name('teachers.store');
    Route::patch('/teachers/{teacher}', [TeacherController::class, 'update'])->name('teachers.update');
    Route::delete('/teachers/{teacher}', [TeacherController::class, 'destroy'])->name('teachers.destroy');


    Route::get('/settings', function () {
        return Inertia::render('Settings/Index', [
            'general' => [
                'schoolName' => '',
                'contactPhone' => '',
                'contactEmail' => '',
                'termLabel' => '',
                'weekStartDay' => 'Sunday',
                'academicYear' => '',
            ],
            'periods' => [],
            'notifications' => [],
            'weekStartOptions' => ['Sunday', 'Monday'],
        ]);
    })->name('settings.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';

