<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canLogin' => Route::has('login'),
        'canRegister' => Route::has('register'),
        'laravelVersion' => Application::VERSION,
        'phpVersion' => PHP_VERSION,
    ]);
});

Route::middleware(['auth', 'verified'])->group(function () {
    // Dashboard — mock data shaped exactly like the future controller response
    Route::get('/dashboard', function () {
        return Inertia::render('Dashboard', [
            'alerts' => [
                '2 proxy periods unresolved',
                '2 leave requests pending',
                'Mr. Ahmed absent 3 days running',
            ],
            'routineSummary' => [
                'days' => 5, 'classes' => 12, 'teachers' => 18, 'termLabel' => 'Term 1 — 2025/26',
            ],
            'proxySummary' => [
                'absentToday' => 5, 'assignedToday' => 11, 'unresolvedToday' => 2,
            ],
            'weekStats' => [
                ['label' => 'Absent Teachers', 'sub' => 'Mon–Fri', 'value' => 3, 'color' => 'rose'],
                ['label' => 'Proxy Classes', 'sub' => 'Total assigned', 'value' => 35, 'color' => 'amber'],
                ['label' => 'Unresolved Classes', 'sub' => 'Need attention', 'value' => 2, 'color' => 'rose'],
            ],
            'today' => [
                'status' => 'Pending finalization', 'absentCount' => 5, 'proxiesAssigned' => 11,
                'unresolvedPeriods' => 2, 'ackRate' => 69,
            ],
            'monthStats' => [
                ['label' => 'Proxies this month', 'value' => 47, 'sub' => '18 teachers involved', 'color' => 'emerald'],
                ['label' => 'Absence streak', 'value' => 3, 'sub' => 'Mr. Ahmed — flagged', 'color' => 'rose'],
                ['label' => 'Leave pending', 'value' => 2, 'sub' => 'needs approval', 'color' => 'amber'],
            ],
            'liveActivity' => [
                ['id' => 1, 'text' => 'Proxy engine ran — 11 of 13 periods assigned', 'time' => '8:42', 'color' => 'emerald'],
                ['id' => 2, 'text' => 'Mr. Ahmed marked absent — 2 periods affected', 'time' => '8:38', 'color' => 'rose'],
                ['id' => 3, 'text' => 'P4 · Class 7C flagged — no teacher available', 'time' => '8:38', 'color' => 'amber'],
                ['id' => 4, 'text' => 'Ms. Karim submitted leave request', 'time' => '8:25', 'color' => 'sky'],
                ['id' => 5, 'text' => 'Notice posted — Staff meeting this Friday', 'time' => '8:10', 'color' => 'violet'],
            ],
            'todaysAbsences' => [
                ['teacher' => 'Mr. Ahmed', 'subject' => 'Physics', 'section' => '9C', 'periods' => 3, 'proxy' => '—', 'status' => 'Unresolved'],
                ['teacher' => 'Ms. Karim', 'subject' => 'English', 'section' => '8A', 'periods' => 2, 'proxy' => 'Mr. Hossain', 'status' => 'Assigned'],
                ['teacher' => 'Mr. Rahman', 'subject' => 'Math', 'section' => '7B', 'periods' => 2, 'proxy' => 'Ms. Sultana', 'status' => 'Assigned'],
                ['teacher' => 'Ms. Begum', 'subject' => 'Biology', 'section' => '10A', 'periods' => 1, 'proxy' => 'Mr. Islam', 'status' => 'Assigned'],
                ['teacher' => 'Mr. Chowdhury', 'subject' => 'Chemistry', 'section' => '9A', 'periods' => 3, 'proxy' => '—', 'status' => 'Unresolved'],
            ],
            'quickActions' => [
                ['label' => 'Mark Teacher Absent', 'icon' => 'UserX', 'href' => '#'],
                ['label' => 'Finalize Proxies', 'icon' => 'CheckCircle2', 'href' => '#'],
                ['label' => 'Post Notice', 'icon' => 'Megaphone', 'href' => '#'],
                ['label' => 'Create Routine', 'icon' => 'CalendarPlus', 'href' => '#'],
            ],
        ]);
    })->name('dashboard');

    // Routines — list
    Route::get('/routines', function () {
        return Inertia::render('Routines/Index', [
            'routines' => [
                ['id' => 1, 'name' => 'Main Routine', 'days' => 5, 'classes' => 12, 'sections' => 24, 'teachers' => 12, 'proxyClassesWeek' => 35, 'status' => 'Active'],
                ['id' => 2, 'name' => 'New Routine Draft 1', 'days' => 5, 'classes' => 12, 'sections' => 24, 'teachers' => 14, 'proxyClassesWeek' => 6, 'status' => 'Draft'],
                ['id' => 3, 'name' => 'Exam Routine', 'days' => 6, 'classes' => 0, 'sections' => 24, 'teachers' => 12, 'proxyClassesWeek' => 0, 'status' => 'Draft'],
            ],
        ]);
    })->name('routines.index');

    Route::get('/routines/create', fn () => Inertia::render('Routines/Create'))->name('routines.create');

    // Routines — grid view (3 distinct mock datasets, keyed by id)
    Route::get('/routines/{routine}', function ($routine) {
        $meta = [
            1 => ['name' => 'Main Routine', 'term' => '2025/26', 'status' => 'Active'],
            2 => ['name' => 'New Routine Draft 1', 'term' => '2025/26', 'status' => 'Draft'],
            3 => ['name' => 'Exam Routine', 'term' => '2025/26', 'status' => 'Draft'],
        ];

        $days = [
            1 => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu'],
            2 => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu'],
            3 => ['Sun', 'Mon', 'Tue', 'Wed', 'Thu', 'Fri'],
        ];

        $periods = [
            ['key' => 'P1', 'label' => 'P1', 'time' => '8:00–8:45'],
            ['key' => 'P2', 'label' => 'P2', 'time' => '8:45–9:30'],
            ['key' => 'BREAK', 'label' => 'BREAK', 'type' => 'break'],
            ['key' => 'P3', 'label' => 'P3', 'time' => '9:45–10:30'],
            ['key' => 'P4', 'label' => 'P4', 'time' => '10:30–11:15'],
            ['key' => 'P5', 'label' => 'P5', 'time' => '11:15–12:00'],
            ['key' => 'LUNCH', 'label' => 'LUNCH', 'type' => 'lunch'],
            ['key' => 'P6', 'label' => 'P6', 'time' => '1:00–1:45'],
            ['key' => 'P7', 'label' => 'P7', 'time' => '1:45–2:30'],
        ];

        $legend = [
            ['subject' => 'Math', 'color' => 'blue'],
            ['subject' => 'English', 'color' => 'amber'],
            ['subject' => 'Science', 'color' => 'emerald'],
            ['subject' => 'History', 'color' => 'indigo'],
            ['subject' => 'Physics', 'color' => 'cyan'],
            ['subject' => 'Bangla', 'color' => 'rose'],
        ];

        $teacherSets = [
            // 1. Main Routine — fully built, Active, proxy engine already ran
            1 => [
                [
                    'name' => 'Mr. Rahman', 'subject' => 'Mathematics',
                    'cells' => [
                        'P1' => ['type' => 'class', 'subject' => 'Math', 'classLabel' => 'Class 9A', 'color' => 'blue'],
                        'P2' => ['type' => 'class', 'subject' => 'Math', 'classLabel' => 'Class 8B', 'color' => 'blue'],
                        'P3' => ['type' => 'empty'],
                        'P4' => ['type' => 'class', 'subject' => 'Math', 'classLabel' => 'Class 10A', 'color' => 'blue'],
                        'P5' => ['type' => 'empty'],
                        'P6' => ['type' => 'class', 'subject' => 'Math', 'classLabel' => 'Class 7C', 'color' => 'blue'],
                        'P7' => ['type' => 'empty'],
                    ],
                ],
                [
                    'name' => 'Ms. Karim', 'subject' => 'English',
                    'cells' => [
                        'P1' => ['type' => 'class', 'subject' => 'English', 'classLabel' => 'Class 10A', 'color' => 'amber'],
                        'P2' => ['type' => 'empty'],
                        'P3' => ['type' => 'class', 'subject' => 'English', 'classLabel' => 'Class 9B', 'color' => 'amber'],
                        'P4' => ['type' => 'class', 'subject' => 'English', 'classLabel' => 'Class 8A', 'color' => 'amber'],
                        'P5' => ['type' => 'empty'],
                        'P6' => ['type' => 'empty'],
                        'P7' => ['type' => 'class', 'subject' => 'Eng 2nd', 'classLabel' => 'Class XI B', 'color' => 'amber'],
                    ],
                ],
                [
                    'name' => 'Mr. Hossain', 'subject' => 'Physics',
                    'cells' => [
                        'P1' => ['type' => 'empty'],
                        'P2' => ['type' => 'class', 'subject' => 'Physics', 'classLabel' => 'Class 10B', 'color' => 'cyan'],
                        'P3' => ['type' => 'class', 'subject' => 'Physics', 'classLabel' => 'Class 9A', 'color' => 'cyan'],
                        'P4' => ['type' => 'unresolved', 'classLabel' => 'Class 7C'],
                        'P5' => ['type' => 'class', 'subject' => 'Physics', 'classLabel' => 'Class 8C', 'color' => 'cyan'],
                        'P6' => ['type' => 'empty'],
                        'P7' => ['type' => 'class', 'subject' => 'Physics', 'classLabel' => 'Class XI A', 'color' => 'cyan'],
                    ],
                ],
                [
                    'name' => 'Ms. Islam', 'subject' => 'History',
                    'cells' => [
                        'P1' => ['type' => 'class', 'subject' => 'History', 'classLabel' => 'Class 8A', 'color' => 'indigo'],
                        'P2' => ['type' => 'class', 'subject' => 'History', 'classLabel' => 'Class 7B', 'color' => 'indigo'],
                        'P3' => ['type' => 'empty'],
                        'P4' => ['type' => 'proxy', 'subject' => 'History', 'classLabel' => 'Class 9C'],
                        'P5' => ['type' => 'class', 'subject' => 'History', 'classLabel' => 'Class 9C', 'color' => 'indigo'],
                        'P6' => ['type' => 'class', 'subject' => 'History', 'classLabel' => 'Class 10B', 'color' => 'indigo'],
                        'P7' => ['type' => 'empty'],
                    ],
                ],
                [
                    'name' => 'Mr. Ahmed', 'subject' => 'Bangla',
                    'cells' => [
                        'P1' => ['type' => 'class', 'subject' => 'Bangla', 'classLabel' => 'Class 6A', 'color' => 'rose'],
                        'P2' => ['type' => 'empty'],
                        'P3' => ['type' => 'class', 'subject' => 'Bangla', 'classLabel' => 'Class 7A', 'color' => 'rose'],
                        'P4' => ['type' => 'class', 'subject' => 'Bangla', 'classLabel' => 'Class 8B', 'color' => 'rose'],
                        'P5' => ['type' => 'empty'],
                        'P6' => ['type' => 'class', 'subject' => 'Bangla', 'classLabel' => 'Class 9B', 'color' => 'rose'],
                        'P7' => ['type' => 'empty'],
                    ],
                ],
            ],

            // 2. New Routine Draft 1 — partially built, no unresolved periods yet
            2 => [
                [
                    'name' => 'Mr. Sarkar', 'subject' => 'Mathematics',
                    'cells' => [
                        'P1' => ['type' => 'class', 'subject' => 'Math', 'classLabel' => 'Class 6A', 'color' => 'blue'],
                        'P2' => ['type' => 'empty'],
                        'P3' => ['type' => 'class', 'subject' => 'Math', 'classLabel' => 'Class 7B', 'color' => 'blue'],
                        'P4' => ['type' => 'empty'],
                        'P5' => ['type' => 'class', 'subject' => 'Math', 'classLabel' => 'Class 8A', 'color' => 'blue'],
                        'P6' => ['type' => 'empty'],
                        'P7' => ['type' => 'empty'],
                    ],
                ],
                [
                    'name' => 'Mrs. Akter', 'subject' => 'English',
                    'cells' => [
                        'P1' => ['type' => 'empty'],
                        'P2' => ['type' => 'class', 'subject' => 'English', 'classLabel' => 'Class 9A', 'color' => 'amber'],
                        'P3' => ['type' => 'empty'],
                        'P4' => ['type' => 'class', 'subject' => 'English', 'classLabel' => 'Class 10B', 'color' => 'amber'],
                        'P5' => ['type' => 'empty'],
                        'P6' => ['type' => 'class', 'subject' => 'English', 'classLabel' => 'Class 6B', 'color' => 'amber'],
                        'P7' => ['type' => 'empty'],
                    ],
                ],
                [
                    'name' => 'Mr. Talukder', 'subject' => 'Science',
                    'cells' => [
                        'P1' => ['type' => 'class', 'subject' => 'Science', 'classLabel' => 'Class 8B', 'color' => 'emerald'],
                        'P2' => ['type' => 'empty'],
                        'P3' => ['type' => 'empty'],
                        'P4' => ['type' => 'class', 'subject' => 'Science', 'classLabel' => 'Class 9C', 'color' => 'emerald'],
                        'P5' => ['type' => 'empty'],
                        'P6' => ['type' => 'empty'],
                        'P7' => ['type' => 'class', 'subject' => 'Science', 'classLabel' => 'Class 7A', 'color' => 'emerald'],
                    ],
                ],
                [
                    'name' => 'Ms. Nasrin', 'subject' => 'Bangla',
                    'cells' => [
                        'P1' => ['type' => 'empty'],
                        'P2' => ['type' => 'empty'],
                        'P3' => ['type' => 'class', 'subject' => 'Bangla', 'classLabel' => 'Class 10A', 'color' => 'rose'],
                        'P4' => ['type' => 'proxy', 'subject' => 'Bangla', 'classLabel' => 'Class 8C'],
                        'P5' => ['type' => 'empty'],
                        'P6' => ['type' => 'class', 'subject' => 'Bangla', 'classLabel' => 'Class 6B', 'color' => 'rose'],
                        'P7' => ['type' => 'empty'],
                    ],
                ],
            ],

            // 3. Exam Routine — brand-new draft, nothing assigned yet (0 classes)
            3 => [
                ['name' => 'Mr. Rahman', 'subject' => 'Not assigned', 'cells' => array_fill_keys(['P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7'], ['type' => 'empty'])],
                ['name' => 'Ms. Karim', 'subject' => 'Not assigned', 'cells' => array_fill_keys(['P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7'], ['type' => 'empty'])],
                ['name' => 'Mr. Hossain', 'subject' => 'Not assigned', 'cells' => array_fill_keys(['P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7'], ['type' => 'empty'])],
                ['name' => 'Ms. Islam', 'subject' => 'Not assigned', 'cells' => array_fill_keys(['P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7'], ['type' => 'empty'])],
                ['name' => 'Mr. Ahmed', 'subject' => 'Not assigned', 'cells' => array_fill_keys(['P1', 'P2', 'P3', 'P4', 'P5', 'P6', 'P7'], ['type' => 'empty'])],
            ],
        ];

        $id = (int) $routine;
        $set = $meta[$id] ?? $meta[1];
        $teacherData = $teacherSets[$id] ?? $teacherSets[1];

        return Inertia::render('Routines/Show', [
            'routine' => array_merge(['id' => $id], $set),
            'days' => $days[$id] ?? $days[1],
            'periods' => $periods,
            'legend' => $legend,
            'teachers' => $teacherData,
        ]);
    })->name('routines.show');

    Route::get('/proxy-manager', fn () => Inertia::render('ProxyManager/Index'))->name('proxy-manager.index');
    Route::get('/exam-schedule', fn () => Inertia::render('ExamSchedule/Index'))->name('exam-schedule.index');
    Route::get('/leave-requests', fn () => Inertia::render('LeaveRequests/Index'))->name('leave-requests.index');
    Route::get('/noticeboard', fn () => Inertia::render('Noticeboard/Index'))->name('noticeboard.index');
    Route::get('/staff-room', fn () => Inertia::render('StaffRoom/Index'))->name('staff-room.index');
    Route::get('/classrooms', fn () => Inertia::render('Classrooms/Index'))->name('classrooms.index');
    Route::get('/analytics', fn () => Inertia::render('Analytics/Index'))->name('analytics.index');
    Route::get('/teachers', fn () => Inertia::render('Teachers/Index'))->name('teachers.index');
    Route::get('/settings', fn () => Inertia::render('Settings/Index'))->name('settings.index');
});

Route::middleware('auth')->group(function () {
    Route::get('/profile', [App\Http\Controllers\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [App\Http\Controllers\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [App\Http\Controllers\ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';