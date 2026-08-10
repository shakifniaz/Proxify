<?php

namespace App\Http\Controllers;

use App\Models\Notice;
use App\Models\TeacherProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class NoticeController extends Controller
{
    public function index(Request $request): Response
    {
        $institutionId = $request->user()?->institution_id;
        $role = strtolower($request->user()?->role ?? 'admin');
        $notices = Notice::query()
            ->with('user:id,name')
            ->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))
            ->latest()
            ->get();

        return Inertia::render('Noticeboard/Index', [
            'institutionalNotices' => $notices
                ->where('board', 'institutional')
                ->filter(fn (Notice $notice) => $role === 'admin' || $notice->visibility === 'All' || ($notice->visibility === 'Teachers' && $role === 'teacher'))
                ->map(fn (Notice $notice) => $this->payload($notice, $request))
                ->values(),
            'staffNotices' => $role === 'student'
                ? []
                : $notices->where('board', 'staff')->map(fn (Notice $notice) => $this->payload($notice, $request))->values(),
            'urgencyOptions' => ['Low', 'Normal', 'Important', 'Urgent'],
            'visibilityOptions' => ['All', 'Teachers', 'Admins'],
            'totalStaff' => TeacherProfile::query()->when($institutionId, fn ($query) => $query->where('institution_id', $institutionId))->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'board' => ['required', 'in:institutional,staff'],
            'title' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', 'in:Low,Normal,Important,Urgent'],
            'visibility' => ['nullable', 'in:All,Teachers,Admins'],
        ]);
        $role = strtolower($request->user()?->role ?? 'admin');
        abort_unless(
            ($data['board'] === 'institutional' && $role === 'admin')
            || ($data['board'] === 'staff' && in_array($role, ['admin', 'teacher'], true)),
            403
        );

        Notice::create([
            'institution_id' => $request->user()?->institution_id,
            'user_id' => $request->user()?->id,
            'board' => $data['board'],
            'title' => $data['title'],
            'message' => $data['message'],
            'urgency' => $data['urgency'],
            'visibility' => $data['board'] === 'institutional' ? ($data['visibility'] ?? 'Teachers') : null,
            'acknowledged_by' => [],
        ]);

        return back()->with('success', 'Notice posted.');
    }

    public function update(Request $request, Notice $notice): RedirectResponse
    {
        abort_unless($this->canManage($request, $notice), 403);

        $data = $request->validate([
            'title' => ['required', 'string', 'max:180'],
            'message' => ['required', 'string', 'max:5000'],
            'urgency' => ['required', 'in:Low,Normal,Important,Urgent'],
            'visibility' => ['nullable', 'in:All,Teachers,Admins'],
        ]);

        $notice->update([
            'title' => $data['title'],
            'message' => $data['message'],
            'urgency' => $data['urgency'],
            'visibility' => $notice->board === 'institutional' ? ($data['visibility'] ?? 'Teachers') : null,
        ]);

        return back()->with('success', 'Notice updated.');
    }

    public function destroy(Request $request, Notice $notice): RedirectResponse
    {
        abort_unless($this->canManage($request, $notice), 403);
        $notice->delete();

        return back()->with('success', 'Notice deleted.');
    }

    public function acknowledge(Request $request, Notice $notice): RedirectResponse
    {
        abort_unless($notice->board === 'staff', 403);

        $name = $request->user()?->name ?? 'User';
        $acknowledged = collect($notice->acknowledged_by ?? []);
        $notice->update([
            'acknowledged_by' => $acknowledged->contains($name)
                ? $acknowledged->reject(fn ($item) => $item === $name)->values()->all()
                : $acknowledged->push($name)->unique()->values()->all(),
        ]);

        return back();
    }

    private function payload(Notice $notice, Request $request): array
    {
        return [
            'id' => $notice->id,
            'title' => $notice->title,
            'message' => $notice->message,
            'urgency' => $notice->urgency,
            'visibility' => $notice->visibility,
            'postedBy' => $notice->user?->name ?? 'Admin',
            'postedDate' => $notice->created_at?->format('M j') ?? '',
            'readCount' => $notice->read_count,
            'acknowledgedBy' => $notice->acknowledged_by ?? [],
            'owner' => (int) $notice->user_id === (int) ($request->user()?->id ?? 0),
        ];
    }

    private function canManage(Request $request, Notice $notice): bool
    {
        $role = strtolower($request->user()?->role ?? 'admin');
        return $role === 'admin' || ($notice->board === 'staff' && (int) $notice->user_id === (int) $request->user()?->id);
    }
}
