<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesSystemMailSettings;
use App\Support\PneduVerificationUrl;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PneduFrontendVerifyEmail extends Notification
{
    use UsesSystemMailSettings;

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = PneduVerificationUrl::forUser($notifiable);

        return $this->configureSystemMail(new MailMessage)
            ->subject('Zweryfikuj adres e-mail — Platforma Nowoczesnej Edukacji')
            ->line('Kliknij poniższy przycisk, aby potwierdzić swój adres e-mail i aktywować konto na pnedu.pl.')
            ->action('Zweryfikuj adres e-mail', $verificationUrl)
            ->line('Jeśli nie zakładałeś/aś konta, zignoruj tę wiadomość.');
    }
}
