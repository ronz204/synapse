<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Measurement account password
    |--------------------------------------------------------------------------
    |
    | Used only by `php artisan perf:measure --layer=browser`, which signs in
    | through the real login form so the numbers it reports come from a real
    | session. Lives in config rather than being read from env at the call site
    | so a cached config does not silently turn it into null.
    |
    */

    'measure_password' => env('PERF_MEASURE_PASSWORD', 'password'),

];
