<?php

namespace Tests\Unit\GrowthOS;

use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use App\Support\GrowthOS\GrowthMailUrls;
use PHPUnit\Framework\TestCase;

class GrowthMailUrlsTest extends TestCase
{
    public function test_with_registration_link_replaces_placeholder_for_https_urls(): void
    {
        $draft = 'Zapisz się: '.MaterialDraftTask::LINK_PLACEHOLDER;

        $this->assertSame(
            'Zapisz się: https://pnedu.pl/courses/577',
            GrowthMailUrls::withRegistrationLink($draft, 'https://pnedu.pl/courses/577'),
        );
        $this->assertSame($draft, GrowthMailUrls::withRegistrationLink($draft, null));
        $this->assertSame($draft, GrowthMailUrls::withRegistrationLink($draft, ''));
        $this->assertSame($draft, GrowthMailUrls::withRegistrationLink($draft, 'javascript:alert(1)'));
        $this->assertSame($draft, GrowthMailUrls::withRegistrationLink($draft, 'http://pnedu.pl/courses/577'));
    }
}
