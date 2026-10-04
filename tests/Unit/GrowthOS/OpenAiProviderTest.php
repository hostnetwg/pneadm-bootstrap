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
        config()->set('growth_ai.research.model', 'research-model');
        config()->set('growth_ai.research.timeout_seconds', 30);
        config()->set('growth_ai.research.reasoning_effort', 'medium');

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

    public function test_concept_revision_request_has_no_web_search_tools(): void
    {
        Http::fake([
            'openai.invalid/*' => Http::response($this->openAiResponse($this->validPayload()), 200),
        ]);

        $this->provider()->generateStructured(
            taskType: ConceptRevisionTask::TYPE,
            instructions: $this->task->instructions(),
            input: $this->input(),
            schema: $this->task->schema(),
        );

        Http::assertSent(function (Request $request): bool {
            return $request['store'] === false
                && $request['model'] === 'test-model'
                && ($request['reasoning']['effort'] ?? null) === 'low'
                && $request['text']['format']['type'] === 'json_schema'
                && $request['text']['format']['strict'] === true
                && ! array_key_exists('tools', $request->data())
                && ! array_key_exists('tool_choice', $request->data())
                && ! array_key_exists('include', $request->data());
        });
    }

    public function test_research_request_uses_web_search_tool_research_model_and_store_false(): void
    {
        Http::fake([
            'openai.invalid/*' => Http::response(
                $this->openAiResearchResponse($this->directionPayload()),
                200,
                ['x-request-id' => 'research-req'],
            ),
        ]);

        $response = $this->provider()->generateStructured(
            taskType: 'direction_planning',
            instructions: 'research',
            input: ['topic' => 'NotebookLM'],
            schema: ['type' => 'object'],
            options: [
                'web_search' => true,
                'require_web_search' => true,
                'use_research_model' => true,
            ],
        );

        $this->assertTrue($response->webSearchUsed);
        $this->assertSame('research-model', $response->model);
        $this->assertSame('https://blog.google/notebooklm/', $response->researchSources[0]['url']);
        $this->assertSame('notebooklm.google', $response->researchSources[1]['domain']);
        Http::assertSent(function (Request $request): bool {
            return $request['store'] === false
                && $request['model'] === 'research-model'
                && ($request['reasoning']['effort'] ?? null) === 'medium'
                && $request['text']['format']['type'] === 'json_schema'
                && $request['text']['format']['name'] === 'direction_planning'
                && $request['text']['format']['strict'] === true
                && $request['tools'] === [['type' => 'web_search']]
                && $request['tool_choice'] === ['type' => 'web_search']
                && $request['include'] === ['web_search_call.action.sources']
                && ! str_contains($request->body(), 'test-key-never-sent-to-openai');
        });
    }

    public function test_research_extracts_sources_skips_malformed_and_deduplicates(): void
    {
        $payload = $this->openAiResearchResponse($this->directionPayload(), [
            [
                'type' => 'web_search_call',
                'action' => [
                    'sources' => [
                        ['url' => 'https://www.gov.pl/web/edukacja', 'title' => 'MEN'],
                        ['url' => 'not-a-url', 'title' => 'bad'],
                        ['url' => 'ftp://example.com/file', 'title' => 'ftp'],
                        ['title' => 'missing url'],
                        'plain-string',
                        ['url' => 'https://www.gov.pl/web/edukacja', 'title' => 'duplicate'],
                    ],
                ],
            ],
            [
                'type' => 'message',
                'content' => [[
                    'type' => 'output_text',
                    'text' => json_encode($this->directionPayload(), JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                    'annotations' => [
                        ['type' => 'url_citation', 'url' => 'https://isap.sejm.gov.pl/', 'title' => 'ISAP'],
                        ['type' => 'file_citation', 'url' => 'https://ignored.example/'],
                    ],
                ]],
            ],
        ]);

        Http::fake([
            'openai.invalid/*' => Http::response($payload, 200),
        ]);

        $response = $this->provider()->generateStructured(
            taskType: 'direction_planning',
            instructions: 'research',
            input: ['topic' => 'NotebookLM'],
            schema: ['type' => 'object'],
            options: ['web_search' => true, 'require_web_search' => true],
        );

        $this->assertSame([
            'https://www.gov.pl/web/edukacja',
            'https://isap.sejm.gov.pl/',
        ], array_column($response->researchSources, 'url'));
        $this->assertSame('www.gov.pl', $response->researchSources[0]['domain']);
    }

    public function test_missing_web_search_call_fails_closed_when_required(): void
    {
        Http::fake([
            'openai.invalid/*' => Http::response($this->openAiResponse($this->directionPayload()), 200),
        ]);

        try {
            $this->provider()->generateStructured(
                taskType: 'direction_planning',
                instructions: 'research',
                input: ['topic' => 'NotebookLM'],
                schema: ['type' => 'object'],
                options: [
                    'web_search' => true,
                    'require_web_search' => true,
                ],
            );
            $this->fail('Expected GrowthAiException was not thrown.');
        } catch (GrowthAiException $exception) {
            $this->assertSame('web_search_missing', $exception->errorType);
            $this->assertSame(GrowthAiException::RESEARCH_FAILED_MESSAGE, $exception->userMessage);
        }
    }

    public function test_research_rate_limit_is_retried_once(): void
    {
        Http::fakeSequence()
            ->push(['error' => ['message' => 'rate limited']], 429)
            ->push($this->openAiResearchResponse($this->directionPayload()), 200);

        $response = $this->provider()->generateStructured(
            taskType: 'direction_planning',
            instructions: 'research',
            input: ['topic' => 'NotebookLM'],
            schema: ['type' => 'object'],
            options: ['web_search' => true, 'require_web_search' => true],
        );

        $this->assertTrue($response->webSearchUsed);
        Http::assertSentCount(2);
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
     * @return array<string, mixed>
     */
    private function directionPayload(): array
    {
        return [
            'working_topic' => 'NotebookLM w pracy nauczyciela',
            'why_now' => 'Narzędzie jest aktualne.',
            'audience' => 'Nauczyciele przedmiotowi',
            'problem' => 'Przygotowanie lekcji z dokumentami zajmuje dużo czasu.',
            'takeaway' => 'Uczestnik ułoży prosty proces pracy z NotebookLM.',
            'sell_later' => 'być może',
            'title_suggestions' => [],
            'change_summary' => 'Zaproponowano kierunek na podstawie researchu.',
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

    /**
     * @param  array<string, mixed>  $payload
     * @param  list<array<string, mixed>>|null  $output
     * @return array<string, mixed>
     */
    private function openAiResearchResponse(array $payload, ?array $output = null): array
    {
        return [
            'id' => 'resp-research',
            'model' => 'research-model',
            'status' => 'completed',
            'output' => $output ?? [
                [
                    'type' => 'web_search_call',
                    'action' => [
                        'sources' => [
                            ['url' => 'https://blog.google/notebooklm/', 'title' => 'NotebookLM blog'],
                        ],
                    ],
                ],
                [
                    'type' => 'message',
                    'content' => [[
                        'type' => 'output_text',
                        'text' => json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR),
                        'annotations' => [
                            ['type' => 'url_citation', 'url' => 'https://notebooklm.google/', 'title' => 'NotebookLM'],
                        ],
                    ]],
                ],
            ],
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
