<?php

namespace Tests\Unit\GrowthOS;

use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Providers\OpenAiImageProvider;
use App\Services\GrowthOS\AI\Tasks\GraphicImageTask;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiImageProviderTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.api_key', 'test-key-never-sent-to-openai');
        config()->set('services.openai.base_url', 'https://openai.invalid/v1');
        config()->set('growth_ai.images.model', 'test-image-model');
        config()->set('growth_ai.images.timeout_seconds', 30);

        Http::preventStrayRequests();
    }

    public function test_request_body_and_base64_image_are_handled(): void
    {
        Http::fake([
            'openai.invalid/*' => Http::response(['data' => [['b64_json' => base64_encode('png-bytes')]]], 200, ['x-request-id' => 'req-image']),
        ]);

        $response = $this->provider()->generateImage('Opis obrazu', '1536x1024', 'medium');

        $this->assertSame('png-bytes', $response->bytes);
        $this->assertSame('req-image', $response->requestId);
        $this->assertSame('test-image-model', $response->model);
        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://openai.invalid/v1/images/generations'
                && $request['model'] === 'test-image-model'
                && $request['prompt'] === 'Opis obrazu'
                && $request['size'] === '1536x1024'
                && $request['quality'] === 'medium'
                && $request['n'] === 1
                && $request['output_format'] === 'png'
                && $request->hasHeader('Authorization', 'Bearer test-key-never-sent-to-openai')
                && ! str_contains($request->body(), 'test-key-never-sent-to-openai');
        });
    }

    public function test_edit_sends_the_source_image_as_multipart(): void
    {
        Http::fake([
            'openai.invalid/*' => Http::response(['data' => [['b64_json' => base64_encode('square-bytes')]]], 200),
        ]);

        $response = $this->provider()->editImage('Przekomponuj', 'jpeg-source', 'image/jpeg', '1024x1024', 'medium');

        $this->assertSame('square-bytes', $response->bytes);
        Http::assertSent(function (Request $request): bool {
            $parts = collect($request->data())->keyBy('name');

            return $request->url() === 'https://openai.invalid/v1/images/edits'
                && $request->isMultipart()
                && ($parts['image[]']['contents'] ?? null) === 'jpeg-source'
                && ($parts['image[]']['filename'] ?? null) === 'source.jpg'
                && ($parts['model']['contents'] ?? null) === 'test-image-model'
                && ($parts['prompt']['contents'] ?? null) === 'Przekomponuj'
                && ($parts['size']['contents'] ?? null) === '1024x1024'
                && ($parts['quality']['contents'] ?? null) === 'medium';
        });
    }

    public function test_server_error_is_not_retried(): void
    {
        Http::fake(['openai.invalid/*' => Http::response(['error' => ['message' => 'boom']], 500)]);

        $this->assertErrorType('provider_server_error', fn () => $this->provider()->generateImage('Opis', '1024x1024', 'medium'));
        Http::assertSentCount(1);
    }

    public function test_moderation_block_has_its_own_message(): void
    {
        Http::fake(['openai.invalid/*' => Http::response(['error' => ['code' => 'moderation_blocked', 'message' => 'blocked']], 400)]);

        try {
            $this->provider()->generateImage('Opis', '1024x1024', 'medium');
            $this->fail('Expected exception.');
        } catch (GrowthAiException $exception) {
            $this->assertSame('provider_content_policy', $exception->errorType);
            $this->assertSame(OpenAiImageProvider::CONTENT_POLICY_MESSAGE, $exception->userMessage);
        }
    }

    public function test_authentication_error_and_missing_image_data(): void
    {
        Http::fakeSequence()
            ->push(['error' => ['message' => 'unauthorized']], 401)
            ->push(['data' => [['url' => 'https://example.invalid/image.png']]], 200);

        $this->assertErrorType('provider_authentication_error', fn () => $this->provider()->generateImage('Opis', '1024x1024', 'medium'));

        try {
            $this->provider()->generateImage('Opis', '1024x1024', 'medium');
            $this->fail('Expected exception.');
        } catch (GrowthAiException $exception) {
            $this->assertSame('missing_image_data', $exception->errorType);
            $this->assertSame(GraphicImageTask::INVALID_IMAGE_MESSAGE, $exception->userMessage);
        }
    }

    public function test_missing_api_key_never_calls_the_provider(): void
    {
        config()->set('services.openai.api_key', '');
        Http::fake();

        $this->assertErrorType('missing_api_key', fn () => $this->provider()->generateImage('Opis', '1024x1024', 'medium'));
        Http::assertNothingSent();
    }

    private function assertErrorType(string $expected, callable $call): void
    {
        try {
            $call();
            $this->fail('Expected GrowthAiException.');
        } catch (GrowthAiException $exception) {
            $this->assertSame($expected, $exception->errorType);
        }
    }

    private function provider(): OpenAiImageProvider
    {
        return app(OpenAiImageProvider::class);
    }
}
