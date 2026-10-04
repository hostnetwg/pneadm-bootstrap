<?php

$enabled = filter_var(
    env('GROWTH_AI_ENABLED', false),
    FILTER_VALIDATE_BOOLEAN,
    FILTER_NULL_ON_FAILURE
);

return [
    /*
    |--------------------------------------------------------------------------
    | PNE Growth OS — AI pilot
    |--------------------------------------------------------------------------
    |
    | Fail closed: brak zmiennej lub nieprawidłowy boolean wyłącza prawdziwe
    | AI. Growth OS i ręczna edycja pozostają dostępne.
    |
    */
    'enabled' => $enabled ?? false,
    'provider' => env('GROWTH_AI_PROVIDER', 'openai'),
    'model' => env('GROWTH_AI_MODEL', 'gpt-5-mini'),
    'prompt_version' => 'concept_revision_v1',
    'schema_version' => 'concept_revision_schema_v1',

    'timeout_seconds' => max(5, (int) env('GROWTH_AI_TIMEOUT_SECONDS', 60)),
    'reasoning_effort' => env('GROWTH_AI_REASONING_EFFORT', 'low'),

    /*
    | Asystent planowania kierunku (DEC-037). Osobny model tylko dla direction_planning.
    | Oficjalna ścieżka OpenAI dla web_search to Responses + gpt-5.5. Najmocniejszy
    | gpt-6-astra można ustawić tutaj, ale bywa, że zamiast wypełnić schemat, dopytuje.
    */
    'research' => [
        'model' => env('GROWTH_AI_RESEARCH_MODEL', 'gpt-5.5'),
        'timeout_seconds' => max(30, (int) env('GROWTH_AI_RESEARCH_TIMEOUT_SECONDS', 120)),
        'reasoning_effort' => env('GROWTH_AI_RESEARCH_REASONING_EFFORT', 'medium'),
        'input_per_million' => max(0, (float) env('GROWTH_AI_RESEARCH_INPUT_COST_PER_MILLION', 2.50)),
        'output_per_million' => max(0, (float) env('GROWTH_AI_RESEARCH_OUTPUT_COST_PER_MILLION', 15.00)),
    ],
    'limits' => [
        'max_output_tokens' => max(256, (int) env('GROWTH_AI_MAX_OUTPUT_TOKENS', 4000)),
        'max_input_chars' => max(1000, (int) env('GROWTH_AI_MAX_INPUT_CHARS', 12000)),
        'max_instruction_chars' => max(100, (int) env('GROWTH_AI_MAX_INSTRUCTION_CHARS', 1000)),
        'per_minute' => max(1, (int) env('GROWTH_AI_RATE_LIMIT_PER_MINUTE', 5)),
        'daily_per_user' => max(1, (int) env('GROWTH_AI_DAILY_LIMIT_PER_USER', 30)),
    ],

    'circuit' => [
        'failure_threshold' => max(1, (int) env('GROWTH_AI_CIRCUIT_FAILURE_THRESHOLD', 3)),
        'cooldown_seconds' => max(30, (int) env('GROWTH_AI_CIRCUIT_COOLDOWN_SECONDS', 300)),
    ],

    'log_channel' => env('GROWTH_AI_LOG_CHANNEL', 'growth_ai'),

    /*
    | Prices are configurable because changing the model can change pricing.
    | Defaults correspond to gpt-5-mini at the time of the pilot.
    */
    'cost' => [
        'input_per_million' => max(0, (float) env('GROWTH_AI_INPUT_COST_PER_MILLION', 0.25)),
        'output_per_million' => max(0, (float) env('GROWTH_AI_OUTPUT_COST_PER_MILLION', 2.00)),
    ],

    /*
    | Generator obrazu grafiki głównej (DEC-029, DEC-030). Ta sama flaga `enabled`;
    | osobny limit dzienny i obwód awaryjny. Ceny to szacunek USD za obraz
    | (gpt-image-2, jakość medium); wersja kwadratowa z poziomej płaci też za obraz wejściowy.
    */
    'images' => [
        'model' => env('GROWTH_AI_IMAGE_MODEL', 'gpt-image-2'),
        'quality' => env('GROWTH_AI_IMAGE_QUALITY', 'medium'),
        'timeout_seconds' => max(30, (int) env('GROWTH_AI_IMAGE_TIMEOUT_SECONDS', 180)),
        'daily_per_user' => max(1, (int) env('GROWTH_AI_IMAGE_DAILY_LIMIT_PER_USER', 10)),
        'disk' => env('GROWTH_AI_IMAGE_DISK', 'local'),
        'cost_per_image' => [
            'landscape' => max(0, (float) env('GROWTH_AI_IMAGE_COST_LANDSCAPE', 0.042)),
            'square' => max(0, (float) env('GROWTH_AI_IMAGE_COST_SQUARE', 0.053)),
            'square_from_landscape' => max(0, (float) env('GROWTH_AI_IMAGE_COST_ADAPT', 0.07)),
        ],
    ],
];
