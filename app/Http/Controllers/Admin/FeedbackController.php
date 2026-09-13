<?php

namespace App\Http\Controllers\Admin;

use App\Enums\FeedbackStatus;
use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class FeedbackController extends Controller
{
    public function index(Request $request): Response
    {
        $status = in_array($request->query('status'), array_column(FeedbackStatus::cases(), 'value'), true)
            ? $request->query('status')
            : null;

        $items = Feedback::query()
            ->with(['user:id,name,email', 'group:id,name', 'handler:id,name'])
            ->when($status, fn ($q, $s) => $q->where('status', $s))
            ->orderByRaw("FIELD(status, 'new', 'read', 'resolved')")
            ->latest()
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Feedback $f) => [
                'id' => $f->id,
                'user_name' => $f->user?->name,
                'user_email' => $f->user?->email,
                'group_name' => $f->group?->name,
                'category' => $f->category->value,
                'category_label' => $f->category->label(),
                'message' => $f->message,
                'status' => $f->status->value,
                'status_label' => $f->status->label(),
                'admin_note' => $f->admin_note,
                'handler_name' => $f->handler?->name,
                'created_label' => $f->created_at?->translatedFormat('d M Y H:i'),
            ]);

        return Inertia::render('Admin/Feedback/Index', [
            'feedback' => $items,
            'filters' => ['status' => $status],
            'counts' => Feedback::query()->selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'statuses' => collect(FeedbackStatus::cases())->map(fn ($s) => ['value' => $s->value, 'label' => $s->label()])->values(),
        ]);
    }

    public function update(Request $request, Feedback $feedback): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(FeedbackStatus::class)],
            'admin_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $handled = $data['status'] !== FeedbackStatus::New->value;

        $feedback->update([
            'status' => $data['status'],
            'admin_note' => $data['admin_note'] ?? null,
            'handled_by' => $handled ? $request->user()->getKey() : null,
            'handled_at' => $handled ? ($feedback->handled_at ?? now()) : null,
        ]);

        return back()->with('success', 'Status saran diperbarui.');
    }
}
