<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default production stages
    |--------------------------------------------------------------------------
    |
    | Seeded into `job_stages` when a job is approved. Keyed by box shape
    | (box jobs) or `manual`; `default` is the fallback.
    |
    | PLACEHOLDER — the real per-box-type stage lists haven't been provided
    | yet. Replace these once the factory confirms them.
    |
    */

    'default_stages' => [
        'default' => ['طباعة', 'سلوفان', 'تكسير', 'لصق وتطبيق', 'مراجعة جودة', 'جاهز للتسليم'],
        'pillow_bag' => ['طباعة', 'سلوفان', 'تكسير', 'تطبيق يدوي', 'مراجعة جودة', 'جاهز للتسليم'],
        'manual' => ['طباعة', 'تشطيب', 'مراجعة جودة', 'جاهز للتسليم'],
    ],

];
