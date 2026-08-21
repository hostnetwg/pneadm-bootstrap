<?php

namespace Tests\Feature\Dev;

use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ClickMeetingEmbedPocTest extends TestCase
{
    public function test_poc_route_is_hidden_without_secret(): void
    {
        config([
            'services.clickmeeting.poc_secret' => 'test-secret',
        ]);

        $this->get('/dev/clickmeeting-embed-poc')->assertNotFound();
        $this->get('/dev/clickmeeting-embed-poc?key=wrong')->assertNotFound();
    }

    public function test_poc_route_renders_with_secret_in_local_environment(): void
    {
        app()->detectEnvironment(fn () => 'local');

        Http::fake([
            'api.clickmeeting.com/v1/conferences/10088701' => Http::response([
                'conference' => [
                    'id' => 10088701,
                    'name' => 'Dev webinar',
                    'access_type' => 1,
                    'room_pin' => '225723416',
                    'room_url' => 'https://example.clickmeeting.com/dev-room',
                    'embed_room_url' => 'https://embed.clickmeeting.com/embed_conference.html?r=999',
                ],
            ], 200),
            'api.clickmeeting.com/v1/conferences/10088701/room/autologin_hash' => Http::response([
                'autologin_hash' => 'AUTOHASH',
            ], 200),
        ]);

        config([
            'services.clickmeeting.url' => 'https://api.clickmeeting.com/v1/',
            'services.clickmeeting.token' => 'test-api-key',
            'services.clickmeeting.poc_secret' => 'test-secret',
            'services.clickmeeting.poc_room_id' => '10088701',
            'services.clickmeeting.poc_email' => 'dev@example.com',
        ]);

        $response = $this->get('/dev/clickmeeting-embed-poc?key=test-secret');

        $response->assertOk();
        $response->assertSee('ClickMeeting embed — PoC', false);
        $response->assertSee('example.clickmeeting.com/225723416', false);
        $response->assertSee('l=AUTOHASH', false);
        $response->assertDontSee('test-api-key', false);
    }

    public function test_poc_route_is_not_registered_outside_local_environment(): void
    {
        app()->detectEnvironment(fn () => 'production');

        config([
            'services.clickmeeting.poc_secret' => 'test-secret',
        ]);

        $this->get('/dev/clickmeeting-embed-poc?key=test-secret')->assertNotFound();
    }
}
