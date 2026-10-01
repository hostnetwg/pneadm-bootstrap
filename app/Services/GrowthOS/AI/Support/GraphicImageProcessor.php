<?php

namespace App\Services\GrowthOS\AI\Support;

use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Tasks\GraphicImageTask;
use GdImage;

final class GraphicImageProcessor
{
    public const MIME = 'image/jpeg';

    private const JPEG_QUALITY = 90;

    /**
     * Center-crop to the target aspect ratio, scale to the exact size and encode as JPEG.
     */
    public function fit(string $bytes, int $width, int $height): string
    {
        $source = @imagecreatefromstring($bytes);
        if (! $source instanceof GdImage) {
            throw new GrowthAiException(
                errorType: 'invalid_image',
                userMessage: GraphicImageTask::INVALID_IMAGE_MESSAGE,
            );
        }

        $sourceWidth = imagesx($source);
        $sourceHeight = imagesy($source);
        $targetRatio = $width / $height;

        if ($sourceWidth / $sourceHeight > $targetRatio) {
            $cropHeight = $sourceHeight;
            $cropWidth = (int) round($sourceHeight * $targetRatio);
        } else {
            $cropWidth = $sourceWidth;
            $cropHeight = (int) round($sourceWidth / $targetRatio);
        }

        $target = imagecreatetruecolor($width, $height);
        imagecopyresampled(
            $target,
            $source,
            0,
            0,
            intdiv($sourceWidth - $cropWidth, 2),
            intdiv($sourceHeight - $cropHeight, 2),
            $width,
            $height,
            $cropWidth,
            $cropHeight,
        );

        return $this->encode($target);
    }

    /**
     * Local simulation image; never sent anywhere.
     */
    public function placeholder(int $width, int $height): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, (int) imagecolorallocate($image, 31, 58, 95));

        $accent = (int) imagecolorallocate($image, 240, 140, 60);
        imagefilledrectangle($image, 0, $height - 24, $width, $height, $accent);

        $white = (int) imagecolorallocate($image, 255, 255, 255);
        $lines = ['SYMULACJA LOKALNA', 'bez wywolania OpenAI', $width.' x '.$height];
        foreach ($lines as $index => $line) {
            $x = (int) (($width - imagefontwidth(5) * strlen($line)) / 2);
            $y = (int) ($height / 2 - 30 + $index * 24);
            imagestring($image, 5, $x, $y, $line, $white);
        }

        return $this->encode($image);
    }

    private function encode(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);

        return (string) ob_get_clean();
    }
}
