<?php

namespace App\Services\GrowthOS\AI\Support;

use App\Models\GrowthAiPrompt;
use App\Services\GrowthOS\AI\Tasks\ConceptRevisionTask;
use App\Services\GrowthOS\AI\Tasks\DirectionPlanningTask;
use App\Services\GrowthOS\AI\Tasks\GraphicImageDescriptionTask;
use App\Services\GrowthOS\AI\Tasks\GraphicImageTask;
use App\Services\GrowthOS\AI\Tasks\MaterialDraftTask;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use Throwable;

/**
 * Prompty systemowe Growth OS. Kod trzyma wersję domyślną, zapis w bazie ją podmienia dla wszystkich projektów.
 */
final class GrowthAiPromptLibrary
{
    public const MAX_CHARS = 20000;

    private const CACHE_KEY = 'growth_ai_prompt_overrides';

    private const MEMO_KEY = 'growth_ai_prompt_override_memo';

    public static function has(string $key): bool
    {
        return array_key_exists($key, self::definitions());
    }

    public static function resolve(string $key, string $default): string
    {
        $override = self::overrides()[$key] ?? null;

        return is_string($override) && trim($override) !== '' ? $override : $default;
    }

    public static function save(string $key, string $body, ?int $userId): void
    {
        if (! self::has($key)) {
            throw new InvalidArgumentException('Unsupported Growth OS prompt.');
        }

        $body = trim($body);
        if ($body === trim(self::defaultBody($key))) {
            self::reset($key);

            return;
        }

        GrowthAiPrompt::query()->updateOrCreate(
            ['prompt_key' => $key],
            ['body' => $body, 'updated_by' => $userId],
        );
        self::forget();
    }

    public static function reset(string $key): void
    {
        GrowthAiPrompt::query()->where('prompt_key', $key)->delete();
        self::forget();
    }

    /**
     * @return list<array{id: string, label: string, prompts: list<array{key: string, label: string, version: string, body: string, customized: bool, search: bool, placeholders: string}>}>
     */
    public static function stages(): array
    {
        $overrides = self::overrides();
        $grouped = [];

        foreach (self::definitions() as $key => $meta) {
            $grouped[$meta['stage']]['id'] = $meta['stage'];
            $grouped[$meta['stage']]['label'] = $meta['stage_label'];
            $grouped[$meta['stage']]['prompts'][] = [
                'key' => $key,
                'label' => $meta['label'],
                'version' => $meta['version'],
                'body' => self::resolve($key, self::defaultBody($key)),
                'customized' => is_string($overrides[$key] ?? null) && trim((string) $overrides[$key]) !== '',
                'search' => $meta['search'],
                'placeholders' => $meta['placeholders'],
            ];
        }

        return array_values($grouped);
    }

    public static function defaultBody(string $key): string
    {
        return match ($key) {
            'direction-generate-search' => (new DirectionPlanningTask(DirectionPlanningTask::MODE_GENERATE, true))->defaultInstructions(),
            'direction-generate' => (new DirectionPlanningTask(DirectionPlanningTask::MODE_GENERATE, false))->defaultInstructions(),
            'direction-iterate-search' => (new DirectionPlanningTask(DirectionPlanningTask::MODE_ITERATE, true))->defaultInstructions(),
            'direction-iterate' => (new DirectionPlanningTask(DirectionPlanningTask::MODE_ITERATE, false))->defaultInstructions(),
            'concept-from-direction' => (new ConceptRevisionTask(true))->defaultInstructions(),
            'concept-revise' => (new ConceptRevisionTask(false))->defaultInstructions(),
            'material-youtube-description' => (new MaterialDraftTask(MaterialDraftTask::MATERIAL_KEY))->defaultInstructions(),
            'material-facebook-post' => (new MaterialDraftTask(MaterialDraftTask::FACEBOOK_MATERIAL_KEY))->defaultInstructions(),
            'material-main-graphic' => (new MaterialDraftTask(MaterialDraftTask::GRAPHIC_MATERIAL_KEY))->defaultInstructions(),
            'material-main-mail' => (new MaterialDraftTask(MaterialDraftTask::MAIL_MATERIAL_KEY))->defaultInstructions(),
            'material-reminder-mail' => (new MaterialDraftTask(MaterialDraftTask::REMINDER_MATERIAL_KEY))->defaultInstructions(),
            'material-landing' => (new MaterialDraftTask(MaterialDraftTask::LANDING_MATERIAL_KEY))->defaultInstructions(),
            'material-host-script' => (new MaterialDraftTask(MaterialDraftTask::HOST_SCRIPT_MATERIAL_KEY))->defaultInstructions(),
            'image-description' => (new GraphicImageDescriptionTask)->defaultInstructions(),
            'image-landscape-crop', 'image-landscape', 'image-square',
            'image-landscape-crop-headline', 'image-landscape-headline', 'image-square-headline',
            'image-adapt', 'image-adapt-headline', 'image-revise', 'image-revise-headline' => GraphicImageTask::defaultTemplate($key),
            default => throw new InvalidArgumentException('Unsupported Growth OS prompt.'),
        };
    }

