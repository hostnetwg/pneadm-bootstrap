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

    /**
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $schema
     * @param  array{
     *     web_search?: bool,
     *     require_web_search?: bool,
     *     use_research_model?: bool,
     *     model?: string,
     *     reasoning_effort?: string,
     *     max_output_tokens?: int,
     *     selection_source?: string
     * }  $options
     */
    public function generateStructured(
        string $taskType,
        string $instructions,
        array $input,
        array $schema,
        array $options = [],
    ): AiProviderResponse {
        $apiKey = trim((string) config('services.openai.api_key'));
        if ($apiKey === '') {
            throw new GrowthAiException(
                errorType: 'missing_api_key',
                userMessage: 'AI nie jest skonfigurowane. Możesz kontynuować ręcznie.',
            );
        }

        $webSearch = ($options['web_search'] ?? false) === true;
        $useResearchModel = $webSearch || ($options['use_research_model'] ?? false) === true;

        $fallbackModel = $useResearchModel
            ? (string) config('growth_ai.research.model', $this->model())
            : $this->model();
        $fallbackEffort = $useResearchModel
            ? (string) config('growth_ai.research.reasoning_effort', 'medium')
            : (string) config('growth_ai.reasoning_effort', 'low');

        $model = trim((string) ($options['model'] ?? '')) !== ''
            ? (string) $options['model']
            : $fallbackModel;
        $reasoningEffort = trim((string) ($options['reasoning_effort'] ?? '')) !== ''
            ? (string) $options['reasoning_effort']
            : $fallbackEffort;
        $maxOutputTokens = isset($options['max_output_tokens']) && (int) $options['max_output_tokens'] > 0
            ? (int) $options['max_output_tokens']
            : (int) config('growth_ai.limits.max_output_tokens');
        $selectionSource = (string) ($options['selection_source'] ?? '');

        $body = [
            'model' => $model,
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
            'max_output_tokens' => $maxOutputTokens,
            'store' => false,
            // Reasoning tokens share the output budget; effort-aware max_output_tokens reduces incomplete JSON.
            'reasoning' => [
                'effort' => $reasoningEffort,
            ],
        ];

        if ($webSearch) {
            $body['tools'] = [['type' => 'web_search']];
            $body['tool_choice'] = ['type' => 'web_search'];
            $body['include'] = ['web_search_call.action.sources'];
        }

        $startedAt = hrtime(true);
        $response = $this->sendWithSingleRetry(
            $apiKey,
            $body,
            $webSearch || $useResearchModel
                ? (int) config('growth_ai.research.timeout_seconds', config('growth_ai.timeout_seconds'))
                : (int) config('growth_ai.timeout_seconds'),
        );
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
                userMessage: GrowthAiException::INVALID_RESPONSE_MESSAGE,
                previous: $exception,
            );
        }

        if (! is_array($payload)) {
            throw GrowthAiException::invalidResponse('invalid_json_shape');
        }

        $webSearchUsed = $this->webSearchWasUsed($response);
        $webSearchNote = ($webSearch && ! $webSearchUsed)
            ? \App\Support\GrowthOS\GrowthAiRequestOptions::WEB_SEARCH_SKIPPED_MESSAGE
            : null;

        return new AiProviderResponse(
            payload: $payload,
            provider: $this->name(),
            model: (string) ($response->json('model') ?: $body['model']),
            requestId: (string) ($response->header('x-request-id') ?: $response->json('id', '')),
            inputTokens: (int) $response->json('usage.input_tokens', 0),
            outputTokens: (int) $response->json('usage.output_tokens', 0),
            latencyMs: $latencyMs,
            webSearchUsed: $webSearchUsed,
            researchSources: $webSearchUsed ? $this->extractResearchSources($response) : [],
            reasoningEffort: $reasoningEffort,
            selectionSource: $selectionSource,
            webSearchRequested: $webSearch,
            webSearchNote: $webSearchNote,
        );
    }

    /**
     * @param  array<string, mixed>  $body
     */
    private function sendWithSingleRetry(string $apiKey, array $body, int $timeoutSeconds): Response
    {
        $lastException = null;
        $timeoutSeconds = max(5, $timeoutSeconds);

        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = Http::baseUrl((string) config('services.openai.base_url'))
                    ->withToken($apiKey)
                    ->acceptJson()
                    ->asJson()
                    ->timeout($timeoutSeconds)
                    ->connectTimeout(min(10, $timeoutSeconds))
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

    private function webSearchWasUsed(Response $response): bool
    {
        foreach ($this->outputItems($response) as $item) {
            if (($item['type'] ?? null) === 'web_search_call') {
                return true;
            }
        }

        return false;
    }

    /**
     * Sources come from the Responses API, never from model-invented URL fields.
     *
     * @return list<array{title: string, url: string, domain: string}>
     */
    private function extractResearchSources(Response $response): array
    {
        $sources = [];
        $seen = [];

        foreach ($this->outputItems($response) as $item) {
            if (($item['type'] ?? null) === 'web_search_call') {
                $actionSources = data_get($item, 'action.sources', []);
                if (is_array($actionSources)) {
                    foreach ($actionSources as $source) {
                        $this->pushSource($sources, $seen, is_array($source) ? ($source['url'] ?? null) : null, is_array($source) ? ($source['title'] ?? '') : '');
                    }
                }
            }

            if (($item['type'] ?? null) !== 'message') {
                continue;
            }

            foreach (($item['content'] ?? []) as $content) {
                if (! is_array($content)) {
                    continue;
                }
                foreach (($content['annotations'] ?? []) as $annotation) {
                    if (! is_array($annotation) || ($annotation['type'] ?? null) !== 'url_citation') {
                        continue;
                    }
                    $this->pushSource($sources, $seen, $annotation['url'] ?? null, $annotation['title'] ?? '');
                }
            }
        }

        return array_slice($sources, 0, 6);
    }

    /**
     * @param  list<array{title: string, url: string, domain: string}>  $sources
     * @param  array<string, true>  $seen
     */
    private function pushSource(array &$sources, array &$seen, mixed $url, mixed $title): void
    {
        if (! is_string($url)) {
            return;
        }

        $url = trim($url);
        if ($url === '' || isset($seen[$url])) {
            return;
        }

        $parts = parse_url($url);
        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower((string) ($parts['host'] ?? ''));
        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || ! filter_var($url, FILTER_VALIDATE_URL)) {
            return;
        }

        $seen[$url] = true;
        $title = is_string($title) ? trim($title) : '';
        $sources[] = [
            'title' => $title !== '' ? $title : $host,
            'url' => $url,
            'domain' => $host,
        ];
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function outputItems(Response $response): array
    {
        $output = $response->json('output');
        if (! is_array($output)) {
            return [];
        }

        $items = [];
        foreach ($output as $item) {
            if (is_array($item)) {
                $items[] = $item;
            }
        }

        return $items;
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
