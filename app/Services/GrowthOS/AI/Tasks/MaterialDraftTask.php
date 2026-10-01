<?php

namespace App\Services\GrowthOS\AI\Tasks;

use App\Services\GrowthOS\AI\Contracts\GrowthAiTask;
use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Data\MaterialDraftResult;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Support\ProhibitedData;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Szkic materiału webinaru. Profile: opis YouTube (DEC-024), post Facebook (DEC-026) i brief grafiki głównej (DEC-028).
 */
final class MaterialDraftTask implements GrowthAiTask
{
    public const TYPE = 'material_draft';

    public const MATERIAL_KEY = 'youtube-description';

    public const PROFILE = 'youtube_description_v1';

    public const PROMPT_VERSION = 'material_youtube_description_v2';

    public const SCHEMA_VERSION = 'material_youtube_description_schema_v1';

    public const MAX_DRAFT_CHARS = 5000;

    public const FACEBOOK_MATERIAL_KEY = 'facebook-post';

    public const FACEBOOK_PROFILE = 'facebook_post_v1';

    public const FACEBOOK_PROMPT_VERSION = 'material_facebook_post_v1';

    public const FACEBOOK_SCHEMA_VERSION = 'material_facebook_post_schema_v1';

    public const FACEBOOK_MAX_DRAFT_CHARS = 1500;

    public const LINK_PLACEHOLDER = '[LINK DO ZAPISU]';

    public const GRAPHIC_MATERIAL_KEY = 'main-graphic';

    public const GRAPHIC_PROFILE = 'graphic_brief_v1';

    public const GRAPHIC_PROMPT_VERSION = 'material_graphic_brief_v2';

    public const GRAPHIC_SCHEMA_VERSION = 'material_graphic_brief_schema_v1';

    public const GRAPHIC_FORMATS = '16:9 (1920×1080) i kwadrat (1080×1080)';

    /**
     * Optional brief elements with their labels. Headline, date and visual direction are always included.
     *
     * @var array<string, string>
     */
    public const GRAPHIC_OPTIONAL_ELEMENTS = [
        'subtitle' => 'Podtytuł',
        'host' => 'Prowadzący',
        'cta' => 'Wezwanie do działania',
        'image_prompt' => 'Opis obrazu dla AI',
        'alt_text' => 'Tekst alternatywny (alt)',
    ];

    /**
     * @var array<string, int>
     */
    private const GRAPHIC_FIELD_LIMITS = [
        'headline' => 80,
        'subtitle' => 160,
        'cta' => 40,
        'visual_direction' => 800,
        'image_prompt' => 1200,
        'alt_text' => 300,
        'change_summary' => 1000,
    ];

    /**
     * @var array<string, array{profile: string, name: string, type: string, prompt_version: string, schema_version: string, max_chars: int}>
     */
    private const PROFILES = [
        self::MATERIAL_KEY => [
            'profile' => self::PROFILE,
            'name' => 'Opis YouTube',
            'type' => 'youtube_description',
            'prompt_version' => self::PROMPT_VERSION,
            'schema_version' => self::SCHEMA_VERSION,
            'max_chars' => self::MAX_DRAFT_CHARS,
        ],
        self::FACEBOOK_MATERIAL_KEY => [
            'profile' => self::FACEBOOK_PROFILE,
            'name' => 'Post Facebook',
            'type' => 'facebook_post',
            'prompt_version' => self::FACEBOOK_PROMPT_VERSION,
            'schema_version' => self::FACEBOOK_SCHEMA_VERSION,
            'max_chars' => self::FACEBOOK_MAX_DRAFT_CHARS,
        ],
        self::GRAPHIC_MATERIAL_KEY => [
            'profile' => self::GRAPHIC_PROFILE,
            'name' => 'Grafika główna',
            'type' => 'graphic_brief',
            'prompt_version' => self::GRAPHIC_PROMPT_VERSION,
            'schema_version' => self::GRAPHIC_SCHEMA_VERSION,
            'max_chars' => self::MAX_DRAFT_CHARS,
        ],
    ];

