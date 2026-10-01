<?php

namespace App\Services\GrowthOS\AI\Contracts;

use App\Services\GrowthOS\AI\Data\AiImageResponse;

interface GrowthAiImageProvider
{
    public function name(): string;

    public function model(): string;

    /**
     * @param  string  $size  Provider size, e.g. "1536x1024".
     */
    public function generateImage(string $prompt, string $size, string $quality): AiImageResponse;

    /**
     * New image based on the source image (edit endpoint).
     */
    public function editImage(string $prompt, string $sourceBytes, string $sourceMime, string $size, string $quality): AiImageResponse;
}
