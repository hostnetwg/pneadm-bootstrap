<?php

return [
    'cutoff_date' => env('INSTRUCTOR_SETTLEMENT_CUTOFF_DATE', env('TRAINER_SETTLEMENT_CUTOFF_DATE', '2026-05-01')),
];
