<?php

namespace App\Services\GrowthOS\AI;

use App\Models\GrowthOS\GrowthArtifact;
use App\Models\GrowthOS\GrowthArtifactImage;
use App\Models\GrowthOS\GrowthCampaign;
use App\Models\User;
use App\Services\GrowthOS\AI\Contracts\GrowthAiImageProvider;
use App\Services\GrowthOS\AI\Data\AiImageResponse;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Support\GraphicImageProcessor;
use App\Services\GrowthOS\AI\Tasks\GraphicImageTask;
use App\Support\GrowthOS\GraphicLogoStore;
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
        bool $includePneLogo = false,
        bool $includeSponsorLogo = false,
    ): GrowthArtifactImage {
        $size = GraphicImageTask::providerSize($format, $this->provider->model());
        $prompt = $this->task->prompt($description, $format, $includeHeadline, $headline, $liveLabel, $size);
        $withHeadline = $includeHeadline && trim($headline) !== '';
        $logos = $this->logos($artifact, $includePneLogo, $includeSponsorLogo);

        return $this->run($user, $artifact, [
            'format' => $format,
            'prompt' => trim($description),
            'include_headline' => $withHeadline,
            'overlay_headline' => $withHeadline ? mb_substr(trim($headline), 0, 180) : null,
            'overlay_date' => $withHeadline && trim($liveLabel) !== '' ? mb_substr(trim($liveLabel), 0, 80) : null,
            'include_pne_logo' => $this->hasCorner($logos, 'left'),
            'include_sponsor_logo' => $this->hasCorner($logos, 'right'),
            'prompt_version' => GraphicImageTask::PROMPT_VERSION,
        ], $format, $size, fn (string $quality): AiImageResponse => $this->provider->generateImage($prompt, $size, $quality), $logos);
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
        $logos = $this->logos($artifact, $source->include_pne_logo, $source->include_sponsor_logo);
        $cleanPath = $source->base_path ?: $source->path;

        return $this->run($user, $artifact, [
            'source_image_id' => $source->id,
            'format' => 'square',
            'prompt' => $source->prompt,
            'include_headline' => $source->include_headline,
            'overlay_headline' => $source->overlay_headline,
            'overlay_date' => $source->overlay_date,
            'include_pne_logo' => $this->hasCorner($logos, 'left'),
            'include_sponsor_logo' => $this->hasCorner($logos, 'right'),
            'prompt_version' => GraphicImageTask::ADAPT_PROMPT_VERSION,
        ], 'square_from_landscape', $size, fn (string $quality): AiImageResponse => $this->provider->editImage(
            $prompt,
            (string) $disk->get($cleanPath),
            $source->mime,
            $size,
            $quality,
        ), $logos);
    }

    /**
     * One correction of an existing image. Logos are put back afterwards, from the clean file.
     */
    public function revise(User $user, GrowthArtifactImage $source, string $instruction): GrowthArtifactImage
    {
        $disk = Storage::disk($source->disk);
        $cleanPath = $source->base_path ?: $source->path;
        if (! $disk->exists($cleanPath)) {
            throw new GrowthAiException(
                errorType: 'missing_source_image',
                userMessage: 'Nie znaleziono pliku obrazu do poprawki.',
            );
        }

        $artifact = $source->artifact;
        $size = GraphicImageTask::providerSize($source->format, $this->provider->model());
        $prompt = $this->task->revisePrompt(
            $instruction,
            (string) $source->prompt,
            $source->include_headline,
            (string) $source->overlay_headline,
            (string) $source->overlay_date,
        );
        $logos = $this->logos($artifact, $source->include_pne_logo, $source->include_sponsor_logo);

        return $this->run($user, $artifact, [
            'source_image_id' => $source->id,
            'format' => $source->format,
            'prompt' => $source->prompt,
            'include_headline' => $source->include_headline,
            'overlay_headline' => $source->overlay_headline,
            'overlay_date' => $source->overlay_date,
            'include_pne_logo' => $this->hasCorner($logos, 'left'),
            'include_sponsor_logo' => $this->hasCorner($logos, 'right'),
            'prompt_version' => GraphicImageTask::REVISE_PROMPT_VERSION,
        ], 'revise_'.$source->format, $size, fn (string $quality): AiImageResponse => $this->provider->editImage(
            $prompt,
            (string) $disk->get($cleanPath),
            $source->mime,
            $size,
            $quality,
        ), $logos);
    }

    /**
     * With the AI flag off the image is a local placeholder and no provider is called.
     *
     * @param  array<string, mixed>  $attributes
     * @param  Closure(string): AiImageResponse  $call
     * @param  list<array{bytes: string, corner: string}>  $logos
     */
    private function run(User $user, GrowthArtifact $artifact, array $attributes, string $kind, string $size, Closure $call, array $logos = []): GrowthArtifactImage
    {
        $target = GraphicImageTask::format((string) $attributes['format']);

        if (config('growth_ai.enabled') !== true) {
            return $this->store($user, $artifact, $attributes + [
                'source' => GrowthArtifactImage::SOURCE_SIMULATION,
                'provider' => null,
                'model' => null,
                'quality' => null,
            ], $this->processor->placeholder($target['width'], $target['height']), $logos);
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
            ], $this->processor->fit($response->bytes, $target['width'], $target['height']), $logos);
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
        $disk = Storage::disk($image->disk);
        $paths = array_values(array_filter([$image->path, $image->base_path]));
        $image->delete();
        $disk->delete($paths);
    }

    public function deleteForCampaign(GrowthCampaign $campaign): void
    {
        $images = GrowthArtifactImage::query()
            ->whereIn('growth_artifact_id', $campaign->artifacts()->select('id'))
            ->orderByDesc('id')
            ->get();

        foreach ($images as $image) {
            $this->delete($image);
        }
    }

    /**
     * @param  array<string, mixed>  $attributes
     * @param  list<array{bytes: string, corner: string}>  $logos
     */
    private function store(User $user, GrowthArtifact $artifact, array $attributes, string $bytes, array $logos = []): GrowthArtifactImage
    {
        $target = GraphicImageTask::format((string) $attributes['format']);
        $disk = (string) config('growth_ai.images.disk');
        $name = (string) Str::uuid();
        $path = sprintf('growth-os/images/%d/%s.jpg', $artifact->id, $name);
        $basePath = $logos === [] ? null : sprintf('growth-os/images/%d/%s.base.jpg', $artifact->id, $name);
        $final = $logos === [] ? $bytes : $this->processor->overlay($bytes, $logos);
        $storage = Storage::disk($disk);

        if (($basePath !== null && ! $storage->put($basePath, $bytes)) || ! $storage->put($path, $final)) {
            $storage->delete(array_filter([$path, $basePath]));

            throw GrowthAiException::unavailable('storage_error', retryable: false);
        }

        try {
            $image = $artifact->images()->create($attributes + [
                'width' => $target['width'],
                'height' => $target['height'],
                'disk' => $disk,
                'path' => $path,
                'base_path' => $basePath,
                'mime' => GraphicImageProcessor::MIME,
                'size_bytes' => strlen($final),
                'created_by_user_id' => $user->id,
            ]);
        } catch (Throwable $exception) {
            $storage->delete(array_filter([$path, $basePath]));

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

    /**
     * @param  list<array{bytes: string, corner: string}>  $logos
     */
    private function hasCorner(array $logos, string $corner): bool
    {
        foreach ($logos as $logo) {
            if (($logo['corner'] ?? '') === $corner) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return list<array{bytes: string, corner: string}>
     */
    private function logos(GrowthArtifact $artifact, bool $pne, bool $sponsor): array
    {
        return GraphicLogoStore::forOverlay((int) $artifact->growth_campaign_id, $pne, $sponsor);
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
