<?php

$enabled = filter_var(
    env('PNE_GROWTH_OS_ENABLED', false),
    FILTER_VALIDATE_BOOLEAN,
    FILTER_NULL_ON_FAILURE
);

return [
    /*
    |--------------------------------------------------------------------------
    | PNE Growth OS
    |--------------------------------------------------------------------------
    |
    | Fail closed: brak zmiennej lub wartość inna niż prawidłowy boolean
    | wyłącza cały moduł.
    |
    */
    'enabled' => $enabled ?? false,
];
