<?php

declare(strict_types=1);

use Fundly\Modules\Access\Infrastructure\Models\User;

/*
 * Staff authenticate through the `web` session guard (used by Sanctum's
 * stateful SPA mode); service/partner principals through Sanctum tokens.
 * Password reset by email is not part of P0 (administrator-driven resets).
 */
return [
    'defaults' => [
        'guard' => 'web',
        'passwords' => 'users',
    ],

    'guards' => [
        'web' => [
            'driver' => 'session',
            'provider' => 'users',
        ],
    ],

    'providers' => [
        'users' => [
            'driver' => 'eloquent',
            'model' => User::class,
        ],
    ],

    'passwords' => [
        'users' => [
            'provider' => 'users',
            'table' => 'password_reset_tokens',
            'expire' => 60,
            'throttle' => 60,
        ],
    ],

    'password_timeout' => 300,
];