    /**
     * @return array<string, string>
     */
    private static function overrides(): array
    {
        if (app()->bound(self::MEMO_KEY)) {
            $memo = app(self::MEMO_KEY);

            return is_array($memo) ? $memo : [];
        }

        try {
            if (! Schema::hasTable('growth_ai_prompts')) {
                $loaded = [];
            } else {
                $loaded = Cache::remember(self::CACHE_KEY, 60, function (): array {
                    return GrowthAiPrompt::query()->pluck('body', 'prompt_key')->all();
                });
            }
        } catch (Throwable) {
            $loaded = [];
        }

        app()->instance(self::MEMO_KEY, $loaded);

        return $loaded;
    }

    private static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
        app()->forgetInstance(self::MEMO_KEY);
    }

    /**
     * @return array<string, array{stage: string, stage_label: string, label: string, version: string, search: bool, placeholders: string}>
     */
    private static function definitions(): array
    {
        $conceptVersion = (new ConceptRevisionTask)->promptVersion();
        $imagePlaceholders = 'Miejsca na dane z karty: {{opis}}, {{naglowek}}, {{napis_terminu}}, {{uwaga}}, {{blok_opisu}}.';

        return [
            'direction-generate-search' => self::meta('direction', 'Pomysł i kierunek', 'Przygotuj od nowa, z wyszukiwaniem', DirectionPlanningTask::PROMPT_VERSION, true),
            'direction-generate' => self::meta('direction', 'Pomysł i kierunek', 'Przygotuj od nowa, bez wyszukiwania', DirectionPlanningTask::PROMPT_VERSION, false),
            'direction-iterate-search' => self::meta('direction', 'Pomysł i kierunek', 'Popraw propozycję, z wyszukiwaniem', DirectionPlanningTask::PROMPT_VERSION, true),
            'direction-iterate' => self::meta('direction', 'Pomysł i kierunek', 'Popraw propozycję, bez wyszukiwania', DirectionPlanningTask::PROMPT_VERSION, false),
            'concept-from-direction' => self::meta('concept', 'Koncepcja', 'Wygeneruj na podstawie pomysłu i kierunku', $conceptVersion, true),
            'concept-revise' => self::meta('concept', 'Koncepcja', 'Popraw zgodnie z sugestiami', $conceptVersion, true),
            'material-youtube-description' => self::meta('material-youtube-description', 'Opis YouTube', 'Szkic materiału', (new MaterialDraftTask(MaterialDraftTask::MATERIAL_KEY))->promptVersion(), true),
            'material-facebook-post' => self::meta('material-facebook-post', 'Post Facebook', 'Szkic materiału', (new MaterialDraftTask(MaterialDraftTask::FACEBOOK_MATERIAL_KEY))->promptVersion(), true),
            'material-main-graphic' => self::meta('material-main-graphic', 'Grafika główna', 'Szkic materiału', (new MaterialDraftTask(MaterialDraftTask::GRAPHIC_MATERIAL_KEY))->promptVersion(), true),
            'material-main-mail' => self::meta('material-main-mail', 'Mailing główny', 'Szkic materiału', (new MaterialDraftTask(MaterialDraftTask::MAIL_MATERIAL_KEY))->promptVersion(), true),
            'material-reminder-mail' => self::meta('material-reminder-mail', 'Mailing przypominający', 'Szkic materiału', (new MaterialDraftTask(MaterialDraftTask::REMINDER_MATERIAL_KEY))->promptVersion(), true),
            'material-landing' => self::meta('material-landing', 'Formularz zapisu / landing', 'Szkic materiału', (new MaterialDraftTask(MaterialDraftTask::LANDING_MATERIAL_KEY))->promptVersion(), true),
            'material-host-script' => self::meta('material-host-script', 'Scenariusz prowadzącego', 'Szkic materiału', (new MaterialDraftTask(MaterialDraftTask::HOST_SCRIPT_MATERIAL_KEY))->promptVersion(), true),
            'image-description' => self::meta('image-description', 'Grafika — opis obrazu', 'Opis dla modelu graficznego', GraphicImageDescriptionTask::PROMPT_VERSION, true),
            'image-landscape-crop' => self::meta('image-generation', 'Grafika — generowanie obrazu', 'Obraz poziomy, kadr przycinany, bez napisu', GraphicImageTask::PROMPT_VERSION, false, $imagePlaceholders),
            'image-landscape' => self::meta('image-generation', 'Grafika — generowanie obrazu', 'Obraz poziomy bez napisu', GraphicImageTask::PROMPT_VERSION, false, $imagePlaceholders),
            'image-square' => self::meta('image-generation', 'Grafika — generowanie obrazu', 'Obraz kwadratowy bez napisu', GraphicImageTask::PROMPT_VERSION, false, $imagePlaceholders),
            'image-landscape-crop-headline' => self::meta('image-generation', 'Grafika — generowanie obrazu', 'Obraz poziomy, kadr przycinany, z nagłówkiem', GraphicImageTask::PROMPT_VERSION, false, $imagePlaceholders),
            'image-landscape-headline' => self::meta('image-generation', 'Grafika — generowanie obrazu', 'Obraz poziomy z nagłówkiem', GraphicImageTask::PROMPT_VERSION, false, $imagePlaceholders),
            'image-square-headline' => self::meta('image-generation', 'Grafika — generowanie obrazu', 'Obraz kwadratowy z nagłówkiem', GraphicImageTask::PROMPT_VERSION, false, $imagePlaceholders),
            'image-adapt' => self::meta('image-generation', 'Grafika — generowanie obrazu', 'Wersja kwadratowa bez napisu', GraphicImageTask::ADAPT_PROMPT_VERSION, false, $imagePlaceholders),
            'image-adapt-headline' => self::meta('image-generation', 'Grafika — generowanie obrazu', 'Wersja kwadratowa z nagłówkiem', GraphicImageTask::ADAPT_PROMPT_VERSION, false, $imagePlaceholders),
            'image-revise' => self::meta('image-generation', 'Grafika — generowanie obrazu', 'Poprawka obrazu bez napisu', GraphicImageTask::REVISE_PROMPT_VERSION, false, $imagePlaceholders),
            'image-revise-headline' => self::meta('image-generation', 'Grafika — generowanie obrazu', 'Poprawka obrazu z nagłówkiem', GraphicImageTask::REVISE_PROMPT_VERSION, false, $imagePlaceholders),
        ];
    }

    /**
     * @return array{stage: string, stage_label: string, label: string, version: string, search: bool, placeholders: string}
     */
    private static function meta(string $stage, string $stageLabel, string $label, string $version, bool $search, string $placeholders = ''): array
    {
        return [
            'stage' => $stage,
            'stage_label' => $stageLabel,
            'label' => $label,
            'version' => $version,
            'search' => $search,
            'placeholders' => $placeholders,
        ];
    }
}
