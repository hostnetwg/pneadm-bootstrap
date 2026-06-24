<?php

namespace Tests\Unit;

use App\Services\Analytics\AnalyticsPayloadSanitizer;
use Tests\TestCase;

class AnalyticsPayloadSanitizerTest extends TestCase
{
    public function test_it_keeps_only_allowed_metadata_and_removes_personal_data_recursively(): void
    {
        $sanitized = (new AnalyticsPayloadSanitizer)->sanitize([
            'event_name' => 'order_form_viewed',
            'event_category' => 'order_form',
            'email' => 'secret@example.com',
            'url' => 'https://pnedu.pl/courses/123?email=secret@example.com&utm_source=newsletter',
            'referrer' => 'https://facebook.com/post/1?fbclid=abc',
            'metadata_json' => [
                'buyer_type' => 'school',
                'participant_count' => 2,
                'nested' => [
                    'phone' => '123456789',
                    'payment_type' => 'deferred',
                ],
                'invoice_data' => 'forbidden',
                'query_parameters' => [
                    'utm_source' => 'newsletter',
                    'email' => 'secret@example.com',
                ],
            ],
        ]);

        $this->assertSame('order_form_viewed', $sanitized['event_name']);
        $this->assertSame('/courses/123', $sanitized['path']);
        $this->assertSame('facebook.com', $sanitized['referrer_domain']);
        $this->assertSame('school', $sanitized['metadata']['buyer_type']);
        $this->assertSame(2, $sanitized['metadata']['participant_count']);
        $this->assertSame(['utm_source' => 'newsletter'], $sanitized['metadata']['query_parameters']);
        $this->assertArrayNotHasKey('email', $sanitized);
        $this->assertArrayNotHasKey('url', $sanitized);
        $this->assertArrayNotHasKey('referrer', $sanitized);
        $this->assertArrayNotHasKey('invoice_data', $sanitized['metadata']);
        $this->assertArrayNotHasKey('nested', $sanitized['metadata']);
    }

    public function test_it_detects_forbidden_keys_in_nested_payloads(): void
    {
        $this->assertTrue((new AnalyticsPayloadSanitizer)->containsForbiddenKeys([
            'metadata' => [
                'safe' => true,
                'buyer_nip' => '123',
            ],
        ]));
    }
}
