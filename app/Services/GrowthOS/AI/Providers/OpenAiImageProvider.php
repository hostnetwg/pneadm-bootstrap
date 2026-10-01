<?php

namespace App\Services\GrowthOS\AI\Providers;

use App\Services\GrowthOS\AI\Contracts\GrowthAiImageProvider;
use App\Services\GrowthOS\AI\Data\AiImageResponse;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Tasks\GraphicImageTask;
use Closure;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Throwable;

final class OpenAiImageProvider implements GrowthAiImageProvider
{
    public const CONTENT_POLICY_MESSAGE = 'OpenAI odrzucił opis obrazu ze względu na zasady treści. Zmień opis i spróbuj ponownie.';

    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return (string) config('growth_ai.images.model');
    }

    public function generateImage(string $prompt, string $size, string $quality): AiImageResponse
    {
        return $this->send(fn (PendingRequest $http): Response => $http->asJson()->post('/images/generations', [
            'model' => $this->model(),
            'prompt' => $prompt,
            'n' => 1,
            'size' => $size,
            'quality' => $quality,
            'output_format' => 'png',
        ]));
    }

    public function editImage(string $prompt, string $sourceBytes, string $sourceMime, string $size, string $quality): AiImageResponse
    {
        $extension = $sourceMime === 'image/png' ? 'png' : 'jpg';

        return $this->send(fn (PendingRequest $http): Response => $http
            ->attach('image[]', $sourceBytes, 'source.'.$extension, ['Content-Type' => $sourceMime])
            ->post('/images/edits', [
                'model' => $this->model(),
                'prompt' => $prompt,
                'n' => '1',
                'size' => $size,
                'quality' => $quality,
                'output_format' => 'png',
            ]));
    }

    /**
     * No retry: an image is paid for and slow to generate.
     *
     * @param  Closure(PendingRequest): Response  $request
     */
    private function send(Closure $request): AiImageResponse
    {
        $apiKey = trim((string) config('services.openai.api_key'));
        if ($apiKey === '') {
            throw new GrowthAiException(
                errorType: 'missing_api_key',
                userMessage: 'AI nie jest skonfigurowane. Możesz kontynuować ręcznie.',
            );
        }

        $timeout = (int) config('growth_ai.images.timeout_seconds');
        $startedAt = hrtime(true);

        try {
            $response = $request(
                Http::baseUrl((string) config('services.openai.base_url'))
                    ->withToken($apiKey)
                    ->acceptJson()
                    ->timeout($timeout)
                    ->connectTimeout(min(10, $timeout)),
            );
        } catch (ConnectionException $exception) {
            throw GrowthAiException::unavailable('connection_error', previous: $exception);
        } catch (Throwable $exception) {
            throw GrowthAiException::unavailable('http_client_error', previous: $exception);
        }

        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if (! $response->successful()) {
            throw $this->exceptionForResponse($response);
        }

        $encoded = $response->json('data.0.b64_json');
        $bytes = is_string($encoded) ? base64_decode($encoded, true) : false;
        if (! is_string($bytes) || $bytes === '') {
            throw new GrowthAiException(
                errorType: 'missing_image_data',
                userMessage: GraphicImageTask::INVALID_IMAGE_MESSAGE,
            );
        }

        return new AiImageResponse(
            bytes: $bytes,
            provider: $this->name(),
            model: $this->model(),
            requestId: (string) ($response->header('x-request-id') ?: ''),
            latencyMs: $latencyMs,
        );
    }

    private function exceptionForResponse(Response $response): GrowthAiException
    {
        $code = (string) $response->json('error.code', '');

        return match (true) {
            $response->status() === 400 && in_array($code, ['moderation_blocked', 'content_policy_violation'], true) => new GrowthAiException(
                errorType: 'provider_content_policy',
                userMessage: self::CONTENT_POLICY_MESSAGE,
            ),
            $response->status() === 429 => GrowthAiException::unavailable('provider_rate_limited'),
            $response->serverError() => GrowthAiException::unavailable('provider_server_error'),
            in_array($response->status(), [401, 403], true) => new GrowthAiException(
                errorType: 'provider_authentication_error',
                userMessage: 'AI nie jest poprawnie skonfigurowane albo konto OpenAI nie ma dostępu do modelu obrazów. Możesz kontynuować ręcznie.',
            ),
            default => GrowthAiException::unavailable('provider_api_error', retryable: false),
        };
    }
}
