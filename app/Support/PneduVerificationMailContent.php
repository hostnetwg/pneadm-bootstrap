<?php

namespace App\Support;

use App\Models\PneduUser;

class PneduVerificationMailContent
{
    /**
     * Treść wiadomości wysyłanej ręcznie z panelu adm (ponowny link po czasie od rejestracji).
     * Odróżnia się od automatycznej wiadomości po rejestracji na pnedu.pl ({@see SystemVerifyEmail}).
     *
     * @return array{
     *     to: string,
     *     from_address: string,
     *     from_name: string,
     *     reply_to_address: string,
     *     reply_to_name: string,
     *     subject: string,
     *     intro: string,
     *     context: string,
     *     action_prompt: string,
     *     action_label: string,
     *     action_url: string,
     *     outro: string
     * }
     */
    public static function buildForAdminResend(PneduUser $user, string $verificationUrl): array
    {
        $firstName = trim((string) ($user->first_name ?? ''));
        $greeting = $firstName !== '' ? "Dzień dobry, {$firstName}," : 'Dzień dobry,';

        $registeredAt = $user->created_at?->timezone(config('app.timezone'))->format('d.m.Y');
        $email = $user->email;

        if ($registeredAt !== null) {
            $context = "Zarejestrowałeś/aś konto na pnedu.pl ({$registeredAt}). Do tej pory nie potwierdziliśmy adresu e-mail {$email} — bez weryfikacji nie masz pełnego dostępu do panelu użytkownika (zapisy na szkolenia, certyfikaty itd.).";
        } else {
            $context = "Twoje konto na pnedu.pl ({$email}) nie ma jeszcze potwierdzonego adresu e-mail — bez weryfikacji nie masz pełnego dostępu do panelu użytkownika.";
        }

        return [
            'to' => $email,
            'from_address' => (string) config('mail.system.from_address'),
            'from_name' => (string) config('mail.system.from_name'),
            'reply_to_address' => (string) config('mail.system.reply_to_address'),
            'reply_to_name' => (string) config('mail.system.reply_to_name'),
            'subject' => 'Przypomnienie: potwierdź adres e-mail — Platforma Nowoczesnej Edukacji',
            'intro' => $greeting,
            'context' => $context,
            'action_prompt' => 'Aby dokończyć aktywację konta, kliknij poniższy przycisk:',
            'action_label' => 'Potwierdź adres e-mail',
            'action_url' => $verificationUrl,
            'outro' => 'Pierwsza wiadomość weryfikacyjna mogła trafić do folderu Spam, Oferty lub Powiadomienia — sprawdź te miejsca w skrzynce. Jeśli nie zakładałeś/aś konta na pnedu.pl, zignoruj tę wiadomość.',
        ];
    }
}
