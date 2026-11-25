<?php

namespace App\Http\Controllers;

use App\Models\TrainerPortalNotification;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class TrainerPortalNotificationController extends Controller
{
    public function index(): View
    {
        $notifications = TrainerPortalNotification::query()
            ->where('audience', TrainerPortalNotification::AUDIENCE_ADMIN)
            ->where('pneadm_user_id', auth()->id())
            ->latest()
            ->limit(50)
            ->get();

        return view('trainer-portal.notifications', [
            'notifications' => $notifications,
        ]);
    }

    public function open(TrainerPortalNotification $notification): RedirectResponse
    {
        abort_unless(
            $notification->audience === TrainerPortalNotification::AUDIENCE_ADMIN
            && (int) $notification->pneadm_user_id === (int) auth()->id(),
            404
        );

        if ($notification->read_at === null) {
            $notification->read_at = now();
            $notification->save();
        }

        if ($notification->closed_training_case_id) {
            return redirect()->route('closed-training-cases.show', $notification->closed_training_case_id);
        }

        return redirect()->route('trainer-portal.notifications');
    }
}
