<?php

namespace Tests\Unit;

use App\Notifications\PneduFormOrderProvisionedExistingUser;
use Tests\TestCase;

class PneduProvisionArticle14NoticeTest extends TestCase
{
    public function test_first_access_email_names_data_source_and_links_full_notice(): void
    {
        config(['services.pnedu_frontend_url' => 'https://pnedu.pl']);

        $notification = new PneduFormOrderProvisionedExistingUser(
            'Szkolenie testowe',
            null,
            null,
            null,
            'Szkoła Podstawowa nr 1'
        );

        $notifiable = new class
        {
            public function getEmailForPasswordReset(): string
            {
                return 'participant@example.com';
            }
        };

        $mail = $notification->toMail($notifiable);
        $content = collect($mail->introLines)
            ->merge($mail->outroLines)
            ->map(fn ($line) => (string) $line)
            ->implode(' ');

        $this->assertStringContainsString('Szkoła Podstawowa nr 1', $content);
        $this->assertStringContainsString('https://pnedu.pl/rodo-art-14', $content);
    }
}
