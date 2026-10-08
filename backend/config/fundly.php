<?php

declare(strict_types=1);

/*
 * Fundly LOS platform configuration.
 *
 * Values here are installation defaults. Several of them (session policy) can
 * be overridden per tenant at runtime by an *active* configuration artefact
 * that went through the draft → review → approved → active lifecycle with
 * maker-checker (FR-TEN-009).
 */
return [

    'installation' => [
        // 'production' or 'non_production'. Simulators refuse to bind in production
        // and fault scripts are ignored there (integration register §3).
        'environment' => env('FUNDLY_INSTALLATION_ENV', 'non_production'),
    ],

    'tenancy' => [
        // 'single': installation = tenant (D-033); the sole active tenant is used
        // to resolve unauthenticated requests such as login.
        // 'hostname': the tenant is resolved from the request host (future SaaS).
        'resolution' => env('FUNDLY_TENANCY_RESOLUTION', 'single'),
        'setting' => 'app.tenant_id',
    ],

    'database' => [
        // Runtime role that receives table grants from migrations.
        'app_role' => env('DB_USERNAME', 'fundly_app'),
        'owner_connection' => 'pgsql_owner',
    ],

    'auth' => [
        'mfa_required' => (bool) env('FUNDLY_MFA_REQUIRED', true),
        'mfa_issuer' => env('FUNDLY_MFA_ISSUER', 'Fundly LOS'),
        'lockout' => [
            // Consecutive failures before the account is locked.
            'threshold' => (int) env('FUNDLY_LOCKOUT_THRESHOLD', 5),
            // Lock duration grows exponentially: base * 2^(failures - threshold), capped.
            'base_minutes' => (int) env('FUNDLY_LOCKOUT_BASE_MINUTES', 1),
            'max_minutes' => (int) env('FUNDLY_LOCKOUT_MAX_MINUTES', 60),
        ],
        'rate_limit' => [
            'per_identity_per_minute' => (int) env('FUNDLY_LOGIN_RATE_IDENTITY', 5),
            'per_ip_per_minute' => (int) env('FUNDLY_LOGIN_RATE_IP', 20),
        ],
        'password' => [
            'min_length' => 12,
        ],
        'step_up_default_minutes' => (int) env('FUNDLY_STEP_UP_MINUTES', 5),
    ],

    // TRD §8.2 session controls. Overridable per tenant by the active
    // `security.session_policy` configuration artefact.
    'session' => [
        'idle_minutes' => (int) env('FUNDLY_SESSION_IDLE_MINUTES', 15),
        'absolute_minutes' => (int) env('FUNDLY_SESSION_ABSOLUTE_MINUTES', 480),
        'max_concurrent' => (int) env('FUNDLY_SESSION_MAX_CONCURRENT', 1),
    ],

    'delegation' => [
        'max_days' => (int) env('FUNDLY_DELEGATION_MAX_DAYS', 30),
    ],

    'api' => [
        'problem_type_base' => 'urn:fundly:problem:',
        'idempotency_ttl_hours' => 72,
        'page_size_default' => 25,
        'page_size_max' => 100,
    ],

    'audit' => [
        // Optional file sink for checkpoints (stand-in for the Object-Lock bucket / SIEM).
        'checkpoint_path' => env('FUNDLY_AUDIT_CHECKPOINT_PATH', storage_path('app/audit-checkpoints')),
        // Audit retention is independent of operational retention (FR-AUD-012).
        'retention_years' => (int) env('FUNDLY_AUDIT_RETENTION_YEARS', 10),
    ],

    // Keys whose values are masked in audit before/after, integration logs and
    // exports (FR-CMP-036). Matching is case-insensitive on the key name.
    'pii' => [
        'mask_keys' => [
            'bvn', 'nin', 'account_no', 'account_number', 'accountno', 'phone', 'phone_number', 'mobile',
            'email', 'date_of_birth', 'dob', 'passport_number', 'drivers_licence_number', 'tin',
            'address', 'residential_address', 'rc_number', 'rcnumber',
        ],
        'redact_keys' => [
            'password', 'password_confirmation', 'current_password', 'secret', 'mfa_secret', 'token',
            'plain_text_token', 'code', 'otp', 'signature', 'private_key', 'api_key', 'credentials',
        ],
    ],

    'crypto' => [
        // Local keyfile KEK (KeyManagementPort, MVP adapter). 32 random bytes, base64.
        // Supplied by a root-owned 0400 file or a secret store, never committed.
        'kek_path' => env('FUNDLY_KEK_PATH', storage_path('keys/kek.key')),
        'kek_id' => env('FUNDLY_KEK_ID', 'local-1'),
    ],

    'licence' => [
        // Ed25519 vendor public key (base64). Pinned per installation.
        'public_key' => env('FUNDLY_LICENCE_PUBLIC_KEY', ''),
        'warning_days' => [60, 30, 7],
        'cache_seconds' => 60,
    ],

    'integration' => [
        'breaker' => [
            // 'database' or 'redis'
            'store' => env('FUNDLY_BREAKER_STORE', 'database'),
            'failure_threshold' => (int) env('FUNDLY_BREAKER_FAILURES', 5),
            'open_seconds' => (int) env('FUNDLY_BREAKER_OPEN_SECONDS', 30),
        ],
        'retry' => [
            'max_attempts' => (int) env('FUNDLY_RETRY_MAX_ATTEMPTS', 5),
            'base_delay_ms' => (int) env('FUNDLY_RETRY_BASE_MS', 500),
            'max_delay_ms' => (int) env('FUNDLY_RETRY_MAX_MS', 300_000),
        ],
        'outbox' => [
            'batch_size' => 50,
        ],
    ],
];
