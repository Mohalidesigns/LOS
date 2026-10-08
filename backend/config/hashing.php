<?php

declare(strict_types=1);

/*
 * Local identity store passwords use Argon2id (TRD §8.2, LOS-FR-302).
 * Cost parameters are environment-tunable; tests use cheaper settings.
 */
return [
    'driver' => 'argon2id',
    'bcrypt' => ['rounds' => (int) env('BCRYPT_ROUNDS', 12), 'verify' => true, 'limit' => null],
    'argon' => [
        'memory' => (int) env('ARGON_MEMORY', 65536),
        'threads' => (int) env('ARGON_THREADS', 1),
        'time' => (int) env('ARGON_TIME', 4),
        'verify' => true,
    ],
    'rehash_on_login' => true,
];