    public function __construct(private readonly string $materialKey = self::MATERIAL_KEY)
    {
        if (! self::supports($materialKey)) {
            throw new InvalidArgumentException('Unsupported material draft profile.');
        }
    }

    public static function supports(string $materialKey): bool
    {
        return array_key_exists($materialKey, self::PROFILES);
    }

    /**
     * Materials that receive the approved YouTube description as a source (DEC-026, DEC-029).
     */
    public static function usesYoutubeSource(string $materialKey): bool
    {
        return in_array($materialKey, [self::FACEBOOK_MATERIAL_KEY, self::GRAPHIC_MATERIAL_KEY], true);
    }

    public function forMaterial(string $materialKey): self
    {
        return new self($materialKey);
    }

    public function materialKey(): string
    {
        return $this->materialKey;
    }

    public function type(): string
    {
        return self::TYPE;
    }

    public function promptVersion(): string
    {
        return self::PROFILES[$this->materialKey]['prompt_version'];
    }

    public function schemaVersion(): string
    {
        return self::PROFILES[$this->materialKey]['schema_version'];
    }

    public function instructions(): string
    {
        return match ($this->materialKey) {
            self::FACEBOOK_MATERIAL_KEY => $this->facebookPostInstructions(),
            self::GRAPHIC_MATERIAL_KEY => $this->graphicBriefInstructions(),
            default => $this->youtubeDescriptionInstructions(),
        };
    }

