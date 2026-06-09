<?php

namespace App\Services\Mail;

use App\Support\OutboundMailCapture;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class SystemMailDiagnostics
{
    /**
     * Bieżąca konfiguracja kanału systemowego (do bannerów w panelu).
     *
     * @return array{
     *     mailer: string,
     *     transport: string,
     *     real_delivery: bool,
     *     warning: string|null,
     *     production_misconfigured: bool
     * }
     */
    public static function currentConfig(): array
    {
        $mailer = (string) config('mail.system.mailer', config('mail.default', 'log'));
        $transport = (string) config("mail.mailers.{$mailer}.transport", 'unknown');
        $realDelivery = self::transportDeliversExternally($transport);
        $productionMisconfigured = app()->environment('production') && ! $realDelivery;

        $warning = null;
        if (! $realDelivery) {
            $warning = 'Maile systemowe nie trafiają do Internetu (transport „'.$transport.'”). '
                .'Ustaw MAIL_MAILER=ses oraz MAIL_SYSTEM_MAILER=ses i uruchom php artisan config:clear.';
        }

        return [
            'mailer' => $mailer,
            'transport' => $transport,
            'real_delivery' => $realDelivery,
            'warning' => $warning,
            'production_misconfigured' => $productionMisconfigured,
        ];
    }

    /**
     * Wysyła Mailable przez skonfigurowany mailer systemowy i zwraca metadane diagnostyczne.
     *
     * @return array{
     *     mailer: string,
     *     transport: string,
     *     to: string,
     *     message_id: string|null,
     *     real_delivery: bool,
     *     mailable: class-string,
     *     diagnosed_at: string
     * }
     */
    public function send(string $to, Mailable $mailable): array
    {
        $mailer = (string) config('mail.system.mailer', config('mail.default', 'log'));
        $transport = (string) config("mail.mailers.{$mailer}.transport", 'unknown');
        $realDelivery = self::transportDeliversExternally($transport);

        OutboundMailCapture::reset();

        Mail::mailer($mailer)->to($to)->send($mailable);

        $meta = [
            'mailer' => $mailer,
            'transport' => $transport,
            'to' => $to,
            'message_id' => OutboundMailCapture::$messageId,
            'real_delivery' => $realDelivery,
            'mailable' => $mailable::class,
            'diagnosed_at' => now()->toIso8601String(),
        ];

        $logContext = $meta;

        if (! $realDelivery) {
            Log::warning('System mail accepted locally but NOT delivered to Internet', $logContext);
        } else {
            Log::info('System mail handed off to mail transport', $logContext);
        }

        return $meta;
    }

    public static function transportDeliversExternally(string $transport): bool
    {
        return ! in_array($transport, ['log', 'array'], true);
    }

    /**
     * @param  array<string, mixed>|null  $logMeta
     * @return array<string, mixed>|null
     */
    public static function deliveryMetaFromLog(?array $logMeta): ?array
    {
        if ($logMeta === null) {
            return null;
        }

        if (isset($logMeta['delivery']) && is_array($logMeta['delivery'])) {
            return $logMeta['delivery'];
        }

        if (isset($logMeta['transport'])) {
            return $logMeta;
        }

        return null;
    }
}
