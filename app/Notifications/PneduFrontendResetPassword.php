<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Lang;

/**
 * Link w mailu prowadzi na front pnedu.pl (reset-password), nie na adm.
 */
class PneduFrontendResetPassword extends ResetPassword
{
    protected function resetUrl($notifiable): string
    {
        $base = rtrim((string) config('services.pnedu_frontend_url'), '/');

        return $base.'/reset-password/'.$this->token.'?email='.urlencode($notifiable->getEmailForPasswordReset());
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(Lang::get('Reset Password Notification'))
            ->line(Lang::get('You are receiving this email because we received a password reset request for your account.'))
            ->action(Lang::get('Reset Password'), $this->resetUrl($notifiable))
            ->line('Link do ustawienia nowego hasła możesz wykorzystać w dowolnym momencie.')
            ->line(Lang::get('If you did not request a password reset, no further action is required.'));
    }
}