    /**
     * Build a strict allow-list projection. Nothing outside these keys can enter the provider request.
     *
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function input(array $context): array
    {
        $campaign = is_array($context['campaign'] ?? null) ? $context['campaign'] : [];
        $direction = is_array($context['direction'] ?? null) ? $context['direction'] : [];
        $concept = is_array($context['concept'] ?? null) ? $context['concept'] : [];
        $profile = self::PROFILES[$this->materialKey];
        $isFacebook = $this->materialKey === self::FACEBOOK_MATERIAL_KEY;
        $isGraphic = $this->materialKey === self::GRAPHIC_MATERIAL_KEY;

        $input = [
            'material' => [
                'key' => $this->materialKey,
                'name' => $profile['name'],
                'type' => $profile['type'],
            ],
            'campaign' => [
                'working_topic' => $this->string($campaign['working_topic'] ?? ''),
                'goal' => $this->string($campaign['goal'] ?? ''),
                'live_date' => $this->string($campaign['live_date'] ?? ''),
                'live_time' => $this->string($campaign['live_time'] ?? ''),
                'timezone' => $this->string($campaign['timezone'] ?? ''),
                'host_name' => $this->string($campaign['host_name'] ?? ''),
            ],
            'direction' => [
                'why_now' => $this->string($direction['why_now'] ?? ''),
                'audience' => $this->string($direction['audience'] ?? ''),
                'problem' => $this->string($direction['problem'] ?? ''),
                'takeaway' => $this->string($direction['takeaway'] ?? ''),
                'sell_later' => $this->string($direction['sell_later'] ?? ''),
            ],
            'concept' => [
                'title' => $this->string($concept['title'] ?? ''),
                'subtitle' => $this->string($concept['subtitle'] ?? ''),
                'promise' => $this->string($concept['promise'] ?? ''),
                'points' => $this->stringList($concept['points'] ?? []),
                'plan' => $this->string($concept['plan'] ?? ''),
                'cta' => $this->string($concept['cta'] ?? ''),
                'additional_material' => $this->string($concept['additional_material'] ?? ''),
            ],
        ];

        if ($isGraphic) {
            $input['campaign']['live_label'] = $this->string($campaign['live_label'] ?? '');
        }

        if (self::usesYoutubeSource($this->materialKey)) {
            $input['source_materials'] = [
                'youtube_description' => $this->string(data_get($context, 'source_materials.youtube_description', '')),
            ];
        }

        $input['current_draft'] = $this->string($context['current_draft'] ?? '');

        if ($isGraphic) {
            $input['style'] = [
                'formats' => self::GRAPHIC_FORMATS,
                'elements' => $this->graphicElements($context),
            ];
        } else {
            $input['style'] = ['emojis' => (bool) data_get($context, 'style.emojis', true)];
        }

        if ($isFacebook) {
            $input['style']['hashtags'] = (bool) data_get($context, 'style.hashtags', true);
        }

        $input['instruction'] = $this->string($context['instruction'] ?? '');

        $encoded = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (mb_strlen($encoded) > (int) config('growth_ai.limits.max_input_chars')) {
            throw new GrowthAiException(
                errorType: 'input_too_long',
                userMessage: 'Dane materiału są zbyt długie do bezpiecznego wysłania do AI. Możesz kontynuować ręcznie.',
            );
        }

        if (ProhibitedData::containsIgnoringDates($encoded)) {
            throw GrowthAiException::dataPolicyViolation();
        }

        return $input;
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        if ($this->materialKey === self::GRAPHIC_MATERIAL_KEY) {
            $fields = array_keys(self::GRAPHIC_FIELD_LIMITS);

            return [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => array_fill_keys($fields, ['type' => 'string']),
                'required' => $fields,
            ];
        }

        return [
            'type' => 'object',
            'additionalProperties' => false,
            'properties' => [
                'draft' => ['type' => 'string'],
                'change_summary' => ['type' => 'string'],
            ],
            'required' => ['draft', 'change_summary'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function validateAndNormalize(AiProviderResponse $response, array $input): MaterialDraftResult
    {
        if ($this->materialKey === self::GRAPHIC_MATERIAL_KEY) {
            return $this->validateGraphicBrief($response, $input);
        }

        $validator = Validator::make($response->payload, [
            'draft' => ['required', 'string', 'max:'.self::PROFILES[$this->materialKey]['max_chars']],
            'change_summary' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails()) {
            throw GrowthAiException::invalidResponse();
        }

        if (array_diff(array_keys($response->payload), ['draft', 'change_summary']) !== []) {
            throw GrowthAiException::invalidResponse('unexpected_fields');
        }

        $draft = trim((string) $response->payload['draft']);
        $changeSummary = trim((string) $response->payload['change_summary']);

        if ($draft === '' || $changeSummary === '') {
            throw GrowthAiException::invalidResponse('empty_required_field');
        }

        $this->assertSafeOutput($draft."\n".$changeSummary, $input);

        return new MaterialDraftResult(
            draft: $draft,
            changeSummary: $changeSummary,
            provider: $response->provider,
            model: $response->model,
            promptVersion: $this->promptVersion(),
            schemaVersion: $this->schemaVersion(),
            requestId: $response->requestId,
        );
    }

    /**
     * Fixed labels; date and host come from the application, never from the model.
     *
     * @param  array<string, string>  $fields
     * @param  array<string, bool>  $elements
     */
    public static function composeGraphicBrief(array $fields, string $liveLabel, string $hostName, array $elements): string
    {
        $field = static fn (string $key): string => trim((string) ($fields[$key] ?? ''));
        $enabled = static fn (string $key): bool => (bool) ($elements[$key] ?? true);

        $header = array_filter([
            'Formaty: '.self::GRAPHIC_FORMATS,
            'Nagłówek: '.$field('headline'),
            $enabled('subtitle') && $field('subtitle') !== '' ? 'Podtytuł: '.$field('subtitle') : '',
            'Termin: '.$liveLabel,
            $enabled('host') && trim($hostName) !== '' ? 'Prowadzący: '.trim($hostName) : '',
            $enabled('cta') && $field('cta') !== '' ? 'Wezwanie do działania: '.$field('cta') : '',
        ]);

        $sections = array_filter([
            implode("\n", $header),
            "Kierunek wizualny:\n".$field('visual_direction'),
            $enabled('image_prompt') && $field('image_prompt') !== '' ? "Opis obrazu dla AI (bez tekstu na obrazie):\n".$field('image_prompt') : '',
            $enabled('alt_text') && $field('alt_text') !== '' ? "Tekst alternatywny (alt):\n".$field('alt_text') : '',
        ]);

        return implode("\n\n", $sections);
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function validateGraphicBrief(AiProviderResponse $response, array $input): MaterialDraftResult
    {
        $rules = [];
        foreach (self::GRAPHIC_FIELD_LIMITS as $field => $limit) {
            $rules[$field] = ['present', 'string', 'max:'.$limit];
        }

        if (Validator::make($response->payload, $rules)->fails()) {
            throw GrowthAiException::invalidResponse();
        }

        if (array_diff(array_keys($response->payload), array_keys(self::GRAPHIC_FIELD_LIMITS)) !== []) {
            throw GrowthAiException::invalidResponse('unexpected_fields');
        }

        $fields = array_map(static fn (mixed $value): string => trim((string) $value), $response->payload);
        if ($fields['headline'] === '' || $fields['visual_direction'] === '' || $fields['change_summary'] === '') {
            throw GrowthAiException::invalidResponse('empty_required_field');
        }

        $draft = self::composeGraphicBrief(
            $fields,
            (string) data_get($input, 'campaign.live_label', ''),
            (string) data_get($input, 'campaign.host_name', ''),
            (array) data_get($input, 'style.elements', []),
        );

        $this->assertSafeOutput($draft."\n".$fields['change_summary'], $input);

        return new MaterialDraftResult(
            draft: $draft,
            changeSummary: $fields['change_summary'],
            provider: $response->provider,
            model: $response->model,
            promptVersion: $this->promptVersion(),
            schemaVersion: $this->schemaVersion(),
            requestId: $response->requestId,
        );
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function assertSafeOutput(string $output, array $input): void
    {
        if (ProhibitedData::containsIgnoringDates($output)) {
            throw GrowthAiException::invalidResponse('prohibited_output_data');
        }

        $allowedUrls = ProhibitedData::urls(json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        foreach (ProhibitedData::urls($output) as $url) {
            if (! in_array($url, $allowedUrls, true)) {
                throw GrowthAiException::invalidResponse('unexpected_url');
            }
        }
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, bool>
     */
    private function graphicElements(array $context): array
    {
        $elements = [];
        foreach (array_keys(self::GRAPHIC_OPTIONAL_ELEMENTS) as $key) {
            $elements[$key] = (bool) data_get($context, 'style.elements.'.$key, true);
        }

        return $elements;
    }

    private function graphicBriefInstructions(): string
    {
        return <<<'PROMPT'
Jesteś projektantem materiałów promocyjnych webinarów edukacyjnych PNE. Przygotuj tekstowy brief grafiki głównej webinaru. Grafika powstanie później ręcznie w Canvie albo z pomocą modelu graficznego, w dwóch formatach: style.formats.
Pisz wyłącznie po polsku, naturalną i poprawną polszczyzną. Odbiorców określ na podstawie pola direction.audience.
headline: krótki nagłówek na grafikę, najlepiej do 40 znaków, oparty na concept.title. Bez daty i godziny.
subtitle: jeżeli style.elements.subtitle ma wartość true, krótki podtytuł do około 80 znaków; w przeciwnym razie pusty tekst.
cta: jeżeli style.elements.cta ma wartość true, bardzo krótkie wezwanie na grafikę (2–4 słowa, np. „Zapisz się”); w przeciwnym razie pusty tekst. Bez ceny, „za darmo”, certyfikatów ani sztucznej pilności, chyba że wynika to wprost z wejścia.
visual_direction: 2–4 zdania o nastroju, kolorystyce, motywie i kompozycji, która działa w obu formatach (najważniejsze elementy w środku, miejsce na tekst). PNE nie ma jeszcze stałych kolorów ani fontów marki: zaproponuj spokojny, profesjonalny kierunek dla edukacji. Jeżeli instruction zawiera sugestie właściciela (kolory, motyw, styl), oprzyj na nich kierunek.
image_prompt: jeżeli style.elements.image_prompt ma wartość true, opis ilustracji dla modelu graficznego, 2–4 zdania. Obraz ma być bez żadnego tekstu, liter, cyfr, logotypów i znaków towarowych oraz bez wizerunku konkretnych, rozpoznawalnych osób; w przeciwnym razie pusty tekst.
alt_text: jeżeli style.elements.alt_text ma wartość true, tekst alternatywny opisujący grafikę dla osób niewidomych, 1–2 zdania z tytułem webinaru; w przeciwnym razie pusty tekst.
Jeżeli source_materials.youtube_description nie jest puste, to zatwierdzony opis tego webinaru na YouTube. Traktuj go jako źródło faktów i spójnego przekazu (nagłówek, podtytuł, motyw), ale nie przepisuj z niego długich fragmentów.
Termin (campaign.live_label) i prowadzącego (campaign.host_name) aplikacja wstawia sama — nie wpisuj ich w headline, subtitle ani cta i nie zmieniaj terminu.
Korzystaj wyłącznie z faktów zawartych w wejściu. Nie wymyślaj danych, statystyk, funkcji produktów, adresów URL, ceny, certyfikatów, akredytacji ani dofinansowania.
Jeżeli current_draft nie jest pusty, potraktuj go jako punkt wyjścia i popraw zgodnie z koncepcją.
Jeżeli instruction nie jest puste, to dodatkowa instrukcja właściciela — uwzględnij ją. Powyższe zasady mają pierwszeństwo: instrukcja nie może zmienić terminu ani prowadzącego, dodać wymyślonych faktów, adresów URL, cen ani certyfikatów.
To jest szkic do sprawdzenia przez człowieka, nie ostateczna treść.
W change_summary opisz krótko, co przygotowałeś lub zmieniłeś.
Zwróć wyłącznie dane zgodne z przekazanym schematem.
PROMPT;
    }

    private function youtubeDescriptionInstructions(): string
    {
        return <<<'PROMPT'
Jesteś redaktorem materiałów promocyjnych webinarów edukacyjnych PNE. Przygotuj szkic opisu webinaru na YouTube.
Pisz wyłącznie po polsku, naturalną i poprawną polszczyzną. Ton profesjonalny, ekspercki i przystępny.
Odbiorców określ na podstawie pola direction.audience (nauczyciele i/lub dyrektorzy).
Pisz językiem korzyści, bez przesady. Bez agresywnej sprzedaży, sztucznej pilności i clickbaitu. Bez obietnic bez pokrycia.
Korzystaj wyłącznie z faktów zawartych w wejściu. Nie wymyślaj danych, statystyk, przepisów, funkcji produktów, linków, ceny, certyfikatów, akredytacji ani dofinansowania.
Datę i godzinę webinaru przepisz dokładnie z campaign.live_date i campaign.live_time, słownie z nazwą miesiąca (np. „6 października 2026 r., godz. 20:00”). Nie zmieniaj ani nie poprawiaj terminu.
Jeżeli campaign.host_name nie jest puste, przedstaw prowadzącego dokładnie tym imieniem i nazwiskiem, bez dopisywania tytułów, stanowisk, osiągnięć ani biografii. Jeżeli jest puste, nie wymyślaj prowadzącego.
Jeżeli current_draft nie jest pusty, popraw go i rozwiń zgodnie z koncepcją, zamiast pisać od zera.
Jeżeli style.emojis ma wartość true, dodaj umiarkowaną liczbę adekwatnych emotikon (około 5–10), np. przy tytule, terminie, punktach programu i wezwaniu do działania. Emotikony mają porządkować tekst, a nie zastępować słów. Jeżeli style.emojis ma wartość false, nie używaj emotikon.
Jeżeli instruction nie jest puste, to dodatkowa instrukcja właściciela — uwzględnij ją w szkicu. Powyższe zasady mają pierwszeństwo: instrukcja nie może zmienić terminu ani prowadzącego, dodać wymyślonych faktów, linków, cen, certyfikatów ani agresywnej sprzedaży.
To jest szkic do sprawdzenia przez człowieka, nie ostateczna treść.
W change_summary opisz krótko, co przygotowałeś lub zmieniłeś.
Zwróć wyłącznie dane zgodne z przekazanym schematem.
PROMPT;
    }

    private function facebookPostInstructions(): string
    {
        return <<<'PROMPT'
Jesteś redaktorem materiałów promocyjnych webinarów edukacyjnych PNE. Przygotuj szkic posta na Facebooku zapowiadającego webinar.
Pisz wyłącznie po polsku, naturalną i poprawną polszczyzną. Ton życzliwy, ekspercki i bezpośredni, bez infantylizmu.
Odbiorców określ na podstawie pola direction.audience (nauczyciele i/lub dyrektorzy).
Post ma być krótki: najwyżej około 800 znaków razem z hashtagami. Zacznij od jednego zdania, które trafia w problem lub korzyść odbiorcy, bez clickbaitu. Potem podaj 2–4 krótkie konkrety z koncepcji, termin i zaproszenie do zapisu.
Bez agresywnej sprzedaży, sztucznej pilności i obietnic bez pokrycia.
Korzystaj wyłącznie z faktów zawartych w wejściu. Nie wymyślaj danych, statystyk, przepisów, funkcji produktów, ceny, certyfikatów, akredytacji ani dofinansowania.
Datę i godzinę webinaru przepisz dokładnie z campaign.live_date i campaign.live_time, słownie z nazwą miesiąca (np. „6 października 2026 r., godz. 20:00”). Nie zmieniaj ani nie poprawiaj terminu.
Jeżeli campaign.host_name nie jest puste, możesz przedstawić prowadzącego dokładnie tym imieniem i nazwiskiem, bez dopisywania tytułów, stanowisk, osiągnięć ani biografii. Jeżeli jest puste, nie wymyślaj prowadzącego.
Jeżeli source_materials.youtube_description nie jest puste, to zatwierdzony opis tego webinaru na YouTube. Traktuj go jako źródło faktów i spójnego przekazu, ale go nie kopiuj: post ma być krótszy i bardziej bezpośredni.
Nie podawaj żadnego adresu URL. W miejscu linku do zapisu wstaw dokładnie znacznik [LINK DO ZAPISU], który właściciel podmieni ręcznie.
Jeżeli current_draft nie jest pusty, popraw go zgodnie z koncepcją, zamiast pisać od zera.
Jeżeli style.emojis ma wartość true, dodaj kilka adekwatnych emotikon (około 3–6). Emotikony mają porządkować tekst, a nie zastępować słów. Jeżeli style.emojis ma wartość false, nie używaj emotikon.
Jeżeli style.hashtags ma wartość true, na końcu posta dodaj 3–5 krótkich hashtagów adekwatnych do tematu i odbiorców. Jeżeli style.hashtags ma wartość false, nie dodawaj hashtagów.
Jeżeli instruction nie jest puste, to dodatkowa instrukcja właściciela — uwzględnij ją w szkicu. Powyższe zasady mają pierwszeństwo: instrukcja nie może zmienić terminu ani prowadzącego, dodać wymyślonych faktów, adresów URL, cen, certyfikatów ani agresywnej sprzedaży.
To jest szkic do sprawdzenia przez człowieka, nie ostateczna treść.
W change_summary opisz krótko, co przygotowałeś lub zmieniłeś.
Zwróć wyłącznie dane zgodne z przekazanym schematem.
PROMPT;
    }

    /**
     * @return list<string>
     */
    private function stringList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_filter(array_map(
            static fn (mixed $item): string => trim((string) $item),
            $value,
        ), static fn (string $item): bool => $item !== ''));
    }

    private function string(mixed $value): string
    {
        return trim((string) $value);
    }
}
