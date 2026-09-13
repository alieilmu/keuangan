<?php

namespace App\Http\Controllers;

use App\Enums\FeedbackCategory;
use App\Enums\FeedbackStatus;
use App\Models\Feedback;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Pengguna mengirim saran/laporan ke admin dari menu profil. */
class FeedbackController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'category' => ['required', Rule::enum(FeedbackCategory::class)],
            'message' => ['required', 'string', 'min:10', 'max:2000'],
        ], [
            'message.min' => 'Tulis minimal 10 karakter supaya admin memahami maksud Anda.',
        ]);

        $user = $request->user();

        Feedback::query()->create([
            'user_id' => $user->getKey(),
            'group_id' => $user->group_id,
            'category' => $data['category'],
            'message' => trim($data['message']),
            'status' => FeedbackStatus::New->value,
        ]);

        return back()->with('success', 'Terima kasih! Saran Anda sudah terkirim ke admin.');
    }
}
