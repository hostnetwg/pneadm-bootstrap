<?php

namespace App\Services\GrowthOS\AI;

use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthArtifactImage;
use App\Models\User;
use App\Services\GrowthOS\AI\Contracts\GrowthAiImageProvider;
use App\Services\GrowthOS\AI\Data\AiImageResponse;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Support\GraphicImageProcessor;
use App\Services\GrowthOS\AI\Tasks\GraphicImageTask;
use Closure;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

final class GrowthImageService
{
    public const DAILY_LIMIT_MESSAGE = 'Dzienny limit obrazów AI został wykorzystany. Możesz go wyzerować przyciskiem „Zresetuj limit” w generatorze obrazu.';

    public function __construct(
        private readonly GrowthAiImageProvider $provider,
        private readonly GraphicImageTask $task,
        private readonly GraphicImageProcessor $processor,
    ) {}

    /**
     * With the AI flag off the image is a local placeholder and no provider is called.
     */
    public function generate(
        User $user,
        GrowthArtifact $artifact,
        string $format,
        string $description,
        bool $includeHeadline,
        string $headline,
        string $liveLabel,
    ): GrowthArtifactImage {
        $size = GraphicImageTask::providerSize($format, $this->provider->model());
        $prompt = $this->task->prompt($description, $format, $includeHeadline, $headline, $liveLabel, $size);
        $withHeadline = $includeHeadline && trim($headline) !== '';

        return $this->run($user, $artifact, [
            'format' => $format,
            'prompt' => trim($description),
            'include_headline' => $withHeadline,
            'overlay_headline' => $withHeadline ? mb_substr(trim($headline), 0, 180) : null,
            'overlay_date' => $withHeadline && trim($liveLabel) !== '' ? mb_substr(trim($liveLabel), 0, 80) : null,
            'prompt_version' => GraphicImageTask::PROMPT_VERSION,
        ], $format, $size, fn (string $quality): AiImageResponse => $this->provider->generateImage($prompt, $size, $quality));
    }

    /**
     * Square version of a landscape image: the same elements laid out again through the edit endpoint, not a crop.
     * The text on the image (if any) is the one stored with the source, so a later brief change does not leak in.
     */
    public function adaptToSquare(User $user, GrowthArtifactImage $source): GrowthArtifactImage
    {
        if ($source->format !== 'landscape') {
            throw new GrowthAiException(
                errorType: 'invalid_source_image',
                userMessage: 'Wersję kwadratową można utworzyć tylko z obrazu poziomego.',
            );
        }

        $disk = Storage::disk($source->disk);
        if (! $disk->exists($source->path)) {
            throw new GrowthAiException(
                errorType: 'missing_source_image',
                userMessage: 'Nie znaleziono pliku obrazu poziomego.',
            );
        }

        $artifact = $source->artifact;
        $size = GraphicImageTask::providerSize('square', $this->provider->model());
        $prompt = $this->task->adaptToSquarePrompt(
            $source->prompt,
            $source->include_headline,
            (string) $source->overlay_headline,
            (string) $source->overlay_date,
        );

        return $this->run($user, $artifact, [
            'source_image_id' => $source->id,
            'format' => 'square',
            'prompt' => $source->prompt,
            'include_headline' => $source->include_headline,
            'overlay_headline' => $source->overlay_headline,
            'overlay_date' => $source->overlay_date,
            'prompt_version' => GraphicImageTask::ADAPT_PROMPT_VERSION,
        ], 'square_from_landscape', $size, fn (string $quality): AiImageResponse => $this->provider->editImage(
            $prompt,
            (string) $disk->get($source->path),
            $source->mime,
            $size,
            $quality,
        ));
    }

    /**
     * With the AI flag off the image is a local placeholder and no provider is called.
     *
     * @param  array<string, mixed>  $attributes
     * @param  Closure(string): AiImageResponse  $call
     */
    private function run(User $user, GrowthArtifact $artifact, array $attributes, string $kind, string $size, Closure $call): GrowthArtifactImage
    {
        $target = GraphicImageTask::format((string) $attributes['format']);

        if (config('growth_ai.enabled') !== true) {
            return $this->store($user, $artifact, $attributes + [
                'source' => GrowthArtifactImage::SOURCE_SIMULATION,
                'provider' => null,
                'model' => null,
                'quality' => null,
            ], $this->processor->placeholder($target['width'], $target['height']));
        }

        if (! $user->isSuperAdmin()) {
            throw new GrowthAiException(
                errorType: 'forbidden',
                userMessage: 'Nie masz dostępu do tej funkcji.',
            );
        }

        $this->ensureCircuitIsClosed();
        $this->ensureDailyLimit($user);

        $quality = (string) config('growth_ai.images.quality');
        $response = null;

        try {
            $response = $call($quality);
            $image = $this->store($user, $artifact, $attributes + [
                'source' => GrowthArtifactImage::SOURCE_OPENAI,
                'provider' => $response->provider,
                'model' => $response->model,
                'quality' => $quality,
            ], $this->processor->fit($response->bytes, $target['width'], $target['height']));
            $this->resetCircuit();
            $this->logInvocation($kind, $size, $quality, 'success', $response);

            return $image;
        } catch (GrowthAiException $exception) {
            if ($this->shouldTripCircuit($exception->errorType)) {
                $this->recordFailure();
            }
            $this->logInvocation($kind, $size, $quality, 'failed', $response, $exception->errorType);

            throw $exception;
        } catch (Throwable $exception) {
            $this->recordFailure();
            $this->logInvocation($kind, $size, $quality, 'failed', $response, 'unexpected_error');

            throw GrowthAiException::unavailable('unexpected_error', previous: $exception);
        }
    }

