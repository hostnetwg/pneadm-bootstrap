<?php

namespace App\Support;

use Illuminate\Mail\Events\MessageSent;

/**
 * Przechwytuje Message-ID ostatniej wysyłki w bieżącym żądaniu / jobie.
 */
final class OutboundMailCapture
{
    public static ?string $messageId = null;

    public static function reset(): void
    {
        self::$messageId = null;
    }

    public static function record(MessageSent $event): void
    {
        self::$messageId = $event->sent->getMessageId();
    }
}
