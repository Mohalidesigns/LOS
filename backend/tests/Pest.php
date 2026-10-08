<?php

declare(strict_types=1);

use Tests\Support\CommitsToDatabase;
use Tests\Support\RefreshesPostgres;
use Tests\TestCase;

/*
 * Unit:         pure domain/kernel tests, no framework.
 * Feature:      HTTP + application tests against real PostgreSQL, each test in a rolled-back transaction.
 * Database:     tests that need committed data across two connections (owner vs runtime role),
 *               e.g. RLS and audit tamper detection; tables are truncated afterwards.
 * Architecture: static rules (layering, vendor identifiers, routes, floats).
 */
pest()->extend(TestCase::class)->use(RefreshesPostgres::class)->in('Feature');
pest()->extend(TestCase::class)->use(CommitsToDatabase::class)->in('Database');
pest()->extend(TestCase::class)->in('Architecture');
