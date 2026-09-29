<?php

namespace Tests\Unit\GrowthOS;

use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Providers\OpenAiProvider;
use App\Services\GrowthOS\AI\Tasks\ConceptRevisionTask;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class OpenAiProviderTest extends TestCase
{
    private ConceptRevisionTask $task;

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.openai.api_key', 'test-key-never-sent-to-openai');
        config()->set('services.openai.base_url', 'https://openai.invalid/v1');
        config()->set('growth_ai.model', 'test-model');
        config()->set('growth_ai.timeout_seconds', 2);
        config()->set('growth_ai.limits.max_output_tokens', 500);
        config()->set('growth_ai.reasoning_effort', 'low');

        Http::preventStrayRequests();
        $this->task = app(ConceptRevisionTask::class);
    }

    public function test_rate_limit_is_retried_once_and_structured_response_is_parsed(): void
    {
        Http::fakeSequence()
            ->push(['error' => ['message' => 'rate limited']], 429)
            ->push($this->openAiResponse($this->validPayload()), 200, ['x-request-id' => 'req-header']);

        $response = $this->provider()->generateStructured(
            taskType: ConceptRevisionTask::TYPE,
            instructions: $this->task->instructions(),
            input: $this->input(),
            schema: $this->task->schema(),
        );

        $this->assertSame('Tytuł zmieniony', $response->payload['title']);
        $this->assertSame('req-header', $response->requestId);
        $this->assertSame(111, $response->inputTokens);
        $this->assertSame(222, $response->outputTokens);
        Http::assertSentCount(2);
        Http::assertSent(function (Request $request): bool {
            return $request->url() === 'https://openai.invalid/v1/responses'
                && $request['store'] === false
                && $request['model'] === 'test-model'
                && $request['text']['format']['strict'] === true
                && ($request['reasoning']['effort'] ?? null) === 'low'
                && is_array($request['input'])
                && ! str_contains($request->body(), 'test-key-never-sent-to-openai');
        });
    }

    public function test_output_text_convenience_field_is_accepted(): void
    {
        Http::fake([
            'openai.invalid/*' => Http::response([
                'id' => 'resp-test',
                'model' => 'test-model',
                'status' => 'completed',
                'output_text' => json_encode($this->validPayload(), JSON_UNESCAPED_UNICODE),
                'output' => [],
                'usage' => [
                    'input_tokens' => 11,
                    'output_tokens' => 22,
                ],
            ], 200),
        ]);

        $response = $this->provider()->generateStructured(
            taskType: ConceptRevisionTask::TYPE,
            instructions: $this->task->instructions(),
            input: $this->input(),
            schema: $this->task->schema(),
        );

        $this->assertSame('Tytuł zmieniony', $response->payload['title']);
    }

    public function test_incomplete_status_is_rejected(): void
    {
        Http::fake([
            'openai.invalid/*' => Http::response([
                'id' => 'resp-test',
                'model' => 'test-model',
                'status' => 'incomplete',
                'output_text' => '{',
                'output' => [],
            ], 200),
        ]);

        try {
            $this->provider()->generateStructured(
                taskType: ConceptRevisionTask::TYPE,
                instructions: $this->task->instructions(),
                input: $this->input(),
                schema: $this->task->schema(),
            );
            $this->fail('Expected GrowthAiException was not thrown.');
        } catch (GrowthAiException $exception) {
            $this->assertSame('incomplete_output', $exception->errorType);
        }
    }

    public function test_server_error_is_retried_once_then_returns_safe_exception(): void
    {
        Http::fakeSequence()
            ->push(['error' => ['message' => 'server error']], 500)
            ->push(['error' => ['message' => 'server error']], 503);

        try {
            $this->provider()->generateStructured(
                taskType: ConceptRevisionTask::TYPE,
                instructions: $this->task->instructions(),
                input: $this->input(),
                schema: $this->task->schema(),
            );
            $this->fail('Expected GrowthAiException was not thrown.');
        } catch (GrowthAiException $exception) {
            $this->assertSame('provider_server_error', $exception->errorType);
            $this->assertSame('AI jest chwilowo niedostępne. Możesz kontynuować ręcznie.', $exception->userMessage);
        }

        Http::assertSentCount(2);
    }

    public function test_invalid_json_is_rejected_without_retrying(): void
    {
        Http::fake([
            'openai.invalid/*' => Http::response(
                $this->openAiResponse('{not-json', encodePayload: false),
                200,
            ),
        ]);

        try {
            $this->provider()->generateStructured(
                taskType: ConceptRevisionTask::TYPE,
                instructions: $this->task->instructions(),
                input: $this->input(),
                schema: $this->task->schema(),
            );
            $this->fail('Expected GrowthAiException was not thrown.');
        } catch (GrowthAiException $exception) {
            $this->assertSame('invalid_json', $exception->errorType);
        }

        Http::assertSentCount(1);
    }

    /**
     * @return array<string, mixed>
     */
    private function validPayload(): array
    {
        return [
            'title' => 'Tytuł zmieniony',
            'subtitle' => 'Podtytuł',
            'promise' => 'Obietnica',
            'audience' => 'Nauczyciele',
            'main_points' => ['Punkt 1'],
            'agenda' => 'Agenda',
            'cta' => 'CTA',
            'additional_material' => 'Materiał',
            'changed_fields' => ['title'],
            'change_summary' => 'Zmieniono tytuł.',
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function input(): array
    {
        return [
            'current_concept' => [
                'title' => 'Tytuł pierwotny',
                'subtitle' => 'Podtytuł',
                'promise' => 'Obietnica',
                'audience' => 'Nauczyciele',
                'main_points' => ['Punkt 1'],
                'agenda' => 'Agenda',
                'cta' => 'CTA',
                'additional_material' => 'Materiał',
            ],
            'user_instruction' => 'Popraw tytuł.',
        ];
    }

    /**
     * @param  array<string, mixed>|string  $payload
     * @return array<string, mixed>
     */
    private function openAiResponse(array|string $payload, bool $encodePayload = true): array
    {
        return [
            'id' => 'resp-test',
            'model' => 'test-model',
            'status' => 'completed',
            'output' => [[
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => $encodePayload
                        ? json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)
                        : $payload,
                ]],
            ]],
            'usage' => [
                'input_tokens' => 111,
                'output_tokens' => 222,
            ],
        ];
    }

    private function provider(): OpenAiProvider
    {
        return app(OpenAiProvider::class);
    }
}
