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

    /**
     * Place real logo files on a finished image. The model never paints them.
     *
     * @param  list<array{bytes: string, corner: string}>  $logos
     */
    public function overlay(string $jpegBytes, array $logos): string
    {
        $canvas = @imagecreatefromstring($jpegBytes);
        if (! $canvas instanceof GdImage) {
            throw new GrowthAiException(
                errorType: 'invalid_image',
                userMessage: GraphicImageTask::INVALID_IMAGE_MESSAGE,
            );
        }

        imagealphablending($canvas, true);
        $width = imagesx($canvas);
        $height = imagesy($canvas);
        $margin = max(16, (int) round($width * 0.025));
        $maxWidth = (int) round($width * 0.2);
        $maxHeight = (int) round($height * 0.14);

        foreach ($logos as $logo) {
            $mark = $this->loadLogo($logo['bytes']);
            if (! $mark instanceof GdImage) {
                continue;
            }

            $scaled = $this->scaleLogo($mark, $maxWidth, $maxHeight);
            $logoWidth = imagesx($scaled);
            $logoHeight = imagesy($scaled);
            $pad = max(8, (int) round($logoWidth * 0.06));
            $boxWidth = $logoWidth + ($pad * 2);
            $boxHeight = $logoHeight + ($pad * 2);
            $x = ($logo['corner'] ?? 'left') === 'right'
                ? $width - $margin - $boxWidth
                : $margin;
            $y = $height - $margin - $boxHeight;
            $plate = imagecolorallocatealpha($canvas, 15, 23, 42, 38);
            imagefilledrectangle($canvas, $x, $y, $x + $boxWidth, $y + $boxHeight, $plate);
            imagecopy($canvas, $scaled, $x + $pad, $y + $pad, 0, 0, $logoWidth, $logoHeight);
        }

        return $this->encode($canvas);
    }

    /**
     * Store an uploaded logo as PNG, scaled down when it is larger than needed.
     */
    public function normalizeLogo(string $bytes): string
    {
        $image = $this->loadLogo($bytes);
        if (! $image instanceof GdImage) {
            throw new GrowthAiException(
                errorType: 'invalid_image',
                userMessage: 'Nie udało się odczytać pliku logo. Użyj PNG, JPG albo WebP.',
            );
        }

        $scaled = $this->scaleLogo($image, 800, 800);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        ob_start();
        imagepng($scaled);

        return (string) ob_get_clean();
    }

    private function loadLogo(string $bytes): ?GdImage
    {
        $image = @imagecreatefromstring($bytes);
        if (! $image instanceof GdImage) {
            return null;
        }

        if (! imageistruecolor($image)) {
            $trueColor = imagecreatetruecolor(imagesx($image), imagesy($image));
            imagealphablending($trueColor, false);
            imagesavealpha($trueColor, true);
            imagecopy($trueColor, $image, 0, 0, 0, 0, imagesx($image), imagesy($image));
            $image = $trueColor;
        }

        imagealphablending($image, false);
        imagesavealpha($image, true);

        $width = imagesx($image);
        $height = imagesy($image);
        $hasAlpha = false;
        for ($y = 0; $y < $height && ! $hasAlpha; $y += 3) {
            for ($x = 0; $x < $width; $x += 3) {
                if (((imagecolorat($image, $x, $y) >> 24) & 127) > 8) {
                    $hasAlpha = true;
                }
            }
        }

        if ($hasAlpha) {
            return $image;
        }

        $corners = [
            imagecolorat($image, 0, 0),
            imagecolorat($image, $width - 1, 0),
            imagecolorat($image, 0, $height - 1),
            imagecolorat($image, $width - 1, $height - 1),
        ];
        $background = $corners[0];
        $red = ($background >> 16) & 255;
        $green = ($background >> 8) & 255;
        $blue = $background & 255;
        foreach ($corners as $corner) {
            if (abs((($corner >> 16) & 255) - $red) > 12 || abs((($corner >> 8) & 255) - $green) > 12 || abs(($corner & 255) - $blue) > 12) {
                return $image;
            }
        }

        $transparent = imagecolorallocatealpha($image, 0, 0, 0, 127);
        for ($y = 0; $y < $height; $y++) {
            for ($x = 0; $x < $width; $x++) {
                $color = imagecolorat($image, $x, $y);
                if (abs((($color >> 16) & 255) - $red) < 28 && abs((($color >> 8) & 255) - $green) < 28 && abs(($color & 255) - $blue) < 28) {
                    imagesetpixel($image, $x, $y, $transparent);
                }
            }
        }

        return $image;
    }

    private function scaleLogo(GdImage $image, int $maxWidth, int $maxHeight): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $ratio = min($maxWidth / max(1, $width), $maxHeight / max(1, $height), 1);
        $targetWidth = max(1, (int) round($width * $ratio));
        $targetHeight = max(1, (int) round($height * $ratio));
        $scaled = imagecreatetruecolor($targetWidth, $targetHeight);
        imagealphablending($scaled, false);
        imagesavealpha($scaled, true);
        imagefilledrectangle($scaled, 0, 0, $targetWidth, $targetHeight, imagecolorallocatealpha($scaled, 0, 0, 0, 127));
        imagealphablending($scaled, true);
        imagecopyresampled($scaled, $image, 0, 0, 0, 0, $targetWidth, $targetHeight, $width, $height);

        return $scaled;
    }

    private function encode(GdImage $image): string
    {
        ob_start();
        imagejpeg($image, null, self::JPEG_QUALITY);

        return (string) ob_get_clean();
    }
}
