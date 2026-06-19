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

    Route::get('/routines', fn () => Inertia::render('Routines/Index'))->name('routines.index');
    Route::get('/routines/create', fn () => Inertia::render('Routines/Create'))->name('routines.create');
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