<?php

namespace App\Mail\Concerns;

trait UsesSystemMailSettings
{
    protected function withSystemMailSettings(): static
    {
        return $this
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
