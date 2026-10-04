<?php

namespace App\Support\GrowthOS;

use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Support\GraphicImageProcessor;
use Illuminate\Support\Facades\Storage;

final class GraphicLogoStore
{
    public const PNE_PUBLIC = 'images/Logo nazwa PNE - white.png';

    public static function pneAvailable(): bool
    {
        return is_file(public_path(self::PNE_PUBLIC));
    }

    public static function sponsorPath(int $campaignId): string
    {
        return sprintf('growth-os/logos/%d/sponsor.png', $campaignId);
    }

    public static function hasSponsor(int $campaignId): bool
    {
        return Storage::disk(self::disk())->exists(self::sponsorPath($campaignId));
    }

    public static function storeSponsor(int $campaignId, string $bytes, GraphicImageProcessor $processor): void
    {
        $png = $processor->normalizeLogo($bytes);
        if (! Storage::disk(self::disk())->put(self::sponsorPath($campaignId), $png)) {
            throw new GrowthAiException(
                errorType: 'storage_error',
                userMessage: 'Nie udało się zapisać logo sponsora.',
            );
        }
    }

    public static function deleteSponsor(int $campaignId): void
    {
        Storage::disk(self::disk())->delete(self::sponsorPath($campaignId));
    }

    /**
     * @return list<array{bytes: string, corner: string}>
     */
    public static function forOverlay(int $campaignId, bool $pne, bool $sponsor): array
    {
        $logos = [];

        if ($pne && self::pneAvailable()) {
            $bytes = file_get_contents(public_path(self::PNE_PUBLIC));
            if ($bytes !== false && $bytes !== '') {
                $logos[] = ['bytes' => $bytes, 'corner' => 'left'];
            }
        }

        if ($sponsor && self::hasSponsor($campaignId)) {
            $bytes = Storage::disk(self::disk())->get(self::sponsorPath($campaignId));
            if ($bytes !== '') {
                $logos[] = ['bytes' => $bytes, 'corner' => 'right'];
            }
        }

        return $logos;
    }

    private static function disk(): string
    {
        return (string) config('growth_ai.images.disk');
    }
}