    public function select(GrowthArtifactImage $image): void
    {
        DB::transaction(function () use ($image): void {
            GrowthArtifactImage::query()
                ->where('growth_artifact_id', $image->growth_artifact_id)
                ->update(['is_selected' => false]);
            $image->forceFill(['is_selected' => true])->save();
        });
    }

    public function delete(GrowthArtifactImage $image): void
    {
        $image->delete();
        Storage::disk($image->disk)->delete($image->path);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function store(User $user, GrowthArtifact $artifact, array $attributes, string $bytes): GrowthArtifactImage
    {
        $target = GraphicImageTask::format((string) $attributes['format']);
        $disk = (string) config('growth_ai.images.disk');
        $path = sprintf('growth-os/images/%d/%s.jpg', $artifact->id, Str::uuid());

        if (! Storage::disk($disk)->put($path, $bytes)) {
            throw GrowthAiException::unavailable('storage_error', retryable: false);
        }

        try {
            $image = $artifact->images()->create($attributes + [
                'width' => $target['width'],
                'height' => $target['height'],
                'disk' => $disk,
                'path' => $path,
                'mime' => GraphicImageProcessor::MIME,
                'size_bytes' => strlen($bytes),
                'created_by_user_id' => $user->id,
            ]);
        } catch (Throwable $exception) {
            Storage::disk($disk)->delete($path);

            throw $exception;
        }

        $this->prune($artifact);

        return $image;
    }

    private function prune(GrowthArtifact $artifact): void
    {
        $artifact->images()
            ->orderByDesc('is_selected')
            ->orderByDesc('id')
            ->skip(GrowthArtifactImage::KEEP_LATEST)
            ->take(PHP_INT_MAX)
            ->get()
            ->each(fn (GrowthArtifactImage $stale) => $this->delete($stale));
    }

    public function dailyUsage(User $user): int
    {
        return RateLimiter::attempts($this->dailyLimitKey($user));
    }

    public function resetDailyLimit(User $user): void
    {
        $used = $this->dailyUsage($user);
        RateLimiter::clear($this->dailyLimitKey($user));

        Log::channel((string) config('growth_ai.log_channel'))->info('Growth AI image daily limit reset', [
            'task_type' => GraphicImageTask::TYPE,
            'user_id' => $user->getAuthIdentifier(),
            'used_before_reset' => $used,
        ]);
    }

    private function dailyLimitKey(User $user): string
    {
        return sprintf('growth-ai:images:daily:user:%s', $user->getAuthIdentifier());
    }

    private function ensureDailyLimit(User $user): void
    {
        $key = $this->dailyLimitKey($user);

        if (RateLimiter::tooManyAttempts($key, (int) config('growth_ai.images.daily_per_user'))) {
            throw new GrowthAiException(
                errorType: 'daily_limit_reached',
                userMessage: self::DAILY_LIMIT_MESSAGE,
            );
        }

        RateLimiter::hit($key, 86_400);
    }

    private function shouldTripCircuit(string $errorType): bool
    {
        return in_array($errorType, [
            'connection_error',
            'provider_rate_limited',
            'provider_server_error',
            'provider_api_error',
            'http_client_error',
            'unexpected_error',
        ], true);
    }

    private function ensureCircuitIsClosed(): void
    {
        $openUntil = (int) Cache::get($this->circuitOpenKey(), 0);

        if ($openUntil > now()->timestamp) {
            throw GrowthAiException::unavailable('circuit_open');
        }

        if ($openUntil !== 0) {
            $this->resetCircuit();
        }
    }

    private function recordFailure(): void
    {
        $cooldown = (int) config('growth_ai.circuit.cooldown_seconds');
        Cache::add($this->circuitFailuresKey(), 0, $cooldown);
        $failures = (int) Cache::increment($this->circuitFailuresKey());

        if ($failures >= (int) config('growth_ai.circuit.failure_threshold')) {
            Cache::put($this->circuitOpenKey(), now()->addSeconds($cooldown)->timestamp, $cooldown);
        }
    }

    private function resetCircuit(): void
    {
        Cache::forget($this->circuitFailuresKey());
        Cache::forget($this->circuitOpenKey());
    }

    private function circuitFailuresKey(): string
    {
        return 'growth-ai:circuit:'.$this->provider->name().'-images:failures';
    }

    private function circuitOpenKey(): string
    {
        return 'growth-ai:circuit:'.$this->provider->name().'-images:open-until';
    }

    private function logInvocation(
        string $kind,
        string $size,
        string $quality,
        string $status,
        ?AiImageResponse $response = null,
        ?string $errorType = null,
    ): void {
        try {
            Log::channel((string) config('growth_ai.log_channel'))->info('Growth AI invocation', [
                'task_type' => GraphicImageTask::TYPE,
                'image_kind' => $kind,
                'provider' => $response?->provider ?? $this->provider->name(),
                'model' => $response?->model ?? $this->provider->model(),
                'prompt_version' => $kind === 'square_from_landscape'
                    ? GraphicImageTask::ADAPT_PROMPT_VERSION
                    : GraphicImageTask::PROMPT_VERSION,
                'size' => $size,
                'quality' => $quality,
                'latency_ms' => $response?->latencyMs,
                'estimated_cost_usd' => $response !== null
                    ? (float) config('growth_ai.images.cost_per_image.'.$kind, 0)
                    : 0.0,
                'status' => $status,
                'provider_request_id' => $response?->requestId,
                'error_type' => $errorType,
            ]);
        } catch (Throwable) {
            // A technical log failure must never block the image or manual work.
        }
    }
}
