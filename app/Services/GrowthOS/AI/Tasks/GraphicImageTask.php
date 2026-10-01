<?php

namespace App\Services\GrowthOS\AI\Tasks;

use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Support\ProhibitedData;
use InvalidArgumentException;

/**
 * Obraz grafiki głównej (DEC-029, DEC-030). Aplikacja dokleja do opisu właściciela stałe zasady
 * kompozycji i tekstu; nagłówek i termin pochodzą z aplikacji, nigdy z modelu.
 */
final class GraphicImageTask
{
    public const TYPE = 'material_image';

    public const MATERIAL_KEY = MaterialDraftTask::GRAPHIC_MATERIAL_KEY;

    public const PROMPT_VERSION = 'material_graphic_image_v2';

    public const ADAPT_PROMPT_VERSION = 'material_graphic_square_adapt_v1';

    public const MAX_PROMPT_CHARS = 2000;

    public const INVALID_IMAGE_MESSAGE = 'Nie udało się przygotować poprawnego obrazu. Spróbuj ponownie lub zmień opis.';

    /**
     * @var array<string, array{label: string, width: int, height: int}>
     */
    public const FORMATS = [
        'landscape' => [
            'label' => 'Poziomy 16:9',
            'width' => 1920,
            'height' => 1080,
        ],
        'square' => [
            'label' => 'Kwadrat',
            'width' => 1080,
            'height' => 1080,
        ],
    ];

    /**
     * gpt-image-2 accepts any size with sides divisible by 16; older GPT image models only 1024x1024 and 1536x1024.
     *
     * @var array<string, array<string, string>>
     */
    private const PROVIDER_SIZES = [
        'flexible' => ['landscape' => '2048x1152', 'square' => '1024x1024'],
        'legacy' => ['landscape' => '1536x1024', 'square' => '1024x1024'],
    ];

    /**
     * @return array{label: string, width: int, height: int}
     */
    public static function format(string $format): array
    {
        if (! array_key_exists($format, self::FORMATS)) {
            throw new InvalidArgumentException('Unsupported image format.');
        }

        return self::FORMATS[$format];
    }

    public static function providerSize(string $format, string $model): string
    {
        self::format($format);

        return self::PROVIDER_SIZES[str_starts_with($model, 'gpt-image-2') ? 'flexible' : 'legacy'][$format];
    }

    public static function cropsToFormat(string $format, string $providerSize): bool
    {
        $target = self::format($format);
        [$width, $height] = array_map('intval', explode('x', $providerSize));

        return $width * $target['height'] !== $height * $target['width'];
    }

    public function prompt(
        string $description,
        string $format,
        bool $includeHeadline,
        string $headline,
        string $liveLabel,
        string $providerSize,
    ): string {
        $description = $this->description($description);

        $composition = match (true) {
            $format === 'square' => 'Kompozycja kwadratowa. Najważniejsze elementy umieść w środku kadru. Zostaw spokojną przestrzeń na tekst.',
            self::cropsToFormat($format, $providerSize) => 'Kompozycja pozioma 16:9. Najważniejsze elementy umieść w środku kadru, bo górny i dolny brzeg zostaną lekko przycięte. Zostaw spokojną przestrzeń na tekst.',
            default => 'Kompozycja pozioma 16:9. Zostaw spokojną przestrzeń na tekst.',
        };

        return $this->safe(implode("\n\n", [
            $description,
            $composition,
            $this->textRule($includeHeadline, $headline, $liveLabel, false),
        ]));
    }

    /**
     * Instruction for the edit endpoint: the same elements laid out again for a square, not a crop.
     */
    public function adaptToSquarePrompt(string $description, bool $includeHeadline, string $headline, string $liveLabel): string
    {
        return $this->safe(implode("\n\n", [
            'Przekomponuj tę grafikę do formatu kwadratowego 1:1. Zachowaj te same elementy, styl, kolorystykę, oświetlenie i nastrój, ale rozmieść je na nowo tak, żeby wszystkie zmieściły się w kwadracie z marginesem od krawędzi. Nie przycinaj ani nie zniekształcaj elementów i nie dodawaj nowych.',
            'Opis oryginalnej grafiki: '.$this->description($description),
            $this->textRule($includeHeadline, $headline, $liveLabel, true),
        ]));
    }

    private function description(string $description): string
    {
        $description = trim($description);

        if ($description === '') {
            throw new GrowthAiException(
                errorType: 'empty_prompt',
                userMessage: 'Wpisz opis obrazu.',
            );
        }

        if (mb_strlen($description) > self::MAX_PROMPT_CHARS) {
            throw new GrowthAiException(
                errorType: 'input_too_long',
                userMessage: 'Opis obrazu jest zbyt długi.',
            );
        }

        return $description;
    }

    private function textRule(bool $includeHeadline, string $headline, string $liveLabel, bool $moveExisting): string
    {
        $headline = trim($headline);
        $liveLabel = trim($liveLabel);

        if (! $includeHeadline || $headline === '') {
            return 'Na obrazie nie może być żadnego tekstu, liter, cyfr, logotypów ani znaków towarowych. Bez wizerunku konkretnych, rozpoznawalnych osób.';
        }

        $texts = 'duży nagłówek „'.$headline.'”'.($liveLabel !== '' ? ' oraz mniejszy napis „'.$liveLabel.'”' : '');
        $lead = $moveExisting
            ? 'Zachowaj na obrazie wyłącznie te napisy, dokładnie w tym brzmieniu i z poprawnymi polskimi znakami, przeniesione tak, żeby były w całości czytelne w kwadracie: '
            : 'Umieść na obrazie wyłącznie ten tekst, dokładnie w tym brzmieniu i z poprawnymi polskimi znakami: ';

        return $lead.$texts.'. Poza tym żadnego innego tekstu, liter, cyfr, logotypów ani znaków towarowych. Bez wizerunku konkretnych, rozpoznawalnych osób.';
    }

    private function safe(string $prompt): string
    {
        if (ProhibitedData::containsIgnoringDates($prompt) || ProhibitedData::urls($prompt) !== []) {
            throw GrowthAiException::dataPolicyViolation();
        }

        return $prompt;
    }
}
