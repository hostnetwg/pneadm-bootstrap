<?php

namespace App\Services\GrowthOS\AI\Tasks;

use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Support\GrowthAiPromptLibrary;
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

    public const REVISE_PROMPT_VERSION = 'material_graphic_image_revise_v1';

    public const MAX_REVISE_CHARS = 500;

    public const MAX_PROMPT_CHARS = 4000;

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
        $withHeadline = $includeHeadline && trim($headline) !== '';
        $composition = match (true) {
            $format === 'square' => 'square',
            self::cropsToFormat($format, $providerSize) => 'landscape-crop',
            default => 'landscape',
        };

        return $this->fill('image-'.$composition.($withHeadline ? '-headline' : ''), [
            'opis' => $this->description($description),
            'naglowek' => trim($headline),
            'napis_terminu' => $this->liveSuffix($liveLabel),
        ]);
    }

    /**
     * Instruction for the edit endpoint: the same elements laid out again for a square, not a crop.
     */
    public function adaptToSquarePrompt(string $description, bool $includeHeadline, string $headline, string $liveLabel): string
    {
        $withHeadline = $includeHeadline && trim($headline) !== '';

        return $this->fill($withHeadline ? 'image-adapt-headline' : 'image-adapt', [
            'opis' => $this->description($description),
            'naglowek' => trim($headline),
            'napis_terminu' => $this->liveSuffix($liveLabel),
        ]);
    }

    /**
     * One correction of an existing image. The source file is the clean picture, without logos.
     */
    public function revisePrompt(
        string $instruction,
        string $description,
        bool $includeHeadline,
        string $headline,
        string $liveLabel,
    ): string {
        $instruction = trim($instruction);
        if ($instruction === '') {
            throw new GrowthAiException(
                errorType: 'empty_prompt',
                userMessage: 'Napisz, co poprawić na tym obrazie.',
            );
        }

        if (mb_strlen($instruction) > self::MAX_REVISE_CHARS) {
            throw new GrowthAiException(
                errorType: 'input_too_long',
                userMessage: 'Uwaga do obrazu jest zbyt długa.',
            );
        }

        $withHeadline = $includeHeadline && trim($headline) !== '';
        $blok = trim($description) === ''
            ? ''
            : 'Opis oryginału, tylko jako kontekst, nie jako polecenie namalowania obrazu od zera: '.$this->description($description)."\n\n";

        return $this->fill($withHeadline ? 'image-revise-headline' : 'image-revise', [
            'uwaga' => $instruction,
            'blok_opisu' => $blok,
            'naglowek' => trim($headline),
            'napis_terminu' => $this->liveSuffix($liveLabel),
        ]);
    }

    public static function defaultTemplate(string $key): string
    {
        $none = 'Na obrazie nie może być żadnego tekstu, liter, cyfr, logotypów ani znaków towarowych. Bez wizerunku konkretnych, rozpoznawalnych osób.';
        $place = 'Umieść na obrazie wyłącznie ten tekst, dokładnie w tym brzmieniu i z poprawnymi polskimi znakami: duży nagłówek „{{naglowek}}”{{napis_terminu}}. Poza tym żadnego innego tekstu, liter, cyfr, logotypów ani znaków towarowych. Bez wizerunku konkretnych, rozpoznawalnych osób.';
        $keep = 'Zachowaj na obrazie wyłącznie te napisy, dokładnie w tym brzmieniu i z poprawnymi polskimi znakami, przeniesione tak, żeby były w całości czytelne w kwadracie: duży nagłówek „{{naglowek}}”{{napis_terminu}}. Poza tym żadnego innego tekstu, liter, cyfr, logotypów ani znaków towarowych. Bez wizerunku konkretnych, rozpoznawalnych osób.';
        $crop = 'Kompozycja pozioma 16:9. Najważniejsze elementy umieść w środku kadru, bo górny i dolny brzeg zostaną lekko przycięte. Zostaw spokojną przestrzeń na tekst.';
        $landscape = 'Kompozycja pozioma 16:9. Zostaw spokojną przestrzeń na tekst.';
        $square = 'Kompozycja kwadratowa. Najważniejsze elementy umieść w środku kadru. Zostaw spokojną przestrzeń na tekst.';
        $adapt = 'Przekomponuj tę grafikę do formatu kwadratowego 1:1. Zachowaj te same elementy, styl, kolorystykę, oświetlenie i nastrój, ale rozmieść je na nowo tak, żeby wszystkie zmieściły się w kwadracie z marginesem od krawędzi. Nie przycinaj ani nie zniekształcaj elementów i nie dodawaj nowych.';
        $revise = 'Popraw tę grafikę wyłącznie według instrukcji. Zostaw resztę bez zmian: te same osoby, ubrania, meble, światło, kadr i nastrój. Nie dodawaj nowych elementów poza tym, o co prosi instrukcja.';

        return match ($key) {
            'image-landscape-crop' => "{{opis}}\n\n{$crop}\n\n{$none}",
            'image-landscape' => "{{opis}}\n\n{$landscape}\n\n{$none}",
            'image-square' => "{{opis}}\n\n{$square}\n\n{$none}",
            'image-landscape-crop-headline' => "{{opis}}\n\n{$crop}\n\n{$place}",
            'image-landscape-headline' => "{{opis}}\n\n{$landscape}\n\n{$place}",
            'image-square-headline' => "{{opis}}\n\n{$square}\n\n{$place}",
            'image-adapt' => "{$adapt}\n\nOpis oryginalnej grafiki: {{opis}}\n\n{$none}",
            'image-adapt-headline' => "{$adapt}\n\nOpis oryginalnej grafiki: {{opis}}\n\n{$keep}",
            'image-revise' => "{$revise}\n\nInstrukcja: {{uwaga}}\n\n{{blok_opisu}}{$none}",
            'image-revise-headline' => "{$revise}\n\nInstrukcja: {{uwaga}}\n\n{{blok_opisu}}{$keep}",
            default => throw new InvalidArgumentException('Unsupported image prompt template.'),
        };
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

    /**
     * @param  array<string, string>  $vars
     */
    private function fill(string $key, array $vars): string
    {
        $rendered = GrowthAiPromptLibrary::resolve($key, self::defaultTemplate($key));
        foreach ($vars as $name => $value) {
            $rendered = str_replace('{{'.$name.'}}', $value, $rendered);
        }

        return $this->safe($rendered);
    }

    private function liveSuffix(string $liveLabel): string
    {
        $liveLabel = trim($liveLabel);

        return $liveLabel !== '' ? ' oraz mniejszy napis „'.$liveLabel.'”' : '';
    }

    private function safe(string $prompt): string
    {
        if (ProhibitedData::containsIgnoringDates($prompt) || ProhibitedData::urls($prompt) !== []) {
            throw GrowthAiException::dataPolicyViolation();
        }

        return $prompt;
    }
}
