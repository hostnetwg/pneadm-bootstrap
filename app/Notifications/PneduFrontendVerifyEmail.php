<?php

namespace App\Notifications;

use App\Notifications\Concerns\UsesSystemMailSettings;
use App\Support\PneduVerificationMailContent;
use App\Support\PneduVerificationUrl;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class PneduFrontendVerifyEmail extends Notification
{
    use UsesSystemMailSettings;

    public function __construct(
        private readonly ?string $verificationUrl = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $verificationUrl = $this->verificationUrl ?? PneduVerificationUrl::forUser($notifiable);
        $content = PneduVerificationMailContent::build($notifiable, $verificationUrl);

        return $this->configureSystemMail(new MailMessage)
            ->subject($content['subject'])
            ->line($content['intro'])
            ->action($content['action_label'], $content['action_url'])
            ->line($content['outro']);
    }
}
