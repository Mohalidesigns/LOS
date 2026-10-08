<?php

declare(strict_types=1);

namespace Fundly\Shared\Http;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Http\JsonResponse;
use Throwable;

/**
 * /health: liveness (the process is up). /ready: readiness, i.e. the database
 * is reachable as the runtime role, RLS is not bypassable by it, migrations
 * are applied and the cache answers (NFR-011). No tenant data is exposed.
 */
final class HealthController
{
    public function __construct(private readonly ConnectionInterface $db, private readonly Cache $cache, private readonly Migrator $migrator)
    {
    }

    public function health(): JsonResponse
    {
        return new JsonResponse(['status' => 'ok']);
    }

    public function ready(): JsonResponse
    {
        $checks = [];
        try {
            $role = $this->db->selectOne('select current_user as u, (select rolbypassrls or rolsuper from pg_roles where rolname = current_user) as privileged');
            $checks['database'] = is_object($role) ? 'ok' : 'fail';
            $checks['rls_enforced'] = is_object($role) && ! $role->privileged ? 'ok' : 'fail';
        } catch (Throwable) {
            $checks['database'] = 'fail';
        }
        try {
            $files = $this->migrator->getMigrationFiles(database_path('migrations'));
            $ran = $this->db->table('migrations')->count();
            $checks['migrations'] = $ran >= count($files) ? 'ok' : 'pending';
        } catch (Throwable) {
            $checks['migrations'] = 'fail';
        }
        try {
            $this->cache->put('fundly:ready', '1', 5);
            $checks['cache'] = $this->cache->get('fundly:ready') === '1' ? 'ok' : 'fail';
        } catch (Throwable) {
            $checks['cache'] = 'fail';
        }
        $ok = ! in_array(false, array_map(static fn (string $v): bool => $v === 'ok', $checks), true);

        return new JsonResponse(['status' => $ok ? 'ready' : 'not_ready', 'checks' => $checks], $ok ? 200 : 503);
    }
}
