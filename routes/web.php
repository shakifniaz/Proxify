<?php

use Illuminate\Foundation\Application;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\ProxyRunController;
use App\Http\Controllers\RoutineController;
use App\Models\Routine;
use App\Models\TeacherLeaveAllowance;
use Inertia\Inertia;

Route::get('/', function () {
    if (auth()->check()) {
        return redirect()->route('dashboard');
    }
    return redirect()->route('login');
});

Route::middleware(['auth', 'verified'])->group(function () {
    Route::get('/dashboard', function (\Illuminate\Http\Request $request) {
        $role = $request->user()?->role ?? 'admin'; // Defaults to admin for your current setup

        if (strtolower($role) === 'teacher') {
            return Inertia::render('TeacherDashboard', [
                'teacherName' => 'Shakif Niaz',
                'dateLabel' => 'Tuesday, June 23, 2026',
                'stats' => [
                    'classesToday' => 4,
                    'proxiesToday' => 1,
                    'pendingLeaveDays' => 0,
                ],
                'urgentNotices' => [
                    ['id' => 1, 'title' => 'Staff meeting — mandatory attendance', 'message' => 'All teaching staff must attend the departmental meeting this Friday at 2:00 PM in the conference hall.'],
                ],
                'proxyAssignments' => [
                    ['id' => 1, 'date' => 'Today', 'period' => 'P4', 'subject' => 'Mathematics', 'classLabel' => 'Class 8C', 'coveringFor' => 'Mr. Ahmed'],
                ],
                'todaySchedule' => [
                    ['period' => 'P1', 'time' => '8:00–8:45', 'type' => 'class', 'subject' => 'Physics', 'classLabel' => 'Class 10A', 'room' => 'Science Lab A', 'isProxy' => false],
                    ['period' => 'P2', 'time' => '8:45–9:30', 'type' => 'empty'],
                    ['period' => 'BREAK', 'time' => '9:30–9:45', 'type' => 'break', 'label' => 'Morning Break'],
                    ['period' => 'P3', 'time' => '9:45–10:30', 'type' => 'class', 'subject' => 'Physics', 'classLabel' => 'Class 9B', 'room' => 'Room 402', 'isProxy' => false],
                    ['period' => 'P4', 'time' => '10:30–11:15', 'type' => 'class', 'subject' => 'Mathematics', 'classLabel' => 'Class 8C', 'room' => 'Room 301', 'isProxy' => true],
                    ['period' => 'P5', 'time' => '11:15–12:00', 'type' => 'empty'],
                    ['period' => 'LUNCH', 'time' => '12:00–1:00', 'type' => 'break', 'label' => 'Lunch Break'],
                    ['period' => 'P6', 'time' => '1:00–1:45', 'type' => 'class', 'subject' => 'Physics', 'classLabel' => 'Class 11A', 'room' => 'Science Lab A', 'isProxy' => false],
                    ['period' => 'P7', 'time' => '1:45–2:30', 'type' => 'empty'],
                ],
                'tomorrowSchedule' => [
                    ['period' => 'P1', 'subject' => 'Physics', 'classLabel' => 'Class 10B'],
                    ['period' => 'P3', 'subject' => 'Physics', 'classLabel' => 'Class 9A'],
                    ['period' => 'P5', 'subject' => 'Physics', 'classLabel' => 'Class 8C'],
                    ['period' => 'P7', 'subject' => 'Physics', 'classLabel' => 'Class 11B'],
                ]
            ]);
        }

        // Return the existing admin dashboard for everyone else
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
                ['id' => 1, 'text' => 'Proxy engine ran — 11 of 13 periods assigned', 'time' => '8:42', 'color' => 'teal'],
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

    Route::get('/exam-schedule', function () {
        return Inertia::render('ExamSchedule/Index', [
            'session' => [
                'title' => 'Exam Schedule — June 2026',
                'subtitle' => 'Mid-term exams',
                'dateLabel' => 'Monday, June 23',
            ],
            'halls' => [
                ['name' => 'Hall A', 'capacity' => 40],
                ['name' => 'Hall B', 'capacity' => 35],
                ['name' => 'Hall C', 'capacity' => 30],
            ],
            'timeSlots' => [
                ['key' => 'slot1', 'label' => '9:00–11:00', 'startLabel' => '9:00'],
                ['key' => 'slot2', 'label' => '11:30–1:30', 'startLabel' => '11:30'],
                ['key' => 'slot3', 'label' => '2:00–4:00', 'startLabel' => '2:00'],
            ],
            'subjectOptions' => [
                'Mathematics', 'Higher Mathematics', 'English', 'Physics', 'Chemistry',
                'Biology', 'History', 'Bangla', 'Science',
            ],
            'classOptions' => [
                'Class 6A', 'Class 7A', 'Class 7B', 'Class 8A', 'Class 8B',
                'Class 9A', 'Class 9B', 'Class 10A', 'Class 10B', 'Class 11A', 'Class 11B',
            ],
            'invigilatorOptions' => [
                'Mr. Chowdhury', 'Ms. Begum', 'Mr. Ali', 'Ms. Khatun', 'Ms. Islam',
                'Mr. Rahman', 'Ms. Karim', 'Mr. Ahmed', 'Mr. Hossain',
            ],

            'examGrid' => [
                'Hall A' => [
                    'slot1' => ['subject' => 'Mathematics', 'classLabel' => 'Class 9A', 'invigilator' => 'Mr. Chowdhury'],
                    'slot2' => ['subject' => 'English', 'classLabel' => 'Class 10A', 'invigilator' => 'Mr. Chowdhury'],
                    'slot3' => null,
                ],
                'Hall B' => [
                    'slot1' => ['subject' => 'Physics', 'classLabel' => 'Class 10B', 'invigilator' => 'Mr. Ali'],
                    'slot2' => ['subject' => 'Bangla', 'classLabel' => 'Class 7B', 'invigilator' => 'Mr. Chowdhury'],
                    'slot3' => ['subject' => 'History', 'classLabel' => 'Class 8A', 'invigilator' => 'Ms. Khatun'],
                ],
                'Hall C' => [
                    'slot1' => ['subject' => 'Science', 'classLabel' => 'Class 9B', 'invigilator' => 'Ms. Begum'],
                    'slot2' => null,
                    'slot3' => ['subject' => 'Higher Mathematics', 'classLabel' => 'Class 11A', 'invigilator' => 'Ms. Islam'],
                ],
            ],
        ]);
    })->name('exam-schedule.index');

    Route::get('/leave-requests', function () {
        $year = 2026;
        $routine = Routine::where('status', 'Active')->latest()->first() ?? Routine::latest()->first();
        $mockRequests = [
            ['id' => 1, 'teacherName' => 'Mr. Ahmed', 'initials' => 'NA', 'subject' => 'Bangla', 'type' => 'Paid leave', 'dateRange' => '24/07/26 - 24/07/26', 'days' => 1, 'duration' => 'Full day', 'status' => 'pending', 'reason' => 'Medical appointment confirmed for the morning, with rest advised for the day.', 'submittedAt' => 'Today, 8:15 AM', 'proxyRelevant' => false, 'periods' => []],
            ['id' => 2, 'teacherName' => 'Ms. Begum', 'initials' => 'PB', 'subject' => 'Biology', 'type' => 'Casual leave', 'dateRange' => '24/07/26 - 24/07/26', 'days' => 1, 'duration' => 'Selected periods', 'status' => 'pending', 'reason' => 'Family obligation during the middle of the school day.', 'submittedAt' => 'Today, 8:40 AM', 'proxyRelevant' => false, 'periods' => ['P3', 'P4']],
            ['id' => 3, 'teacherName' => 'Ms. Karim', 'initials' => 'SK', 'subject' => 'English', 'type' => 'Paid leave', 'dateRange' => '23/07/26 - 23/07/26', 'days' => 1, 'duration' => 'Full day', 'status' => 'approved', 'reason' => 'Approved medical leave.', 'submittedAt' => 'Yesterday, 2:20 PM', 'proxyRelevant' => true, 'periods' => []],
            ['id' => 4, 'teacherName' => 'Ms. Islam', 'initials' => 'FI', 'subject' => 'History', 'type' => 'Discretionary leave', 'dateRange' => '23/07/26 - 23/07/26', 'days' => 1, 'duration' => 'Morning only', 'status' => 'approved', 'reason' => 'Administrative approval for an exceptional family matter.', 'submittedAt' => 'Yesterday, 1:10 PM', 'proxyRelevant' => true, 'periods' => ['P1', 'P2', 'P3']],
            ['id' => 5, 'teacherName' => 'Shakif Niaz', 'initials' => 'SN', 'subject' => 'Mathematics', 'type' => 'Paid leave', 'dateRange' => '28/07/26 - 29/07/26', 'days' => 2, 'duration' => 'Full day', 'status' => 'pending', 'reason' => 'Doctor advised rest and follow-up appointment.', 'submittedAt' => 'Today, 9:05 AM', 'proxyRelevant' => false, 'periods' => []],
            ['id' => 6, 'teacherName' => 'Shakif Niaz', 'initials' => 'SN', 'subject' => 'Mathematics', 'type' => 'Casual leave', 'dateRange' => '10/05/26 - 10/05/26', 'days' => 1, 'duration' => 'Full day', 'status' => 'approved', 'reason' => 'Attending a family wedding out of town.', 'submittedAt' => 'May 4, 11:00 AM', 'proxyRelevant' => false, 'periods' => []],
            ['id' => 7, 'teacherName' => 'Shakif Niaz', 'initials' => 'SN', 'subject' => 'Mathematics', 'type' => 'Unpaid leave', 'dateRange' => '02/04/26 - 05/04/26', 'days' => 4, 'duration' => 'Full day', 'status' => 'rejected', 'reason' => 'Requested during midterm evaluations.', 'submittedAt' => 'Mar 25, 10:30 AM', 'proxyRelevant' => false, 'periods' => []],
        ];
        $requestsByTeacher = collect($mockRequests)->groupBy('teacherName');
        $allowances = collect($routine?->teachers ?? [])->map(function (array $teacher, int $index) use ($routine, $year, $requestsByTeacher) {
            $teacherId = (string) ($teacher['id'] ?? $index + 1);
            $teacherName = trim((string) ($teacher['name'] ?? 'Teacher '.($index + 1)));
            $subjects = array_values($teacher['primarySubjects'] ?? $teacher['subjects'] ?? []);
            $record = TeacherLeaveAllowance::firstOrCreate(
                ['routine_id' => $routine->id, 'teacher_id' => $teacherId, 'year' => $year],
                ['teacher_name' => $teacherName, 'max_leaves' => 12]
            );
            $teacherRequests = $requestsByTeacher->get($teacherName, collect());

            return [
                'id' => $teacherId,
                'routineId' => $routine->id,
                'teacher' => $teacherName,
                'subject' => $teacher['subjectHint'] ?? implode(', ', array_slice($subjects, 0, 3)),
                'maxLeaves' => $record->max_leaves,
                'used' => $teacherRequests->where('status', 'approved')->sum('days'),
                'pending' => $teacherRequests->where('status', 'pending')->count(),
                'paid' => $teacherRequests->where('status', 'approved')->where('type', 'Paid leave')->sum('days'),
                'casual' => $teacherRequests->where('status', 'approved')->where('type', 'Casual leave')->sum('days'),
                'unpaid' => $teacherRequests->where('status', 'approved')->where('type', 'Unpaid leave')->sum('days'),
                'discretionary' => $teacherRequests->where('status', 'approved')->where('type', 'Discretionary leave')->sum('days'),
            ];
        })->values();

        return Inertia::render('LeaveRequests/Index', [
            'requests' => $mockRequests,
            'leaveBalances' => $allowances,
            'typeOptions' => ['Paid leave', 'Casual leave', 'Unpaid leave', 'Discretionary leave'],
            'year' => $year,
            'activeRoutineName' => $routine?->name,
            'routinePeriods' => $routine?->periods ?? [],
        ]);
    })->name('leave-requests.index');

    Route::put('/leave-requests/allowances', function (\Illuminate\Http\Request $request) {
        $data = $request->validate([
            'routineId' => ['required', 'integer', 'exists:routines,id'],
            'teacherId' => ['required', 'string', 'max:80'],
            'teacherName' => ['required', 'string', 'max:120'],
            'year' => ['required', 'integer', 'min:2020', 'max:2100'],
            'maxLeaves' => ['required', 'integer', 'min:0', 'max:365'],
        ]);

        TeacherLeaveAllowance::updateOrCreate(
            ['routine_id' => $data['routineId'], 'teacher_id' => $data['teacherId'], 'year' => $data['year']],
            ['teacher_name' => $data['teacherName'], 'max_leaves' => $data['maxLeaves']]
        );

        return back()->with('success', 'Leave allowance updated.');
    })->name('leave-requests.allowances.update');

    Route::get('/noticeboard', function () {
        return Inertia::render('Noticeboard/Index', [
            'notices' => [
                [
                    'id' => 1, 'title' => 'Staff meeting — mandatory attendance',
                    'message' => 'All teaching staff must attend the departmental meeting this Friday at 2:00 PM in the conference hall. Attendance will be marked.',
                    'urgency' => 'Urgent', 'postedBy' => 'Admin', 'postedDate' => 'Jun 12', 'readCount' => 12, 'totalStaff' => 18,
                ],
                [
                    'id' => 2, 'title' => 'Mid-term exam schedule published',
                    'message' => 'The mid-term exam schedule for June 2026 has been finalized. Please review your invigilator duties in the Exam Schedule section.',
                    'urgency' => 'Important', 'postedBy' => 'Admin', 'postedDate' => 'Jun 11', 'readCount' => 18, 'totalStaff' => 18,
                ],
                [
                    'id' => 3, 'title' => 'Updated academic calendar 2026',
                    'message' => 'The updated academic calendar with revised holiday dates has been uploaded to the resource library.',
                    'urgency' => 'Normal', 'postedBy' => 'Admin', 'postedDate' => 'Jun 9', 'readCount' => 18, 'totalStaff' => 18,
                ],
            ],
            'urgencyOptions' => ['Normal', 'Important', 'Urgent'],
            'audienceOptions' => ['All staff', 'Mathematics Department', 'English Department', 'Science Department', 'Individual teacher'],
            'totalStaff' => 18,
        ]);
    })->name('noticeboard.index');

    Route::get('/staff-room', function () {
        return Inertia::render('StaffRoom/Index', [
            'boards' => [
                [
                    'id' => 'handovers',
                    'name' => 'Class Handovers',
                    'description' => 'Context notes for the next teacher stepping into a classroom today.',
                    'notes' => [
                        [
                            'id' => 1,
                            'author' => 'Mrs. Ananya (English)',
                            'target' => 'Class 10-A',
                            'time' => '10 mins ago',
                            'content' => 'Started the presentation late. They need exactly 5 minutes at the start of the next period to shut down their laptop slides.',
                            'tag' => 'Urgent Context',
                            'color' => 'rose'
                        ],
                        [
                            'id' => 2,
                            'author' => 'Mr. Zayan (Chemistry)',
                            'target' => 'Class 9-B',
                            'time' => '2 hours ago',
                            'content' => 'Distributed standard carbon compounds sheets. Remind them they must write in blue ink for their in-person submission.',
                            'tag' => 'Task Reminder',
                            'color' => 'violet'
                        ]
                    ]
                ],
                [
                    'id' => 'resources',
                    'name' => 'Shared Equipment & Labs',
                    'description' => 'Coordination notes regarding science apparatus, keys, and facility usage settings.',
                    'notes' => [
                        [
                            'id' => 3,
                            'author' => 'Shakif Niaz (Physics)',
                            'target' => 'Science Lab B',
                            'time' => '1 hour ago',
                            'content' => 'Left the circuit calibration meters wired on row 3 for the 6th period labs. Kindly let them sit undisturbed.',
                            'tag' => 'Lab Layout',
                            'color' => 'amber'
                        ]
                    ]
                ],
                [
                    'id' => 'favors',
                    'name' => 'Internal Swap Requests',
                    'description' => 'Peer-level schedule trade discussions before logging formal proxy absences.',
                    'notes' => [
                        [
                            'id' => 4,
                            'author' => 'Ms. Khan',
                            'target' => 'Friday Period 5 Swap',
                            'time' => '3 hours ago',
                            'content' => 'Looking to trade my Friday afternoon 5th block for any morning session due to an external conference alignment.',
                            'tag' => 'Swap Request',
                            'color' => 'sky'
                        ]
                    ]
                ]
            ]
        ]);
    })->name('staff-room.index');

    Route::get('/classrooms', function () {
        return Inertia::render('Classrooms/Index', [
            'classrooms' => [
                [
                    'id' => 1,
                    'name' => 'Class 10-A',
                    'room' => 'Science Block — Room 402',
                    'advisor' => 'Shakif Niaz',
                    'proxyUpdates' => [
                        [
                            'id' => 201,
                            'period' => '3rd Period (11:00 AM)',
                            'originalTeacher' => 'Shakif Niaz (Physics)',
                            'proxyTeacher' => 'Mr. Ahmed',
                            'note' => 'Please bring your standard physics workbooks. Session will proceed inside Room 402.'
                        ]
                    ],
                    'subjects' => [
                        [
                            'id' => 11,
                            'name' => 'Physics',
                            'teacher' => 'Shakif Niaz',
                            'syllabus' => 'Chapter 5: Electromagnetism & Field Induction Theories.',
                            'homework' => 'Solve textbook back-exercises 5.1 to 5.12 inside your workspace logbooks.',
                            'assignment' => [
                                'title' => 'Induction Lab Writeup',
                                'deadline' => 'Thursday, June 25',
                                'instruction' => 'Complete calibration calculations on metric graph paper.'
                            ]
                        ],
                        [
                            'id' => 12,
                            'name' => 'Mathematics',
                            'teacher' => 'Mr. Rahman',
                            'syllabus' => 'Chapter 9: Differential Geometries and Limits.',
                            'homework' => 'Attempt Section 9C problems 1 through 7 before Sunday morning.',
                            'assignment' => null
                        ]
                    ],
                    'announcements' => [
                        [
                            'id' => 301,
                            'subject' => 'Mathematics',
                            'type' => 'Test Announcement',
                            'date' => 'June 22',
                            'author' => 'Mr. Rahman',
                            'content' => 'Class evaluation test on matrix algebraic equations will take place this Thursday. Bring your scientific calculators.'
                        ],
                        [
                            'id' => 302,
                            'subject' => 'Physics',
                            'type' => 'Assignment Announcement',
                            'date' => 'June 20',
                            'author' => 'Shakif Niaz',
                            'content' => 'Submissions for the electromagnetic induction project close this week. Submit via the lab inbox.'
                        ],
                        [
                            'id' => 303,
                            'subject' => 'General Classroom Sync',
                            'type' => 'General',
                            'date' => 'June 19',
                            'author' => 'Admin Desk',
                            'content' => 'Reminder: The cleaning rotation slots for the science cabinet have been updated on the front notice board.'
                        ]
                    ]
                ],
                [
                    'id' => 2,
                    'name' => 'Class 9-B',
                    'room' => 'Main Academic Block — Room 204',
                    'advisor' => 'Mr. Zayan',
                    'proxyUpdates' => [], // Clear schedule status simulation
                    'subjects' => [
                        [
                            'id' => 13,
                            'name' => 'Chemistry',
                            'teacher' => 'Mr. Zayan',
                            'syllabus' => 'Chapter 3: Carbon Compounds & Polymer Bonding Structures.',
                            'homework' => 'Draw structural formulas for the first 5 alkanes into notes.',
                            'assignment' => null
                        ],
                        [
                            'id' => 14,
                            'name' => 'English Lit',
                            'teacher' => 'Mrs. Ananya',
                            'syllabus' => 'Romantic Era poetry analyses.',
                            'homework' => 'Read structural verses 4 to 8 of the assigned reading packet.',
                            'assignment' => [
                                'title' => 'Poetry Critical Review',
                                'deadline' => 'Monday, June 29',
                                'instruction' => 'Write a short 500-word critical evaluation essay.'
                            ]
                        ]
                    ],
                    'announcements' => [
                        [
                            'id' => 304,
                            'subject' => 'Chemistry',
                            'type' => 'Test Announcement',
                            'date' => 'June 21',
                            'author' => 'Mr. Zayan',
                            'content' => 'Pop quiz on functional compound groups coming up sometime this week. Keep your organic study summary materials ready.'
                        ]
                    ]
                ]
            ]
        ]);
    })->name('classrooms.index');

    Route::get('/analytics', function () {
        return Inertia::render('Analytics/Index', [
            'stats' => [
                'totalAbsences' => ['value' => 47, 'delta' => '+12% vs last month'],
                'proxyClasses' => ['value' => 183, 'delta' => '35 this week'],
                'unresolved' => ['value' => 7, 'delta' => 'avg 0.4/day'],
                'ackRate' => ['value' => 91, 'delta' => '+5% improvement'],
            ],
            'rangeOptions' => ['This week', 'This month', 'This term', 'Custom range'],
            'chartLabel' => 'Daily absences — June 2026',
            'dailyAbsences' => [
                ['day' => 'Mon', 'count' => 4],
                ['day' => 'Tue', 'count' => 7],
                ['day' => 'Wed', 'count' => 3],
                ['day' => 'Thu', 'count' => 6],
                ['day' => 'Fri', 'count' => 9],
                ['day' => 'Mon', 'count' => 5],
                ['day' => 'Tue', 'count' => 8],
            ],
            'proxyLoad' => [
                ['teacher' => 'Ms. Islam', 'count' => 14],
                ['teacher' => 'Mr. Rahman', 'count' => 11],
                ['teacher' => 'Mr. Hossain', 'count' => 8],
                ['teacher' => 'Ms. Karim', 'count' => 5],
            ],
            'heatmapDays' => ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat'],
            'heatmap' => [
                ['teacher' => 'Mr. Rahman', 'values' => [0, 1, 2, 0, 2, 1]],
                ['teacher' => 'Ms. Karim', 'values' => [1, 2, 0, 1, 2, 2]],
                ['teacher' => 'Mr. Ahmed', 'values' => [3, 3, 2, 0, 3, 3]],
            ],
        ]);
    })->name('analytics.index');
    
    Route::get('/teachers', function () {
        return Inertia::render('Teachers/Index', [
            'teachers' => [
                ['id' => 1, 'name' => 'Mr. Rahman', 'phone' => '+8801711000001', 'initials' => 'MR', 'avatarColor' => 'emerald', 'subject' => 'Mathematics', 'proxyLoadThisMonth' => 5, 'leaveUsedDays' => 5, 'status' => 'Active', 'role' => 'Teacher'],
                ['id' => 2, 'name' => 'Ms. Karim', 'phone' => '+8801711000002', 'initials' => 'SK', 'avatarColor' => 'amber', 'subject' => 'English', 'proxyLoadThisMonth' => 3, 'leaveUsedDays' => 2, 'status' => 'Active', 'role' => 'Admin'],
                ['id' => 3, 'name' => 'Mr. Ahmed', 'phone' => '+8801711000003', 'initials' => 'NA', 'avatarColor' => 'rose', 'subject' => 'Bangla', 'proxyLoadThisMonth' => 1, 'leaveUsedDays' => 8, 'status' => 'On leave', 'role' => 'Teacher'],
                ['id' => 4, 'name' => 'Mr. Hossain', 'phone' => '+8801711000004', 'initials' => 'AH', 'avatarColor' => 'sky', 'subject' => 'Physics', 'proxyLoadThisMonth' => 4, 'leaveUsedDays' => 3, 'status' => 'Active', 'role' => 'Teacher'],
                ['id' => 5, 'name' => 'Ms. Islam', 'phone' => '+8801711000005', 'initials' => 'FI', 'avatarColor' => 'violet', 'subject' => 'History', 'proxyLoadThisMonth' => 2, 'leaveUsedDays' => 1, 'status' => 'Active', 'role' => 'Teacher'],
            ],
            'subjectOptions' => [
                'Mathematics', 'Higher Mathematics', 'English', 'Physics', 'Chemistry',
                'Biology', 'History', 'Bangla', 'Science',
            ],
        ]);
    })->name('teachers.index');
    Route::get('/settings', function () {
        return Inertia::render('Settings/Index', [
            'general' => [
                'schoolName' => 'Metropolitan School',
                'contactPhone' => '+8801711000000',
                'contactEmail' => 'admin@metroschool.edu.bd',
                'termLabel' => 'Term 1 — 2025/26',
                'weekStartDay' => 'Sunday',
                'academicYear' => '2025/26',
            ],
            'periods' => [
                ['id' => 1, 'label' => 'P1', 'startTime' => '08:00', 'endTime' => '08:45', 'locked' => false],
                ['id' => 2, 'label' => 'P2', 'startTime' => '08:45', 'endTime' => '09:30', 'locked' => false],
                ['id' => 3, 'label' => 'BREAK', 'startTime' => '09:30', 'endTime' => '09:45', 'locked' => true],
                ['id' => 4, 'label' => 'P3', 'startTime' => '09:45', 'endTime' => '10:30', 'locked' => false],
                ['id' => 5, 'label' => 'P4', 'startTime' => '10:30', 'endTime' => '11:15', 'locked' => false],
                ['id' => 6, 'label' => 'P5', 'startTime' => '11:15', 'endTime' => '12:00', 'locked' => false],
                ['id' => 7, 'label' => 'LUNCH', 'startTime' => '12:00', 'endTime' => '13:00', 'locked' => true],
                ['id' => 8, 'label' => 'P6', 'startTime' => '13:00', 'endTime' => '13:45', 'locked' => false],
                ['id' => 9, 'label' => 'P7', 'startTime' => '13:45', 'endTime' => '14:30', 'locked' => false],
            ],
            'notifications' => [
                ['key' => 'whatsapp', 'label' => 'WhatsApp Notifications', 'description' => 'Send automatic WhatsApp messages for proxy assignments and leave updates.', 'enabled' => true],
                ['key' => 'email_digest', 'label' => 'Daily Email Digest', 'description' => 'Send admins a daily summary of absences, proxies, and pending approvals.', 'enabled' => false],
                ['key' => 'urgent_confirm', 'label' => 'Urgent Broadcast Confirmation', 'description' => 'Require a confirmation step before broadcasting an urgent notice.', 'enabled' => true],
                ['key' => 'leave_alerts', 'label' => 'Leave Approval Alerts', 'description' => 'Notify teachers immediately when their leave request is approved or rejected.', 'enabled' => true],
                ['key' => 'unresolved_alerts', 'label' => 'Unresolved Period Alerts', 'description' => "Alert admins when a proxy period can't be auto-resolved.", 'enabled' => true],
            ],
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
