<?php

namespace App\Notifications\Concerns;

use Illuminate\Notifications\Messages\MailMessage;

trait UsesSystemMailSettings
{
    protected function configureSystemMail(MailMessage $message): MailMessage
    {
        return $message
            ->mailer(config('mail.system.mailer'))
            ->from(
                config('mail.system.from_address'),
                config('mail.system.from_name')
            )
            ->replyTo(
                config('mail.system.reply_to_address'),
                config('mail.system.reply_to_name')
            );
    }
}
