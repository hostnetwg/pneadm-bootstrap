<?php

namespace App\Services\GrowthOS\AI;

use App\Models\User;
use App\Services\GrowthOS\AI\Contracts\GrowthAiProvider;
use App\Services\GrowthOS\AI\Contracts\GrowthAiTask;
use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Data\ConceptRevisionResult;
use App\Services\GrowthOS\AI\Data\MaterialDraftResult;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Tasks\ConceptRevisionTask;
use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Throwable;

final class GrowthAiService
{
    public function __construct(
        private readonly GrowthAiProvider $provider,
        private readonly ConceptRevisionTask $conceptRevisionTask,
        private readonly MaterialDraftTask $materialDraftTask,
    ) {}

    /**
     * @param  array<string, mixed>  $concept
     */
    public function reviseConcept(User $user, array $concept, string $audience, string $instruction): ConceptRevisionResult
    {
        $this->ensureAllowed($user);

        $task = $this->conceptRevisionTask;
        $input = $task->input($concept, $audience, $instruction);

        return $this->run(
            $user,
            $task,
            $input,
            fn (AiProviderResponse $response): ConceptRevisionResult => $task->validateAndNormalize($response, $input),
        );
    }

    /**
     * @param  array<string, mixed>  $context
     */
    public function draftMaterial(User $user, array $context): MaterialDraftResult
    {
        $this->ensureAllowed($user);

        $task = $this->materialDraftTask;
        $input = $task->input($context);

        return $this->run(
            $user,
            $task,
            $input,
            fn (AiProviderResponse $response): MaterialDraftResult => $task->validateAndNormalize($response, $input),
        );
    }

    private function ensureAllowed(User $user): void
    {
        if (config('growth_ai.enabled') !== true) {
            throw new GrowthAiException(
                errorType: 'feature_disabled',
                userMessage: 'Prawdziwe AI jest wyłączone. Możesz kontynuować ręcznie.',
            );
        }

        if (! $user->isSuperAdmin()) {
            throw new GrowthAiException(
                errorType: 'forbidden',
                userMessage: 'Nie masz dostępu do tej funkcji.',
            );
        }
    }

    /**
     * @template TResult
     *
     * @param  array<string, mixed>  $input
     * @param  Closure(AiProviderResponse): TResult  $validate
     * @return TResult
     */
    private function run(User $user, GrowthAiTask $task, array $input, Closure $validate): mixed
    {
        $this->ensureCircuitIsClosed();
        $this->ensureDailyLimit($user);

        $response = null;

        try {
            $response = $this->provider->generateStructured(
                taskType: $task->type(),
                instructions: $task->instructions(),
                input: $input,
                schema: $task->schema(),
            );

            $result = $validate($response);
            $this->resetCircuit();
            $this->logInvocation($task, 'success', 'valid', $response);

            return $result;
        } catch (GrowthAiException $exception) {
            if ($this->shouldTripCircuit($exception->errorType)) {
                $this->recordFailure();
            }

            $this->logInvocation($task, 'failed', 'invalid', $response, $exception->errorType);

            throw $exception;
        } catch (Throwable $exception) {
            $this->recordFailure();
            $this->logInvocation($task, 'failed', 'not_completed', $response, 'unexpected_error');

            throw GrowthAiException::unavailable('unexpected_error', previous: $exception);
        }
    }

    private function shouldTripCircuit(string $errorType): bool
    {
        return in_array($errorType, [
            'connection_error',
            'provider_rate_limited',
            'provider_server_error',
            'provider_api_error',
            'http_client_error',
            'circuit_open',
            'unexpected_error',
        ], true);
    }

    private function ensureDailyLimit(User $user): void
    {
        $key = sprintf('growth-ai:daily:user:%s', $user->getAuthIdentifier());
        $limit = (int) config('growth_ai.limits.daily_per_user');

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            throw new GrowthAiException(
                errorType: 'daily_limit_reached',
                userMessage: 'Dzienny limit AI został wykorzystany. Możesz kontynuować ręcznie.',
            );
        }

        RateLimiter::hit($key, 86_400);
    }

    private function ensureCircuitIsClosed(): void
    {
        $openUntil = (int) Cache::get($this->circuitOpenKey(), 0);

        if ($openUntil > now()->timestamp) {
            throw GrowthAiException::unavailable('circuit_open');
        }

        if ($openUntil !== 0) {
            Cache::forget($this->circuitOpenKey());
            Cache::forget($this->circuitFailuresKey());
        }
    }

    private function recordFailure(): void
    {
        $key = $this->circuitFailuresKey();
        Cache::add($key, 0, (int) config('growth_ai.circuit.cooldown_seconds'));
        $failures = (int) Cache::increment($key);

        if ($failures >= (int) config('growth_ai.circuit.failure_threshold')) {
            Cache::put(
                $this->circuitOpenKey(),
                now()->addSeconds((int) config('growth_ai.circuit.cooldown_seconds'))->timestamp,
                (int) config('growth_ai.circuit.cooldown_seconds'),
            );
        }
    }

    private function resetCircuit(): void
    {
        Cache::forget($this->circuitFailuresKey());
        Cache::forget($this->circuitOpenKey());
    }

    private function circuitFailuresKey(): string
    {
        return 'growth-ai:circuit:'.$this->provider->name().':failures';
    }

    private function circuitOpenKey(): string
    {
        return 'growth-ai:circuit:'.$this->provider->name().':open-until';
    }

    private function logInvocation(
        GrowthAiTask $task,
        string $status,
        string $validationResult,
        ?AiProviderResponse $response = null,
        ?string $errorType = null,
    ): void {
        try {
            $inputTokens = $response?->inputTokens ?? 0;
            $outputTokens = $response?->outputTokens ?? 0;
            $estimatedCost = (
                $inputTokens * (float) config('growth_ai.cost.input_per_million')
                + $outputTokens * (float) config('growth_ai.cost.output_per_million')
            ) / 1_000_000;

            Log::channel((string) config('growth_ai.log_channel'))->info('Growth AI invocation', [
                'task_type' => $task->type(),
                'provider' => $response?->provider ?? $this->provider->name(),
                'model' => $response?->model ?? $this->provider->model(),
                'prompt_version' => $task->promptVersion(),
                'schema_version' => $task->schemaVersion(),
                'latency_ms' => $response?->latencyMs,
                'input_tokens' => $inputTokens,
                'output_tokens' => $outputTokens,
                'estimated_cost_usd' => round($estimatedCost, 6),
                'status' => $status,
                'validation_result' => $validationResult,
                'provider_request_id' => $response?->requestId,
                'error_type' => $errorType,
            ]);
        } catch (Throwable) {
            // A technical log failure must never overwrite a valid proposal or block manual work.
        }
    }
}
