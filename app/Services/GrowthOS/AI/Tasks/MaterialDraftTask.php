<?php

namespace App\Services\GrowthOS\AI\Tasks;

use App\Services\GrowthOS\AI\Contracts\GrowthAiTask;
use App\Services\GrowthOS\AI\Data\AiProviderResponse;
use App\Services\GrowthOS\AI\Data\MaterialDraftResult;
use App\Services\GrowthOS\AI\Exceptions\GrowthAiException;
use App\Services\GrowthOS\AI\Support\PneVoice;
use App\Services\GrowthOS\AI\Support\ProhibitedData;
use App\Support\GrowthOS\AiListFormatter;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;

/**
 * Szkic materiału webinaru. Profile: opis YouTube (DEC-024), post Facebook (DEC-026), brief grafiki głównej (DEC-028)
 * mailing główny (DEC-032), mailing przypominający (DEC-033) i scenariusz prowadzącego (DEC-034).
 */
final class MaterialDraftTask implements GrowthAiTask
{
    public const TYPE = 'material_draft';

    public const MATERIAL_KEY = 'youtube-description';

    public const PROFILE = 'youtube_description_v1';

    public const PROMPT_VERSION = 'material_youtube_description_v3';

    public const MODE_GENERATE = 'generate';

    public const MODE_REFINE = 'refine';

    public const MODE_ITERATE = 'iterate';

    /** Materials with generate / refine / iterate and the communication voice (DEC-035, DEC-036). */
    public const VOICE_MATERIALS = [self::MATERIAL_KEY];

    public const SCHEMA_VERSION = 'material_youtube_description_schema_v1';

    public const MAX_DRAFT_CHARS = 5000;

    public const FACEBOOK_MATERIAL_KEY = 'facebook-post';

    public const FACEBOOK_PROFILE = 'facebook_post_v1';

    public const FACEBOOK_PROMPT_VERSION = 'material_facebook_post_v2';

    public const FACEBOOK_SCHEMA_VERSION = 'material_facebook_post_schema_v1';

    public const FACEBOOK_MAX_DRAFT_CHARS = 1500;

    public const LINK_PLACEHOLDER = '[LINK DO ZAPISU]';

    public const GRAPHIC_MATERIAL_KEY = 'main-graphic';

    public const GRAPHIC_PROFILE = 'graphic_brief_v1';

    public const GRAPHIC_PROMPT_VERSION = 'material_graphic_brief_v3';

    public const GRAPHIC_SCHEMA_VERSION = 'material_graphic_brief_schema_v1';

    public const GRAPHIC_FORMATS = '16:9 (1920×1080) i kwadrat (1080×1080)';

    public const MAIL_MATERIAL_KEY = 'main-mail';

    public const MAIL_PROFILE = 'main_mail_v1';

    public const MAIL_PROMPT_VERSION = 'material_main_mail_v5';

    public const MAIL_SCHEMA_VERSION = 'material_main_mail_schema_v1';

    public const MAIL_SUBJECT_COUNT = 3;

    public const MAIL_DEFAULT_LENGTH = 'short';

    /**
     * @var array<string, string>
     */
    public const MAIL_LENGTHS = [
        'short' => 'Krótki (ok. 150–250 słów)',
        'long' => 'Dłuższy (ok. 300–450 słów)',
    ];

    public const REMINDER_MATERIAL_KEY = 'reminder-mail';

    public const REMINDER_PROFILE = 'reminder_mail_v2';

    public const REMINDER_PROMPT_VERSION = 'material_reminder_mail_v2';

    public const REMINDER_SCHEMA_VERSION = 'material_reminder_mail_schema_v2';

    public const ROOM_LINK_PLACEHOLDER = '[LINK DO POKOJU]';

    /**
     * @var array<string, string>
     */
    public const REMINDER_LENGTHS = [
        'short' => 'Krótki (ok. 80–150 słów)',
        'long' => 'Dłuższy (ok. 180–280 słów)',
    ];

    public const REMINDER_DEFAULT_TIMING = 'same_day';

    /**
     * @var array<string, string>
     */
    public const REMINDER_TIMINGS = [
        'day_before' => 'Dzień przed webinarem („jutro”)',
        'same_day' => 'W dniu webinaru („dziś”)',
    ];

    public const HOST_SCRIPT_MATERIAL_KEY = 'host-script';

    public const HOST_SCRIPT_PROFILE = 'host_script_v1';

    public const HOST_SCRIPT_PROMPT_VERSION = 'material_host_script_v1';

    public const HOST_SCRIPT_SCHEMA_VERSION = 'material_host_script_schema_v1';

    public const HOST_SCRIPT_MAX_DRAFT_CHARS = 12000;

    /**
     * @var list<int>
     */
    public const HOST_SCRIPT_DURATIONS = [45, 60, 90];

    public const HOST_SCRIPT_DEFAULT_DURATION = 60;

    public const HOST_SCRIPT_MIN_DURATION = 15;

    public const HOST_SCRIPT_MAX_DURATION = 240;

    /**
     * Approved materials passed to the model as sources, by material key.
     *
     * @var array<string, list<string>>
     */
    private const SOURCE_MATERIALS = [
        self::FACEBOOK_MATERIAL_KEY => ['youtube_description'],
        self::GRAPHIC_MATERIAL_KEY => ['youtube_description'],
        self::MAIL_MATERIAL_KEY => ['youtube_description'],
        self::REMINDER_MATERIAL_KEY => ['youtube_description', 'main_mail'],
        self::HOST_SCRIPT_MATERIAL_KEY => ['youtube_description'],
    ];

