<?php

namespace Tests\Unit\GrowthOS;

use App\Services\GrowthOS\AI\Support\ProhibitedData;
use PHPUnit\Framework\TestCase;

class ProhibitedDataTest extends TestCase
{
    public function test_replace_disallowed_urls_keeps_allow_listed_and_strips_others(): void
    {
        $text = "Zapisz się: https://pnedu.pl/courses/577 oraz zobacz https://evil.example/x i www.spam.pl/y.";
        $result = ProhibitedData::replaceDisallowedUrls(
            $text,
            ['https://pnedu.pl/courses/577'],
            '',
        );

        $this->assertStringContainsString('https://pnedu.pl/courses/577', $result);
        $this->assertStringNotContainsString('evil.example', $result);
        $this->assertStringNotContainsString('www.spam.pl', $result);
    }

    public function test_replace_disallowed_urls_can_use_placeholder(): void
    {
        $result = ProhibitedData::replaceDisallowedUrls(
            'Link: https://example.com/path.',
            [],
            '[LINK DO ZAPISU]',
        );

        $this->assertSame('Link: [LINK DO ZAPISU].', $result);
    }
}
