<?php

namespace App\Services\GrowthOS\AI\Tasks;

use App\Services\GrowthOS\AI\Contracts\GrowthAiTask;
use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Data\MaterialDraftResult;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Support\ProhibitedData;
use App\Support\GrowthOS\AiListFormatter;
use Illuminate\Support\Facades\Validator;

/**
 * Opis obrazu grafiki głównej, osobno od briefu (DEC-043).
 * Wynik to sam opis dla modelu graficznego, bez nagłówka i bez reszty briefu.
 */
final class GraphicImageDescriptionTask implements GrowthAiTask
{
    public const TYPE = 'graphic_image_description';

    public const PROMPT_VERSION = 'graphic_image_description_v1';

    public const SCHEMA_VERSION = 'graphic_image_description_schema_v1';

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
Jesteś projektantem ilustracji do webinarów edukacyjnych PNE. Przygotowujesz wyłącznie opis obrazu dla modelu graficznego.
Pisz wyłącznie po polsku, naturalną i poprawną polszczyzną, 2–4 zdania.
Opis mówi, co ma być na obrazie: miejsce, osoby, układ, światło, nastrój. Bez nagłówka, daty, ceny i wezwania do działania — napisy dokłada później aplikacja.
Na obrazie nie może być tekstu, liter, cyfr, logotypów ani wizerunku konkretnych, rozpoznawalnych osób. Nie wpisuj tych zakazów jako listy w środku opisu, chyba że doprecyzowują scenę.
Scena ma pasować do campaign.working_topic, direction i concept. Nie wymyślaj innego tematu webinaru.
Jeżeli visual_direction nie jest puste, trzymaj się jego nastroju i motywu.

TRYB PRACY (pole mode):
- "generate": napisz nowy opis od zera. Nie ma wcześniejszego opisu.
- "refine": author_draft to obecny opis, także niezapisany. Popraw tylko to, o co prosi instruction, oraz zdanie sprzeczne z tematem. Resztę opisu zostaw.
- "iterate": previous_proposal to Twoja poprzednia propozycja, a instruction mówi, co jeszcze poprawić. Zmień tylko to, o co prosi instruction.

Korzystaj wyłącznie z faktów zawartych w wejściu. Nie wymyślaj danych, statystyk, funkcji produktów, adresów URL, ceny, certyfikatów ani dofinansowania.
Jeżeli instruction nie jest puste, uwzględnij ją. Nie może ona zmienić tematu webinaru ani dodać wymyślonych faktów.
To jest szkic do sprawdzenia przez człowieka.
W change_summary opisz krótko, co przygotowałeś lub zmieniłeś.
Zwróć wyłącznie dane zgodne z przekazanym schematem.
PROMPT;
    }

    /**
     * @param  array<string, mixed>  $context
     * @return array<string, mixed>
     */
    public function input(array $context): array
    {
        $campaign = is_array($context['campaign'] ?? null) ? $context['campaign'] : [];
        $direction = is_array($context['direction'] ?? null) ? $context['direction'] : [];
        $concept = is_array($context['concept'] ?? null) ? $context['concept'] : [];
        $mode = MaterialDraftTask::aiMode($context['mode'] ?? null);
        $text = trim((string) ($context['text'] ?? ''));

        $input = [
            'campaign' => [
                'working_topic' => trim((string) ($campaign['working_topic'] ?? '')),
                'live_label' => trim((string) ($campaign['live_label'] ?? '')),
            ],
            'direction' => [
                'why_now' => trim((string) ($direction['why_now'] ?? '')),
                'audience' => trim((string) ($direction['audience'] ?? '')),
                'problem' => trim((string) ($direction['problem'] ?? '')),
                'takeaway' => trim((string) ($direction['takeaway'] ?? '')),
                'sell_later' => trim((string) ($direction['sell_later'] ?? '')),
            ],
            'concept' => [
                'title' => trim((string) ($concept['title'] ?? '')),
                'promise' => trim((string) ($concept['promise'] ?? '')),
                'plan' => trim((string) ($concept['plan'] ?? '')),
            ],
            'visual_direction' => trim((string) ($context['visual_direction'] ?? '')),
            'mode' => $mode,
            'instruction' => trim((string) ($context['instruction'] ?? '')),
        ];

        if ($mode === MaterialDraftTask::MODE_REFINE) {
            $input['author_draft'] = $text;
        } elseif ($mode === MaterialDraftTask::MODE_ITERATE) {
            $input['previous_proposal'] = $text;
        }

        $encoded = json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        if (mb_strlen($encoded) > (int) config('growth_ai.limits.max_input_chars')) {
            throw new GrowthAiException(
                errorType: 'input_too_long',
                userMessage: 'Opis obrazu jest zbyt długi do bezpiecznego wysłania do AI. Możesz poprawić go ręcznie.',
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
                'description' => ['type' => 'string'],
                'change_summary' => ['type' => 'string'],
            ],
            'required' => ['description', 'change_summary'],
        ];
    }

    /**
     * @param  array<string, mixed>  $input
     */
    public function validateAndNormalize(AiProviderResponse $response, array $input): MaterialDraftResult
    {
        $validator = Validator::make($response->payload, [
            'description' => ['required', 'string', 'max:'.GraphicImageTask::MAX_PROMPT_CHARS],
            'change_summary' => ['required', 'string', 'max:1000'],
        ]);

        if ($validator->fails() || array_diff(array_keys($response->payload), ['description', 'change_summary']) !== []) {
            throw GrowthAiException::invalidResponse();
        }

        $description = trim((string) $response->payload['description']);
        $changeSummary = AiListFormatter::lineBreaks(trim((string) $response->payload['change_summary']));

        if ($description === '' || $changeSummary === '') {
            throw GrowthAiException::invalidResponse('empty_required_field');
        }

        $output = $description."\n".$changeSummary;
        if (ProhibitedData::containsIgnoringDates($output)) {
            throw GrowthAiException::invalidResponse('prohibited_output_data');
        }

        $allowedUrls = ProhibitedData::urls(json_encode($input, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR));
        foreach (ProhibitedData::urls($output) as $url) {
            if (! in_array($url, $allowedUrls, true)) {
                throw GrowthAiException::invalidResponse('unexpected_url');
            }
        }

        return new MaterialDraftResult(
            draft: $description,
            changeSummary: $changeSummary,
            provider: $response->provider,
            model: $response->model,
            promptVersion: $this->promptVersion(),
            schemaVersion: $this->schemaVersion(),
            requestId: $response->requestId,
        );
    }
}
