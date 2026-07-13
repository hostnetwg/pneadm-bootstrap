<?php

namespace Tests\Unit;

use App\Services\ClickMeetingService;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClickMeetingServiceTest extends TestCase
{
    public function test_extract_token_from_array_response(): void
    {
        $service = app(ClickMeetingService::class);

        $this->assertSame('HCKFWT', $service->extractTokenFromResponse(['HCKFWT']));
        $this->assertSame('D989F8', $service->extractTokenFromResponse(['token' => 'D989F8']));
        $this->assertNull($service->extractTokenFromResponse([]));
    }

    public function test_get_access_token_for_email_uses_post_endpoint_first(): void
    {
        Http::fake([
            'api.clickmeeting.com/v1/conferences/10088701/token' => Http::response(['ABC123'], 200),
        ]);

        config([
            'services.clickmeeting.url' => 'https://api.clickmeeting.com/v1/',
            'services.clickmeeting.token' => 'test-api-key',
        ]);

        $result = app(ClickMeetingService::class)->getAccessTokenForEmail(
            '10088701',
            'waldemar.grabowski@hostnet.pl'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('ABC123', $result['token']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.clickmeeting.com/v1/conferences/10088701/token'
                && $request['email'] === 'waldemar.grabowski@hostnet.pl';
        });
    }

    public function test_get_access_token_for_email_falls_back_to_tokens_list(): void
    {
        Http::fake([
            'api.clickmeeting.com/v1/conferences/10088701/token' => Http::response([], 404),
            'api.clickmeeting.com/v1/conferences/10088701/tokens' => Http::response([
                'access_tokens' => [
                    [
                        'token' => 'ZZZ999',
                        'sent_to_email' => 'other@example.com',
                        'first_use_date' => null,
                    ],
                    [
                        'token' => 'TOKEN42',
                        'sent_to_email' => 'waldemar.grabowski@hostnet.pl',
                        'first_use_date' => null,
                    ],
                ],
            ], 200),
        ]);

        config([
            'services.clickmeeting.url' => 'https://api.clickmeeting.com/v1/',
            'services.clickmeeting.token' => 'test-api-key',
        ]);

        $result = app(ClickMeetingService::class)->getAccessTokenForEmail(
            '10088701',
            'waldemar.grabowski@hostnet.pl'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('TOKEN42', $result['token']);
    }

    public function test_get_conference_reads_access_type(): void
    {
        Http::fake([
            'api.clickmeeting.com/v1/conferences/10088701' => Http::response([
                'conference' => [
                    'id' => 10088701,
                    'access_type' => 3,
                    'status' => 'active',
                ],
            ], 200),
        ]);

        config([
            'services.clickmeeting.url' => 'https://api.clickmeeting.com/v1/',
            'services.clickmeeting.token' => 'test-api-key',
        ]);

        $result = app(ClickMeetingService::class)->getConference('10088701');

        $this->assertTrue($result['success']);
        $this->assertSame(3, $result['access_type']);
    }

    public function test_build_join_url_appends_token(): void
    {
        $service = app(ClickMeetingService::class);

        $this->assertSame(
            'https://pnedu.clickmeeting.com/wydarzenie-testowe/MCHK7N',
            $service->buildJoinUrl('https://pnedu.clickmeeting.com/wydarzenie-testowe/', 'MCHK7N')
        );
        $this->assertSame(
            'https://pnedu.clickmeeting.com/wydarzenie-testowe',
            $service->buildJoinUrl('https://pnedu.clickmeeting.com/wydarzenie-testowe', null)
        );
    }
}
