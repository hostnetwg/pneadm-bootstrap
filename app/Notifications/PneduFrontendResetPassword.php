<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesSystemMailSettings;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

/**
 * Link w mailu prowadzi na front pnedu.pl (reset-password), nie na adm.
 */
class PneduFrontendResetPassword extends ResetPassword
{
    use UsesSystemMailSettings;

    protected function resetUrl($notifiable): string
    {
        $base = rtrim((string) config('services.pnedu_frontend_url'), '/');

        return $base.'/reset-password/'.$this->token.'?email='.urlencode($notifiable->getEmailForPasswordReset());
    }

    public function toMail($notifiable): MailMessage
    {
        return $this->configureSystemMail(
            parent::toMail($notifiable)
        );
    }
}
