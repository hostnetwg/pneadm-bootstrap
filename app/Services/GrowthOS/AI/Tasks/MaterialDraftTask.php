<?php

namespace App\Services\GrowthOS\AI\Tasks;

use App\Services\GrowthOS\AI\Contracts\GrowthAiTask;
use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Data\MaterialDraftResult;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Support\ProhibitedData;
use Illuminate\Support\Facades\Validator;

/**
 * Szkic materiału webinaru. Pierwszy i jedyny profil: opis YouTube.
 */
final class MaterialDraftTask implements GrowthAiTask
{
    public const TYPE = 'material_draft';

    public const MATERIAL_KEY = 'youtube-description';

    public const PROFILE = 'youtube_description_v1';

    public const PROMPT_VERSION = 'material_youtube_description_v2';

    public const SCHEMA_VERSION = 'material_youtube_description_schema_v1';

    public const MAX_DRAFT_CHARS = 5000;

    public function type(): string
    {
        return self::TYPE;
    }

    public function promptVersion(): string
    {
        return self::PROMPT_VERSION;
    }

    public function schemaVersion(): string
    {
        return self::SCHEMA_VERSION;
    }

    public function instructions(): string
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

        $input = [
            'material' => [
                'key' => self::MATERIAL_KEY,
                'name' => 'Opis YouTube',
                'type' => 'youtube_description',
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
            'current_draft' => $this->string($context['current_draft'] ?? ''),
            'style' => [
                'emojis' => (bool) data_get($context, 'style.emojis', true),
            ],
            'instruction' => $this->string($context['instruction'] ?? ''),
        ];

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
        $validator = Validator::make($response->payload, [
            'draft' => ['required', 'string', 'max:'.self::MAX_DRAFT_CHARS],
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

        if (ProhibitedData::containsIgnoringDates($draft."\n".$changeSummary)) {
            throw GrowthAiException::invalidResponse('prohibited_output_data');
        }

        $allowedUrls = ProhibitedData::urls(json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        foreach (ProhibitedData::urls($draft."\n".$changeSummary) as $url) {
            if (! in_array($url, $allowedUrls, true)) {
                throw GrowthAiException::invalidResponse('unexpected_url');
            }
        }

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
