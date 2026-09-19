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

    public function test_generate_autologin_hash_posts_to_clickmeeting(): void
    {
        Http::fake([
            'api.clickmeeting.com/v1/conferences/10088701/room/autologin_hash' => Http::response([
                'autologin_hash' => 'HASH123',
            ], 200),
        ]);

        config([
            'services.clickmeeting.url' => 'https://api.clickmeeting.com/v1/',
            'services.clickmeeting.token' => 'test-api-key',
        ]);

        $result = app(ClickMeetingService::class)->generateAutologinHash(
            '10088701',
            'dev@example.com',
            'Dev Tester',
            'listener',
            'TOK99'
        );

        $this->assertTrue($result['success']);
        $this->assertSame('HASH123', $result['autologin_hash']);

        Http::assertSent(function ($request) {
            return $request->url() === 'https://api.clickmeeting.com/v1/conferences/10088701/room/autologin_hash'
                && $request['email'] === 'dev@example.com'
                && $request['nickname'] === 'Dev Tester'
                && $request['role'] === 'listener'
                && $request['token'] === 'TOK99';
        });
    }

    public function test_build_autologin_url_appends_query_param(): void
    {
        $service = app(ClickMeetingService::class);

        $this->assertSame(
            'https://example.clickmeeting.com/room?l=ABC',
            $service->buildAutologinUrl('https://example.clickmeeting.com/room', 'ABC')
        );
        $this->assertSame(
            'https://embed.clickmeeting.com/embed_conference.html?r=123&l=ABC',
            $service->buildAutologinUrl('https://embed.clickmeeting.com/embed_conference.html?r=123', 'ABC')
        );
    }

    public function test_build_pin_embed_url_matches_official_script_target(): void
    {
        $service = app(ClickMeetingService::class);

        $this->assertSame(
            'https://pnedu.clickmeeting.com/225723416?popup=off&lang=pl',
            $service->buildPinEmbedUrl('https://pnedu.clickmeeting.com/testowy-webinar', '225723416')
        );
    }

    public function test_deactivate_tokens_sends_delete_with_token_list(): void
    {
        Http::fake([
            'api.clickmeeting.com/v1/conferences/10088701/tokens' => Http::response([
                'status' => 'deleted',
                'message' => 'The tokens is not accessible anymore',
            ], 200),
        ]);

        config([
            'services.clickmeeting.url' => 'https://api.clickmeeting.com/v1/',
            'services.clickmeeting.token' => 'test-api-key',
        ]);

        $result = app(ClickMeetingService::class)->deactivateTokens('10088701', ['XF34TY']);

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) {
            return $request->method() === 'DELETE'
                && $request->url() === 'https://api.clickmeeting.com/v1/conferences/10088701/tokens'
                && data_get($request->data(), 'tokens.0') === 'XF34TY';
        });
    }

    public function test_deactivate_tokens_rejects_empty_list(): void
    {
        config([
            'services.clickmeeting.url' => 'https://api.clickmeeting.com/v1/',
            'services.clickmeeting.token' => 'test-api-key',
        ]);

        $result = app(ClickMeetingService::class)->deactivateTokens('10088701', ['', '  ']);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Brak tokenów', (string) ($result['error'] ?? ''));
    }

    public function test_build_post_training_thank_you_url_uses_pnedu_frontend_base(): void
    {
        config(['services.pnedu_frontend_url' => 'https://pnedu.pl']);

        $url = app(ClickMeetingService::class)->buildPostTrainingThankYouUrl(563);

        $this->assertSame('https://pnedu.pl/po-szkoleniu?course=563', $url);
    }

    public function test_update_thank_you_page_url_puts_settings_to_clickmeeting(): void
    {
        Http::fake([
            'api.clickmeeting.com/v1/conferences/10166300' => Http::response(['status' => 'ok'], 200),
        ]);

        config([
            'services.clickmeeting.url' => 'https://api.clickmeeting.com/v1/',
            'services.clickmeeting.token' => 'test-api-key',
        ]);

        $thankYouUrl = 'https://pnedu.pl/po-szkoleniu?course=563';
        $result = app(ClickMeetingService::class)->updateThankYouPageUrl('10166300', $thankYouUrl);

        $this->assertTrue($result['success']);

        Http::assertSent(function ($request) use ($thankYouUrl) {
            return $request->method() === 'PUT'
                && $request->url() === 'https://api.clickmeeting.com/v1/conferences/10166300'
                && data_get($request->data(), 'settings[thank_you_page_url]') === $thankYouUrl;
        });
    }

    public function test_update_thank_you_page_url_requires_api_token(): void
    {
        config(['services.clickmeeting.token' => '']);

        $result = app(ClickMeetingService::class)->updateThankYouPageUrl(
            '10166300',
            'https://pnedu.pl/po-szkoleniu?course=563'
        );

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Brak konfiguracji', (string) ($result['error'] ?? ''));
    }

    public function test_list_conferences_reads_active_and_scheduled_rooms(): void
    {
        Http::fake([
            'api.clickmeeting.com/v1/conferences' => Http::response([
                'active_conferences' => [
                    ['id' => 11, 'name' => 'Aktywne'],
                ],
                'scheduled_conferences' => [
                    ['id' => 22, 'name' => 'Zaplanowane'],
                ],
            ], 200),
        ]);

        config([
            'services.clickmeeting.url' => 'https://api.clickmeeting.com/v1/',
            'services.clickmeeting.token' => 'test-api-key',
        ]);

        $result = app(ClickMeetingService::class)->listConferences();

        $this->assertTrue($result['success']);
        $this->assertSame(11, $result['active_conferences'][0]['id']);
        $this->assertSame(22, $result['scheduled_conferences'][0]['id']);
    }

    public function test_to_warsaw_datetime_local_converts_utc_offset(): void
    {
        $service = app(ClickMeetingService::class);

        $this->assertSame(
            '2026-10-01T10:00',
            $service->toWarsawDatetimeLocal('2026-10-01T08:00:00+00:00')
        );
        $this->assertNull($service->toWarsawDatetimeLocal(''));
        $this->assertNull($service->toWarsawDatetimeLocal(null));
    }

    public function test_start_times_differ_compares_warsaw_minute_with_course_wall_clock(): void
    {
        $service = app(ClickMeetingService::class);

        $this->assertFalse($service->startTimesDiffer(
            '2026-10-02T10:00:00+02:00',
            '2026-10-02 10:00:00'
        ));
        $this->assertTrue($service->startTimesDiffer(
            '2026-10-02T10:00:00+02:00',
            '2026-10-03 09:00:00'
        ));
        $this->assertTrue($service->startTimesDiffer(
            '2026-10-02T10:00:00+02:00',
            null
        ));
        $this->assertFalse($service->startTimesDiffer(null, '2026-10-02 10:00:00'));
    }

    public function test_normalize_room_url_strips_trailing_slash(): void
    {
        $service = app(ClickMeetingService::class);

        $this->assertSame(
            'https://pnedu.clickmeeting.com/nowy',
            $service->normalizeRoomUrl('https://pnedu.clickmeeting.com/nowy/')
        );
        $this->assertTrue($service->roomUrlsDiffer(
            'https://pnedu.clickmeeting.com/stary',
            'https://pnedu.clickmeeting.com/nowy/'
        ));
        $this->assertFalse($service->roomUrlsDiffer(
            'https://pnedu.clickmeeting.com/nowy/',
            'https://pnedu.clickmeeting.com/nowy'
        ));
    }

    public function test_access_type_label_maps_known_values(): void
    {
        $service = app(ClickMeetingService::class);

        $this->assertSame(1, ClickMeetingService::ACCESS_TYPE_OPEN);
        $this->assertSame('Dla wszystkich', $service->accessTypeLabel(1));
        $this->assertSame('Hasło', $service->accessTypeLabel(2));
        $this->assertSame('Tokeny', $service->accessTypeLabel(3));
        $this->assertSame('—', $service->accessTypeLabel(null));
        $this->assertSame(1, $service->extractAccessType(['access_type' => '1']));
        $this->assertNull($service->extractAccessType([]));
    }
}
