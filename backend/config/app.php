<?php

return [
    'name' => env('APP_NAME', 'ERP DPSIT'),
    'env' => env('APP_ENV', 'production'),
    'debug' => (bool) env('APP_DEBUG', false),
    'url' => env('APP_URL', 'http://localhost:8000'),
    'timezone' => env('APP_TIMEZONE', 'America/Argentina/Buenos_Aires'),
    'locale' => env('APP_LOCALE', 'es'),
    'fallback_locale' => env('APP_FALLBACK_LOCALE', 'en'),
    'faker_locale' => env('APP_FAKER_LOCALE', 'es_AR'),
    'cipher' => 'AES-256-CBC',
    'key' => env('APP_KEY'),
    'previous_keys' => [
        ...array_filter(
            explode(',', env('APP_PREVIOUS_KEYS', ''))
        ),
    ],

    // Denominación Institucional de la Dirección Rectora
    'agency' => [
        'name' => env('AGENCY_NAME', 'Dirección Provincial Rectora de Tecnología'),
        'short_name' => env('AGENCY_SHORT_NAME', 'DPSIT'),
        'acronym' => env('AGENCY_ACRONYM', 'DP'),
        'government' => env('AGENCY_GOVERNMENT', 'Poder Ejecutivo Provincial'),
        'tagline' => env('AGENCY_TAGLINE', 'Gobernanza TI Provincial'),
    ],
];
