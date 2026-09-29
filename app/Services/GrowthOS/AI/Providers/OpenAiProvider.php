<?php

namespace App\Services\GrowthOS\AI\Providers;

use App\Services\GrowthOS\AI\Contracts\GrowthAiProvider;
use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use JsonException;
use Throwable;

final class OpenAiProvider implements GrowthAiProvider
{
    public function name(): string
    {
        return 'openai';
    }

    public function model(): string
    {
        return (string) config('growth_ai.model');
    }

    public function generateStructured(
        string $taskType,
        string $instructions,
        array $input,
        array $schema,
    ): AiProviderResponse {
        $apiKey = trim((string) config('services.openai.api_key'));
        if ($apiKey === '') {
            throw new GrowthAiException(
                errorType: 'missing_api_key',
                userMessage: 'AI nie jest skonfigurowane. Możesz kontynuować ręcznie.',
            );
        }

        $body = [
            'model' => $this->model(),
            'instructions' => $instructions,
            'input' => [
                [
                    'role' => 'user',
                    'content' => [
                        [
                            'type' => 'input_text',
                            'text' => json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        ],
                    ],
                ],
            ],
            'text' => [
                'format' => [
                    'type' => 'json_schema',
                    'name' => $taskType,
                    'strict' => true,
                    'schema' => $schema,
                ],
            ],
            'max_output_tokens' => (int) config('growth_ai.limits.max_output_tokens'),
            'store' => false,
            // gpt-5* zużywa limity też na reasoning; niski effort zmniejsza ucinanie JSON.
            'reasoning' => [
                'effort' => (string) config('growth_ai.reasoning_effort', 'low'),
            ],
        ];

        $startedAt = hrtime(true);
        $response = $this->sendWithSingleRetry($apiKey, $body);
        $latencyMs = (int) round((hrtime(true) - $startedAt) / 1_000_000);

        if (! $response->successful()) {
            throw $this->exceptionForResponse($response);
        }

        if (($response->json('status') ?? null) === 'incomplete') {
            throw GrowthAiException::invalidResponse('incomplete_output');
        }

        $text = $this->extractOutputText($response);
        if ($text === null || trim($text) === '') {
            throw GrowthAiException::invalidResponse('missing_output_text');
        }

        $text = $this->normalizeJsonText($text);

        try {
            $payload = json_decode($text, true, flags: JSON_THROW_ON_ERROR);
        } catch (JsonException $exception) {
            throw new GrowthAiException(
                errorType: 'invalid_json',
                userMessage: 'Nie udało się przygotować poprawnej propozycji AI. Twoja obecna koncepcja nie została zmieniona.',
                previous: $exception,
            );
        }

        if (! is_array($payload)) {
            throw GrowthAiException::invalidResponse('invalid_json_shape');
        }

        return new AiProviderResponse(
            payload: $payload,
            provider: $this->name(),
            model: (string) ($response->json('model') ?: $this->model()),
            requestId: (string) ($response->header('x-request-id') ?: $response->json('id', '')),
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
            latencyMs: $latencyMs,
        );
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function sendWithSingleRetry(string $apiKey, array $body): Response
    {
        $lastException = null;

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = Http::baseUrl((string) config('services.openai.base_url'))
                    ->withToken($apiKey)
                    ->acceptJson()
                    ->asJson()
                    ->timeout((int) config('growth_ai.timeout_seconds'))
                    ->connectTimeout(min(10, (int) config('growth_ai.timeout_seconds')))
                    ->post('/responses', $body);

                if ($attempt === 1 && ($response->status() === 429 || $response->serverError())) {
                    continue;
                }

                return $response;
            } catch (ConnectionException $exception) {
                $lastException = $exception;

                if ($attempt === 2) {
                    break;
                }
            } catch (Throwable $exception) {
                throw GrowthAiException::unavailable('http_client_error', previous: $exception);
            }
        }

        throw GrowthAiException::unavailable('connection_error', previous: $lastException);
    }

    private function exceptionForResponse(Response $response): GrowthAiException
    {
        return match (true) {
            $response->status() === 429 => GrowthAiException::unavailable('provider_rate_limited'),
            $response->serverError() => GrowthAiException::unavailable('provider_server_error'),
            in_array($response->status(), [401, 403], true) => new GrowthAiException(
                errorType: 'provider_authentication_error',
                userMessage: 'AI nie jest poprawnie skonfigurowane. Możesz kontynuować ręcznie.',
            ),
            default => GrowthAiException::unavailable('provider_api_error', retryable: false),
        };
    }

    private function extractOutputText(Response $response): ?string
    {
        $aggregated = $response->json('output_text');
        if (is_string($aggregated) && trim($aggregated) !== '') {
            return $aggregated;
        }

        $output = $response->json('output');
        if (! is_array($output)) {
            return null;
        }

        $chunks = [];

        foreach ($output as $item) {
            if (! is_array($item)) {
                continue;
            }

            if (($item['type'] ?? null) === 'output_text' && is_string($item['text'] ?? null)) {
                $chunks[] = $item['text'];

                continue;
            }

            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach (($item['content'] ?? []) as $content) {
                if (! is_array($content)) {
                    continue;
                }

                $type = $content['type'] ?? null;
                if (in_array($type, ['output_text', 'text'], true) && is_string($content['text'] ?? null)) {
                    $chunks[] = $content['text'];
                }
            }
        }

        if ($chunks === []) {
            return null;
        }

        return implode("\n", $chunks);
    }

    private function normalizeJsonText(string $text): string
    {
        $trimmed = trim($text);

        if (str_starts_with($trimmed, '```')) {
            $trimmed = preg_replace('/^```(?:json)?\s*/i', '', $trimmed) ?? $trimmed;
            $trimmed = preg_replace('/\s*```$/', '', $trimmed) ?? $trimmed;
            $trimmed = trim($trimmed);
        }

        return $trimmed;
    }
}
