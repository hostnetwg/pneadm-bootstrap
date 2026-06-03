<?php

namespace App\Support;

use App\Models\PneduUser;

class PneduVerificationMailContent
{
    /**
     * @return array{
     *     to: string,
     *     from_address: string,
     *     from_name: string,
     *     reply_to_address: string,
     *     reply_to_name: string,
     *     subject: string,
     *     intro: string,
     *     action_label: string,
     *     action_url: string,
     *     outro: string
     * }
     */
    public static function build(PneduUser $user, string $verificationUrl): array
    {
        return [
            'to' => $user->email,
            'from_address' => (string) config('mail.system.from_address'),
            'from_name' => (string) config('mail.system.from_name'),
            'reply_to_address' => (string) config('mail.system.reply_to_address'),
            'reply_to_name' => (string) config('mail.system.reply_to_name'),
            'subject' => 'Zweryfikuj adres e-mail — Platforma Nowoczesnej Edukacji',
            'intro' => 'Kliknij poniższy przycisk, aby potwierdzić swój adres e-mail i aktywować konto na pnedu.pl.',
            'action_label' => 'Zweryfikuj adres e-mail',
            'action_url' => $verificationUrl,
            'outro' => 'Jeśli nie zakładałeś/aś konta, zignoruj tę wiadomość.',
        ];
    }
}