    /**
     * @var array<string, int>
     */
    private const MAIL_FIELD_LIMITS = [
        'subject' => 120,
        'preheader' => 160,
        'body' => 6000,
        'change_summary' => 1000,
    ];

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
        'headline' => 160,
        'subtitle' => 160,
        'cta' => 40,
        'visual_direction' => 800,
        'image_prompt' => 4000,
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
        self::MAIL_MATERIAL_KEY => [
            'profile' => self::MAIL_PROFILE,
            'name' => 'Mailing główny',
            'type' => 'main_mail',
            'prompt_version' => self::MAIL_PROMPT_VERSION,
            'schema_version' => self::MAIL_SCHEMA_VERSION,
            'max_chars' => self::MAIL_FIELD_LIMITS['body'],
        ],
        self::REMINDER_MATERIAL_KEY => [
            'profile' => self::REMINDER_PROFILE,
            'name' => 'Mailing przypominający',
            'type' => 'reminder_mail',
            'prompt_version' => self::REMINDER_PROMPT_VERSION,
            'schema_version' => self::REMINDER_SCHEMA_VERSION,
            'max_chars' => self::MAIL_FIELD_LIMITS['body'],
        ],
        self::HOST_SCRIPT_MATERIAL_KEY => [
            'profile' => self::HOST_SCRIPT_PROFILE,
            'name' => 'Scenariusz prowadzącego',
            'type' => 'host_script',
            'prompt_version' => self::HOST_SCRIPT_PROMPT_VERSION,
            'schema_version' => self::HOST_SCRIPT_SCHEMA_VERSION,
            'max_chars' => self::HOST_SCRIPT_MAX_DRAFT_CHARS,
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
     * Approved materials the model receives as sources (DEC-026, DEC-029, DEC-032, DEC-033).
     *
     * @return list<string>
     */
    public static function sourceMaterialKeys(string $materialKey): array
    {
        return self::SOURCE_MATERIALS[$materialKey] ?? [];
    }

    public static function usesVoice(string $materialKey): bool
    {
        return in_array($materialKey, self::VOICE_MATERIALS, true);
    }

    /**
     * Materials with generate / refine / iterate. Voice stays on the YouTube description only.
     */
    public static function usesWorkModes(string $materialKey): bool
    {
        return self::usesVoice($materialKey)
            || $materialKey === self::GRAPHIC_MATERIAL_KEY
            || $materialKey === self::FACEBOOK_MATERIAL_KEY
            || $materialKey === self::MAIL_MATERIAL_KEY
            || $materialKey === self::REMINDER_MATERIAL_KEY;
    }

    public static function aiMode(mixed $value): string
    {
        return in_array($value, [self::MODE_REFINE, self::MODE_ITERATE], true) ? $value : self::MODE_GENERATE;
    }

    public static function usesYoutubeSource(string $materialKey): bool
    {
        return in_array('youtube_description', self::sourceMaterialKeys($materialKey), true);
    }

    /**
     * Materials edited as subject, preheader and body (DEC-032, DEC-033).
     */
    public static function isMail(string $materialKey): bool
    {
        return in_array($materialKey, [self::MAIL_MATERIAL_KEY, self::REMINDER_MATERIAL_KEY], true);
    }

    /**
     * @return array<string, string>
     */
    public static function mailLengths(string $materialKey): array
    {
        return $materialKey === self::REMINDER_MATERIAL_KEY ? self::REMINDER_LENGTHS : self::MAIL_LENGTHS;
    }

    public static function mailLength(mixed $value): string
    {
        return is_string($value) && array_key_exists($value, self::MAIL_LENGTHS) ? $value : self::MAIL_DEFAULT_LENGTH;
    }

    public static function hostScriptDuration(mixed $value): int
    {
        $minutes = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($minutes) && $minutes >= self::HOST_SCRIPT_MIN_DURATION && $minutes <= self::HOST_SCRIPT_MAX_DURATION
            ? $minutes
            : self::HOST_SCRIPT_DEFAULT_DURATION;
    }

    /**
     * End of the live as H:i, computed here so the model never has to add up the time.
     */
    public static function hostScriptEndTime(string $liveTime, int $minutes): string
    {
        try {
            return CarbonImmutable::createFromFormat('H:i', trim($liveTime))->addMinutes($minutes)->format('H:i');
        } catch (\Throwable) {
            return '';
        }
    }

    public static function reminderTiming(mixed $value): string
    {
        return is_string($value) && array_key_exists($value, self::REMINDER_TIMINGS) ? $value : self::REMINDER_DEFAULT_TIMING;
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
            self::MAIL_MATERIAL_KEY => $this->mainMailInstructions(),
            self::REMINDER_MATERIAL_KEY => $this->reminderMailInstructions(),
            self::HOST_SCRIPT_MATERIAL_KEY => $this->hostScriptInstructions(),
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
        $isMail = self::isMail($this->materialKey);
        $isHostScript = $this->materialKey === self::HOST_SCRIPT_MATERIAL_KEY;

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

        if ($isGraphic || $isMail || $isHostScript) {
            $input['campaign']['live_label'] = $this->string($campaign['live_label'] ?? '');
        }

        $sources = self::sourceMaterialKeys($this->materialKey);
        if ($sources !== []) {
            $input['source_materials'] = [];
            foreach ($sources as $source) {
                $input['source_materials'][$source] = $this->string(data_get($context, 'source_materials.'.$source, ''));
            }
        }

        if (self::usesVoice($this->materialKey)) {
            $this->addVoiceAndWork($input, $context);
        } else {
            $input['current_draft'] = $this->string($context['current_draft'] ?? '');
            if (in_array($this->materialKey, [self::MAIL_MATERIAL_KEY, self::REMINDER_MATERIAL_KEY], true)) {
                $input['current_draft'] = \App\Support\GrowthOS\MailHtmlFormatter::plainForAi($input['current_draft']);
            }
        }

        if ($isGraphic) {
            $input['style'] = [
                'formats' => self::GRAPHIC_FORMATS,
                'elements' => $this->graphicElements($context),
            ];
        } elseif ($isHostScript) {
            $minutes = self::hostScriptDuration(data_get($context, 'style.duration_minutes'));
            $input['style'] = [
                'duration_minutes' => $minutes,
                'end_time' => self::hostScriptEndTime($input['campaign']['live_time'], $minutes),
            ];
        } elseif ($isMail) {
            $input['style'] = [
                'emojis' => (bool) data_get($context, 'style.emojis', false),
                'length' => self::mailLength(data_get($context, 'style.length')),
            ];
            if ($this->materialKey === self::MAIL_MATERIAL_KEY) {
                $input['style']['html'] = (bool) data_get($context, 'style.html', false);
            }
            if ($this->materialKey === self::REMINDER_MATERIAL_KEY) {
                $input['style']['timing'] = self::reminderTiming(data_get($context, 'style.timing'));
            }
        } else {
            $input['style'] = ['emojis' => (bool) data_get($context, 'style.emojis', ! self::usesVoice($this->materialKey))];
        }

        if ($isFacebook) {
            $input['style']['hashtags'] = (bool) data_get($context, 'style.hashtags', true);
        }

        $input['instruction'] = $this->string($context['instruction'] ?? '');

        if ($isGraphic || $isFacebook || in_array($this->materialKey, [self::MAIL_MATERIAL_KEY, self::REMINDER_MATERIAL_KEY], true)) {
            $this->addGraphicWork($input, $context);
        }

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
     * Presenter facts and the communication voice are separate keys, so the model never mixes who speaks with who
     * presents. Only the voice owner's full name and profile are sent (DEC-035). Mode decides the working text (DEC-036).
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $context
     */
    private function addVoiceAndWork(array &$input, array $context): void
    {
        $input['presenter'] = ['name' => $input['campaign']['host_name']];
        unset($input['campaign']['host_name']);

        $name = $this->string(data_get($context, 'voice.name', ''));
        $profile = $this->string(data_get($context, 'voice.profile', ''));
        $input['voice'] = [
            'pne_version' => PneVoice::VERSION,
            'pne_rules' => PneVoice::rules(),
            'personal' => $name !== '' && $profile !== '' ? ['name' => $name, 'profile' => $profile] : null,
        ];

        $mode = self::aiMode(data_get($context, 'work.mode'));
        $text = $this->string(data_get($context, 'work.text', ''));
        $input['mode'] = $mode;
        if ($mode === self::MODE_REFINE) {
            $input['author_draft'] = $text;
        } elseif ($mode === self::MODE_ITERATE) {
            $input['previous_proposal'] = $text;
        }
    }

    /**
     * Brief and Facebook modes match the YouTube description (DEC-036) without the communication voice.
     * Refine and iterate replace the saved draft with the text the owner is actually correcting.
     *
     * @param  array<string, mixed>  $input
     * @param  array<string, mixed>  $context
     */
    private function addGraphicWork(array &$input, array $context): void
    {
        $mode = self::aiMode(data_get($context, 'work.mode'));
        $text = $this->string(data_get($context, 'work.text', ''));
        if (in_array($this->materialKey, [self::MAIL_MATERIAL_KEY, self::REMINDER_MATERIAL_KEY], true)) {
            $text = \App\Support\GrowthOS\MailHtmlFormatter::plainForAi($text);
        }
        $input['mode'] = $mode;

        if ($mode === self::MODE_REFINE) {
            $input['author_draft'] = $text;
            $input['current_draft'] = '';
        } elseif ($mode === self::MODE_ITERATE) {
            $input['previous_proposal'] = $text;
            $input['current_draft'] = '';
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(): array
    {
        if (self::isMail($this->materialKey)) {
            return [
                'type' => 'object',
                'additionalProperties' => false,
                'properties' => [
                    'subject_options' => ['type' => 'array', 'items' => ['type' => 'string']],
                    'preheader' => ['type' => 'string'],
                    'body' => ['type' => 'string'],
                    'change_summary' => ['type' => 'string'],
                ],
                'required' => ['subject_options', 'preheader', 'body', 'change_summary'],
            ];
        }

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

        if (self::isMail($this->materialKey)) {
            return $this->validateMainMail($response, $input);
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

        $draft = AiListFormatter::lineBreaks((string) $response->payload['draft']);
        $changeSummary = AiListFormatter::lineBreaks((string) $response->payload['change_summary']);

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
     * Fixed labels; the first subject is the main one.
     *
     * @param  list<string>  $subjects
     */
    public static function composeMainMail(array $subjects, string $preheader, string $body): string
    {
        $subjects = array_values(array_filter(array_map('trim', $subjects), static fn (string $subject): bool => $subject !== ''));
        $alternatives = array_map(static fn (string $subject): string => '- '.$subject, array_slice($subjects, 1));

        $header = array_filter([
            isset($subjects[0]) ? 'Temat: '.$subjects[0] : '',
            $alternatives !== [] ? "Inne propozycje tematu:\n".implode("\n", $alternatives) : '',
            trim($preheader) !== '' ? 'Preheader: '.trim($preheader) : '',
        ]);

        return $header === [] ? trim($body) : trim(implode("\n", $header)."\n\n".trim($body));
    }

    /**
     * Reverse of composeMainMail. A draft without the leading labels goes to the body unchanged.
     *
     * @return array{subject: string, alternatives: list<string>, preheader: string, body: string}
     */
    public static function parseMainMail(string $draft): array
    {
        $result = ['subject' => '', 'alternatives' => [], 'preheader' => '', 'body' => trim($draft)];
        $lines = preg_split('/\R/u', trim($draft)) ?: [];
        $inAlternatives = false;

        foreach ($lines as $index => $line) {
            if (preg_match('/^Temat:[ \t]*(.*)$/u', $line, $match) === 1 && $result['subject'] === '') {
                $result['subject'] = trim($match[1]);
                $inAlternatives = false;
            } elseif (trim($line) === 'Inne propozycje tematu:') {
                $inAlternatives = true;
            } elseif ($inAlternatives && preg_match('/^-[ \t]*(.+)$/u', $line, $match) === 1) {
                $result['alternatives'][] = trim($match[1]);
            } elseif (preg_match('/^Preheader:[ \t]*(.*)$/u', $line, $match) === 1 && $result['preheader'] === '') {
                $result['preheader'] = trim($match[1]);
                $inAlternatives = false;
            } elseif (trim($line) === '' && $index > 0) {
                $result['body'] = trim(implode("\n", array_slice($lines, $index + 1)));

                return $result;
            } else {
                return $index === 0
                    ? $result
                    : ['subject' => '', 'alternatives' => [], 'preheader' => '', 'body' => trim($draft)];
            }
        }

        $result['body'] = '';

        return $result;
    }

    /**
     * @param  array<string, mixed>  $input
     */
    private function validateMainMail(AiProviderResponse $response, array $input): MaterialDraftResult
    {
        $validator = Validator::make($response->payload, [
            'subject_options' => ['required', 'array', 'size:'.self::MAIL_SUBJECT_COUNT],
            'subject_options.*' => ['required', 'string', 'max:'.self::MAIL_FIELD_LIMITS['subject']],
            'preheader' => ['required', 'string', 'max:'.self::MAIL_FIELD_LIMITS['preheader']],
            'body' => ['required', 'string', 'max:'.self::MAIL_FIELD_LIMITS['body']],
            'change_summary' => ['required', 'string', 'max:'.self::MAIL_FIELD_LIMITS['change_summary']],
        ]);

        if ($validator->fails()) {
            throw GrowthAiException::invalidResponse();
        }

        if (array_diff(array_keys($response->payload), ['subject_options', 'preheader', 'body', 'change_summary']) !== []) {
            throw GrowthAiException::invalidResponse('unexpected_fields');
        }

        $subjects = array_map(static fn (mixed $subject): string => self::upperFirstLetter(trim((string) $subject)), $response->payload['subject_options']);
        $preheader = self::upperFirstLetter(trim((string) $response->payload['preheader']));
        $body = AiListFormatter::lineBreaks((string) $response->payload['body']);
        $changeSummary = AiListFormatter::lineBreaks((string) $response->payload['change_summary']);

        if (in_array('', $subjects, true) || $preheader === '' || $body === '' || $changeSummary === '') {
            throw GrowthAiException::invalidResponse('empty_required_field');
        }

        $draft = self::composeMainMail($subjects, $preheader, $body);
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

    private static function upperFirstLetter(string $value): string
    {
        return (string) preg_replace_callback(
            '/^(\P{L}*)(\p{L})/u',
            static fn (array $match): string => $match[1].mb_strtoupper($match[2]),
            $value,
        );
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
        foreach (['subtitle', 'visual_direction', 'image_prompt', 'alt_text', 'change_summary'] as $key) {
            if (array_key_exists($key, $fields)) {
                $fields[$key] = AiListFormatter::lineBreaks($fields[$key]);
            }
        }
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

TRYB PRACY (pole mode):
- "generate": nowy brief od zera na podstawie campaign.working_topic, kierunku i koncepcji. current_draft nie jest tekstem do przepisania.
- "refine": author_draft to brief autora, także niezapisany. Popraw tylko to, o co prosi instruction, oraz zdanie, które zaprzecza tematowi, kierunkowi albo koncepcji. Resztę briefu zostaw.
- "iterate": previous_proposal to Twoja poprzednia propozycja, a instruction mówi, co jeszcze poprawić. Zmień tylko to, o co prosi instruction.

headline: nagłówek na grafikę równy campaign.working_topic (ustalony temat webinaru). Jeżeli temat jest długi, możesz uciąć dopowiedzenie po dwukropku albo myślniku, ale temat musi zostać rozpoznawalny: te same słowa, bez innego tytułu. concept.title jest tylko kontekstem i nie zastępuje tematu. Bez daty i godziny.
subtitle: jeżeli style.elements.subtitle ma wartość true, krótki podtytuł do około 80 znaków; w przeciwnym razie pusty tekst.
cta: jeżeli style.elements.cta ma wartość true, bardzo krótkie wezwanie na grafikę (2–4 słowa, np. „Zapisz się”); w przeciwnym razie pusty tekst. Bez ceny, „za darmo”, certyfikatów ani sztucznej pilności, chyba że wynika to wprost z wejścia.
visual_direction: 2–4 zdania o nastroju, kolorystyce, motywie i kompozycji, która działa w obu formatach (najważniejsze elementy w środku, miejsce na tekst). PNE nie ma jeszcze stałych kolorów ani fontów marki: zaproponuj spokojny, profesjonalny kierunek dla edukacji. Jeżeli instruction zawiera sugestie właściciela (kolory, motyw, styl), oprzyj na nich kierunek.
image_prompt: jeżeli style.elements.image_prompt ma wartość true, opis ilustracji dla modelu graficznego, 2–4 zdania. Obraz ma być bez żadnego tekstu, liter, cyfr, logotypów i znaków towarowych oraz bez wizerunku konkretnych, rozpoznawalnych osób; w przeciwnym razie pusty tekst.
alt_text: jeżeli style.elements.alt_text ma wartość true, tekst alternatywny opisujący grafikę dla osób niewidomych, 1–2 zdania z tytułem webinaru; w przeciwnym razie pusty tekst.
Jeżeli source_materials.youtube_description nie jest puste, to zatwierdzony opis tego webinaru na YouTube. Traktuj go jako źródło faktów i spójnego przekazu (nagłówek, podtytuł, motyw), ale nie przepisuj z niego długich fragmentów.
Termin (campaign.live_label) i prowadzącego (campaign.host_name) aplikacja wstawia sama — nie wpisuj ich w headline, subtitle ani cta i nie zmieniaj terminu.
Korzystaj wyłącznie z faktów zawartych w wejściu. Nie wymyślaj danych, statystyk, funkcji produktów, adresów URL, ceny, certyfikatów, akredytacji ani dofinansowania.
Jeżeli instruction nie jest puste, to dodatkowa instrukcja właściciela — uwzględnij ją. Powyższe zasady mają pierwszeństwo: instrukcja nie może zmienić terminu, tematu ani prowadzącego, dodać wymyślonych faktów, adresów URL, cen ani certyfikatów.
To jest szkic do sprawdzenia przez człowieka, nie ostateczna treść.
W change_summary opisz krótko, co przygotowałeś lub zmieniłeś.
Zwróć wyłącznie dane zgodne z przekazanym schematem.
PROMPT;
    }

    private function mainMailInstructions(): string
    {
        return <<<'PROMPT'
Jesteś redaktorem mailingów webinarów edukacyjnych PNE. Przygotuj szkic głównego maila zapraszającego na webinar. Mail wyśle później człowiek przez system mailingowy; Ty przygotowujesz tylko treść redakcyjną.
Pisz wyłącznie po polsku, naturalną i poprawną polszczyzną. Ton życzliwy, ekspercki i rzeczowy. Bez agresywnej sprzedaży, sztucznej pilności, clickbaitu i obietnic bez pokrycia.
Odbiorców określ na podstawie pola direction.audience (nauczyciele i/lub dyrektorzy). Zwracaj się do odbiorców w formie „Państwo”.
NIE zaczynaj body od „Dzień dobry,” — aplikacja sama doda greeting Sendy z personalizacją [Name,fallback=].
NIE dodawaj przycisków zapisu, linków YouTube, listy płatnych szkoleń, informacji o zaświadczeniu, stopki prawnej ani [unsubscribe]. Te elementy składa aplikacja.

TRYB PRACY (pole mode):
- "generate": nowy mail od zera na podstawie tematu, kierunku i koncepcji. current_draft nie jest tekstem do przepisania.
- "refine": author_draft to mail autora, także niezapisany. Może mieć etykiety „Temat:” i „Preheader:”, a potem treść. Popraw tylko to, o co prosi instruction, oraz zdanie sprzeczne z tematem, kierunkiem albo koncepcją. Pierwsza propozycja tematu zostaje tematem autora, chyba że instruction każe go zmienić. Dwie pozostałe mogą być wariantami. Preheader i body redaguj, nie pisz maila od zera.
- "iterate": previous_proposal to Twoja poprzednia propozycja, a instruction mówi, co jeszcze poprawić. Zmień tylko to, o co prosi instruction. Etykiet „Temat:” i „Preheader:” nie przenoś do body.
subject_options: dokładnie 3 różne propozycje tematu maila, każda najwyżej około 60 znaków. Zwykła polska pisownia: pierwsza litera tematu wielka, nazwy własne i produkty wielką literą (np. „Canva AI”), żadnych słów pisanych w całości wielkimi literami, bez emotikon i bez wykrzyknika. Pierwsza propozycja jest główna.
preheader: jedno zdanie od wielkiej litery, najwyżej 100 znaków, które uzupełnia temat i go nie powtarza.
body: treść redakcyjna maila, bez tematu i preheadera. Zawiera: wstęp, krótkie wyjaśnienie dlaczego warto przyjść, opis wartości oraz punkty zakresu webinaru.
Jeżeli style.length ma wartość "short", body ma około 120–220 słów: 2–3 zdania o problemie lub korzyści odbiorcy oraz 3 punkty „Czego się Państwo dowiedzą” oparte na concept.points.
Jeżeli style.length ma wartość "long", body ma około 250–400 słów: szerszy kontekst problemu, pełniejszy program oparty na concept.points i concept.plan, krótki akapit o wartości spotkania i informacja o materiale dodatkowym (jeżeli concept.additional_material nie jest puste).
Termin webinaru i prowadzącego nie musisz powtarzać w body — aplikacja pokaże je na karcie webinaru. Jeżeli wspominasz termin, podaj go dokładnie tak jak w campaign.live_label.
Jeżeli campaign.host_name nie jest puste i wspominasz prowadzącego, użyj dokładnie tego imienia i nazwiska, bez dopisywania tytułów, stanowisk, osiągnięć ani biografii.
Nie podawaj żadnego adresu URL. Nie wstawiaj znacznika [LINK DO ZAPISU] — przycisk zapisu doda aplikacja.
Jeżeli style.html ma wartość true, body zostaje zwykłym tekstem, bez znaczników HTML, tabel i stylów. Punkty programu pisz w osobnych liniach zaczynających się od „- ”. Nie zwracaj szablonu, stylów ani przycisku.
Zakończ body podpisem: „Z pozdrowieniami,”, a w kolejnych liniach campaign.host_name (jeżeli nie jest puste) i „Zespół PNE”.
Jeżeli source_materials.youtube_description nie jest puste, to zatwierdzony opis tego webinaru na YouTube. Traktuj go jako źródło faktów i spójnego przekazu, ale go nie kopiuj.
Korzystaj wyłącznie z faktów zawartych w wejściu. Nie wymyślaj danych, statystyk, przepisów, funkcji produktów, ceny, certyfikatów, zaświadczeń, akredytacji ani dofinansowania.
Jeżeli style.emojis ma wartość true, dodaj w body 2–4 adekwatne emotikony (np. przy punktach programu), nigdy w temacie i preheaderze. Jeżeli style.emojis ma wartość false, nie używaj emotikon; w trybach "refine" i "iterate" zachowaj emotikony, które już są w treści, chyba że instruction mówi inaczej.
Jeżeli instruction nie jest puste, to dodatkowa instrukcja właściciela — uwzględnij ją w szkicu. Powyższe zasady mają pierwszeństwo: instrukcja nie może zmienić terminu ani prowadzącego, dodać wymyślonych faktów, adresów URL, cen, certyfikatów ani agresywnej sprzedaży.
To jest szkic do sprawdzenia przez człowieka, nie ostateczna treść.
W change_summary opisz krótko, co przygotowałeś lub zmieniłeś.
Zwróć wyłącznie dane zgodne z przekazanym schematem.
PROMPT;
    }

    private function reminderMailInstructions(): string
    {
        return <<<'PROMPT'
Jesteś redaktorem mailingów webinarów edukacyjnych PNE. Przygotuj szkic maila przypominającego o webinarie — wysyłka tuż przed startem lub dzień wcześniej. Mail trafia do zapisanych i do osób, które jeszcze się nie zapisały. Ty przygotowujesz tylko treść redakcyjną (temat, preheader, body); layout Sendy PNE (greeting, czerwony pasek przypomnienia, karta webinaru, przyciski zapisu i YouTube, zaświadczenie, oferta, stopka) składa aplikacja.
Pisz wyłącznie po polsku, naturalną i poprawną polszczyzną. Ton życzliwy, konkretny, z lekką pilnością „już zaraz start”, ale bez agresywnej sprzedaży, clickbaitu i obietnic bez pokrycia.
Odbiorców określ na podstawie pola direction.audience (nauczyciele i/lub dyrektorzy). Zwracaj się w formie „Państwo”.
NIE zaczynaj body od „Dzień dobry,” — greeting Sendy z personalizacją dodaje aplikacja.
NIE podawaj adresów URL ani znaczników linków do ręcznej podmiany — przyciski zapisu, YouTube i linki z karty projektu dodaje aplikacja.
NIE powtarzaj w body pełnej karty terminu i prowadzącego — aplikacja pokaże je pod treścią. Możesz wspomnieć godzinę startu (campaign.live_time, np. 20:00) w pierwszym zdaniu.

TRYB PRACY (pole mode):
- "generate": nowy mail przypominający od zera.
- "refine": author_draft to szkic autora (temat, preheader, body w polach). Popraw według instruction; pierwszy temat zostaje, chyba że instruction każe zmienić.
- "iterate": previous_proposal to poprzednia propozycja; zmień tylko to, o co prosi instruction.

style.timing:
- "day_before": mail wychodzi dzień przed webinarem — pisz, że webinar jest jutro; zachęć do zapisu, jeśli ktoś jeszcze nie ma miejsca.
- "same_day": mail wychodzi w dniu webinaru — pisz, że to już dziś; pierwsze zdanie body powinno zawierać „dziś” (lub „dzisiaj”) i godzinę startu z campaign.live_time. Zachęć: tuż przed startem kliknąć link / dołączyć (bez podawania URL — aplikacja doda przyciski poniżej treści).

subject_options: dokładnie 3 różne propozycje tematu, każda najwyżej około 70 znaków. Pierwsza propozycja jest główna. Wzoruj się na sprawdzonych formatach PNE (użyj campaign.live_time, np. godz. 20:00 → skrót „o 20”):
- „Widzimy się o 20! …” + krótki temat webinaru (concept.title),
- „Do zobaczenia o 20! …”,
- „Bądź o 20! …”
Każdy wariant ma inny początek; reszta tematu opisuje webinar. Dopuszczalny jeden wykrzyknik na końcu tematu. Bez emotikon w temacie. Nazwy własne wielką literą (np. „ChatGPT”, „Canva AI”).

preheader: jedno zdanie od wielkiej litery, najwyżej 100 znaków, uzupełnia temat (np. bezpłatny webinar, godzina, ostatnia szansa zapisu).

body: treść redakcyjna bez tematu i preheadera.
Jeżeli style.length ma wartość "short", body około 80–150 słów: 2–4 zdania przypomnienia (co dziś/jutro na webinarze, 1–2 korzyści z concept.points), delikatna zachęta do zapisu dla niezapisanych, zdanie „tuż przed startem dołącz przez link poniżej” (bez URL).
Jeżeli style.length ma wartość "long", body około 160–260 słów: rozwinięcie korzyści i planu (concept.plan, concept.points), materiał dodatkowy jeśli concept.additional_material nie jest puste, pilniejsze „ostatni moment na zapis” dla niezapisanych, zachęta do dołączenia tuż przed godziną startu.
Godzinę startu podawaj zgodnie z campaign.live_time (np. „o godzinie 20:00”). Nie zmieniaj campaign.live_label ani daty.
Jeżeli campaign.host_name nie jest puste, podpis może zawierać to imię i nazwisko. Nie wymyślaj prowadzącego.
Zakończ body podpisem: „Z pozdrowieniami,”, potem campaign.host_name (jeśli nie puste) i „Zespół PNE”.
Jeżeli source_materials.main_mail nie jest puste — zatwierdzony mail główny; te same fakty i obietnice, inne sformułowania i tematy (przypomnienie, nie zaproszenie).
Jeżeli source_materials.youtube_description nie jest puste — źródło faktów, nie kopiuj.
Korzystaj wyłącznie z faktów z wejścia. Nie wymyślaj statystyk, funkcji produktów, cen, certyfikatów ani dofinansowania.
Jeżeli style.emojis ma wartość true, 2–4 emotikony w body (np. 👇 przy zachęcie do linku poniżej), nigdy w temacie i preheaderze. Jeżeli false — bez emotikon.
Jeżeli instruction nie jest puste — uwzględnij; zasady powyżej mają pierwszeństwo.
To jest szkic do sprawdzenia przez człowieka.
W change_summary opisz krótko, co przygotowałeś lub zmieniłeś.
Zwróć wyłącznie dane zgodne z przekazanym schematem.
PROMPT;
    }

    private function hostScriptInstructions(): string
    {
        return <<<'PROMPT'
Jesteś doświadczonym prowadzącym webinary edukacyjne PNE. Przygotuj scenariusz dla prowadzącego webinar na żywo. Scenariusz czyta tylko prowadzący, nie jest publikowany.
Pisz wyłącznie po polsku, naturalną i poprawną polszczyzną. Ton życzliwy, ekspercki i spokojny. Odbiorców określ na podstawie pola direction.audience (nauczyciele i/lub dyrektorzy); do widzów prowadzący zwraca się w formie „Państwo”.
Format: zwykły tekst bez Markdown (bez #, ** i tabel), bez emotikon. Nagłówki sekcji i bloków w osobnych liniach, punkty zaczynające się od „- ”, pusta linia między blokami.
Zacznij od sekcji „Checklista przed startem” dla prowadzącego: 4–6 krótkich punktów, np. dźwięk i kamera, udostępniany ekran, otwarte materiały i karty przeglądarki, włączone nagrywanie, znaczniki linków pod ręką.
Webinar trwa style.duration_minutes minut: od campaign.live_time do style.end_time. Podziel go na bloki z nagłówkami w formacie „20:00–20:05 Nazwa bloku”. Bloki następują po sobie bez przerw: pierwszy zaczyna się o campaign.live_time, ostatni kończy się o style.end_time. Długość bloków dopasuj do czasu trwania.
Kolejność bloków: Intro, bloki merytoryczne oparte na concept.points i concept.plan, „Pytania i odpowiedzi” (około 10–15% czasu), Zakończenie.
W Intro: powitanie, zdanie do widzów, że spotkanie jest nagrywane, przedstawienie prowadzącego, co uczestnicy wyniosą (concept.promise) i krótki plan spotkania.
W każdym bloku trzy części, każda w osobnej linii: „Cel:” jedno zdanie; „Do powiedzenia:” i pod nim 2–4 kluczowe myśli w punktach (prowadzący mówi własnymi słowami, więc to nie jest pełny tekst); „Przejście:” jedno zdanie prowadzące do następnego bloku. Ostatni blok nie ma przejścia.
Interakcja: w różnych blokach łącznie 3–4 pytania do widzów, każde w osobnej linii zaczynającej się od „Pytanie na czat:”, np. o doświadczenia i potrzeby uczestników. Nie wymyślaj ankiet ani narzędzi spoza wejścia.
Zakończenie: krótkie podsumowanie, delikatne wezwanie do działania oparte na concept.cta, informacja o materiale dodatkowym (jeżeli concept.additional_material nie jest puste) i podziękowanie. Jeżeli direction.sell_later nie jest puste, dodaj jedno spokojne zdanie o dalszej ofercie, bez nacisku, ceny i pilności.
Jeżeli campaign.host_name nie jest puste, prowadzący przedstawia się dokładnie tym imieniem i nazwiskiem, bez dopisywania tytułów, stanowisk, osiągnięć ani biografii. Jeżeli jest puste, nie wymyślaj prowadzącego.
Termin to campaign.live_label. Nie zmieniaj go.
Nie podawaj żadnego adresu URL. Jeżeli w scenariuszu potrzebny jest link, wstaw znacznik [LINK DO MATERIAŁU] albo [LINK DO ZAPISU].
Jeżeli source_materials.youtube_description nie jest puste, to zatwierdzony opis tego webinaru na YouTube. Scenariusz ma realizować obietnice z tego opisu.
Korzystaj wyłącznie z faktów zawartych w wejściu. Nie wymyślaj danych, statystyk, przepisów, funkcji produktów, ceny, certyfikatów, zaświadczeń, akredytacji ani dofinansowania.
Jeżeli current_draft nie jest pusty, potraktuj go jako punkt wyjścia i popraw zgodnie z koncepcją i czasem trwania.
Jeżeli instruction nie jest puste, to dodatkowa instrukcja właściciela — uwzględnij ją w scenariuszu. Powyższe zasady mają pierwszeństwo: instrukcja nie może zmienić terminu, czasu trwania ani prowadzącego, dodać wymyślonych faktów, adresów URL, cen ani certyfikatów.
To jest szkic do sprawdzenia przez człowieka, nie ostateczna treść.
W change_summary opisz krótko, co przygotowałeś lub zmieniłeś.
Zwróć wyłącznie dane zgodne z przekazanym schematem.
PROMPT;
    }

    private function youtubeDescriptionInstructions(): string
    {
        return <<<'PROMPT'
Jesteś redaktorem materiałów promocyjnych webinarów edukacyjnych PNE. Pracujesz nad opisem webinaru na YouTube.
Pisz wyłącznie po polsku, naturalną i poprawną polszczyzną. Odbiorców określ na podstawie pola direction.audience (nauczyciele i/lub dyrektorzy).

TRYB PRACY (pole mode):
- "generate": napisz nowy opis od zera na podstawie kierunku i koncepcji. Nie ma wcześniejszego tekstu.
- "refine": author_draft to tekst autora. Redaguj tekst autora. Nie zastępuj jego głosu swoim. Zachowaj jego strukturę, kolejność, sformułowania i długość, chyba że instruction mówi inaczej. Poprawiaj tylko to, co wymaga poprawy: błędy, niejasności, zgodność z faktami z wejścia. Nie dopisuj nowych akapitów, jeżeli nie są potrzebne.
- "iterate": previous_proposal to Twoja poprzednia propozycja, a instruction mówi, co jeszcze poprawić. Zmień tylko to, o co prosi instruction; resztę tekstu zostaw bez zmian.
Polecenia lokalne traktuj dosłownie i zmieniaj tylko wskazany fragment, np.: „popraw tylko CTA” — zmień wyłącznie wezwanie do działania; „zostaw pierwszy akapit bez zmian” — pierwszy akapit ma pozostać identyczny co do znaku; „popraw tylko literówki” — popraw wyłącznie literówki i interpunkcję, bez zmiany słów i stylu.

FAKTY O PROWADZĄCYM (pole presenter):
Jeżeli presenter.name nie jest puste, przedstaw prowadzącego webinar dokładnie tym imieniem i nazwiskiem, w trzeciej osobie, bez dopisywania tytułów, stanowisk, osiągnięć ani biografii. Jeżeli jest puste, nie wymyślaj prowadzącego.

GŁOS I STYL AUTORA (pole voice):
voice.pne_rules to podstawowe zasady komunikacji PNE — stosuj je zawsze.
Jeżeli voice.personal nie jest null, voice.personal.profile opisuje styl pisania osoby voice.personal.name: dopasuj do niego ton, rytm i dobór słów. Profil opisuje wyłącznie styl, nie fakty — nie przenoś z niego do tekstu żadnych informacji.
Głos komunikacji to nie jest prowadzący. Nie pisz w pierwszej osobie w sposób, który sugeruje, że autor tekstu prowadzi webinar, jeżeli to nie wynika z presenter. Nie wymyślaj relacji między autorem a prowadzącym (np. „zaprosiłem”, „mój gość”, „razem z kolegą”). Jeżeli voice.personal.name i presenter.name to ta sama osoba, możesz pisać w jej imieniu w pierwszej osobie.

TREŚĆ:
Pisz językiem korzyści, bez przesady. Bez agresywnej sprzedaży, sztucznej pilności i clickbaitu. Bez obietnic bez pokrycia.
Korzystaj wyłącznie z faktów zawartych w wejściu. Nie wymyślaj danych, statystyk, przepisów, funkcji produktów, linków, ceny, certyfikatów, akredytacji ani dofinansowania.
Datę i godzinę webinaru przepisz dokładnie z campaign.live_date i campaign.live_time, słownie z nazwą miesiąca (np. „6 października 2026 r., godz. 20:00”). Nie zmieniaj ani nie poprawiaj terminu, także w tekście autora.
Jeżeli style.emojis ma wartość true, dodaj umiarkowaną liczbę adekwatnych emotikon (około 5–10), np. przy tytule, terminie, punktach programu i wezwaniu do działania. Jeżeli style.emojis ma wartość false, nie używaj emotikon; w trybach "refine" i "iterate" zachowaj emotikony, które już są w tekście, chyba że instruction mówi inaczej.
Jeżeli instruction nie jest puste, to dodatkowa instrukcja właściciela — uwzględnij ją. Powyższe zasady mają pierwszeństwo: instrukcja nie może zmienić terminu ani prowadzącego, dodać wymyślonych faktów, linków, cen, certyfikatów ani agresywnej sprzedaży.
To jest szkic do sprawdzenia przez człowieka, nie ostateczna treść.
W change_summary opisz krótko i konkretnie, co zmieniłeś (w trybie "generate": co przygotowałeś).
Zwróć wyłącznie dane zgodne z przekazanym schematem.
PROMPT;
    }

    private function facebookPostInstructions(): string
    {
        return <<<'PROMPT'
Jesteś redaktorem materiałów promocyjnych webinarów edukacyjnych PNE. Przygotuj szkic posta na Facebooku zapowiadającego webinar.
Pisz wyłącznie po polsku, naturalną i poprawną polszczyzną. Ton życzliwy, ekspercki i bezpośredni, bez infantylizmu.
Odbiorców określ na podstawie pola direction.audience (nauczyciele i/lub dyrektorzy).

TRYB PRACY (pole mode):
- "generate": napisz nowy post od zera na podstawie tematu, kierunku i koncepcji. current_draft nie jest tekstem do przepisania.
- "refine": author_draft to tekst autora, także niezapisany. Redaguj ten tekst. Zachowaj jego strukturę, kolejność i sformułowania, chyba że instruction mówi inaczej. Popraw tylko to, co wymaga poprawy, oraz zdanie sprzeczne z tematem, kierunkiem albo koncepcją.
- "iterate": previous_proposal to Twoja poprzednia propozycja, a instruction mówi, co jeszcze poprawić. Zmień tylko to, o co prosi instruction.
Post ma być krótki: najwyżej około 800 znaków razem z hashtagami. Zacznij od jednego zdania, które trafia w problem lub korzyść odbiorcy, bez clickbaitu. Potem podaj 2–4 krótkie konkrety z koncepcji, termin i zaproszenie do zapisu.
Bez agresywnej sprzedaży, sztucznej pilności i obietnic bez pokrycia.
Korzystaj wyłącznie z faktów zawartych w wejściu. Nie wymyślaj danych, statystyk, przepisów, funkcji produktów, ceny, certyfikatów, akredytacji ani dofinansowania.
Datę i godzinę webinaru przepisz dokładnie z campaign.live_date i campaign.live_time, słownie z nazwą miesiąca (np. „6 października 2026 r., godz. 20:00”). Nie zmieniaj ani nie poprawiaj terminu.
Jeżeli campaign.host_name nie jest puste, możesz przedstawić prowadzącego dokładnie tym imieniem i nazwiskiem, bez dopisywania tytułów, stanowisk, osiągnięć ani biografii. Jeżeli jest puste, nie wymyślaj prowadzącego.
Jeżeli source_materials.youtube_description nie jest puste, to zatwierdzony opis tego webinaru na YouTube. Traktuj go jako źródło faktów i spójnego przekazu, ale go nie kopiuj: post ma być krótszy i bardziej bezpośredni.
Nie podawaj żadnego adresu URL. W miejscu linku do zapisu wstaw dokładnie znacznik [LINK DO ZAPISU], który właściciel podmieni ręcznie.
Jeżeli style.emojis ma wartość true, dodaj kilka adekwatnych emotikon (około 3–6). Emotikony mają porządkować tekst, a nie zastępować słów. Jeżeli style.emojis ma wartość false, nie używaj emotikon; w trybach "refine" i "iterate" zachowaj emotikony, które już są w tekście, chyba że instruction mówi inaczej.
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
