<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Resilience;

use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Contracts\Redis\Factory as Redis;

/**
 * Breaker state in Redis (TRD §11), shared by all workers. Uses WATCH/MULTI
 * optimistic locking; retries a few times on contention.
 */
final class RedisBreakerStore implements BreakerStore
{
    public function __construct(private readonly Redis $redis, private readonly TenantContext $tenant)
    {
    }

    public function update(string $key, callable $mutate): array
    {
        $conn = $this->redis->connection();
        $rkey = $this->key($key);
        for ($i = 0; $i < 5; $i++) {
            $conn->command('watch', [$rkey]);
            $before = $this->decode($conn->command('get', [$rkey]));
            $after = $mutate($before);
            $conn->command('multi');
            $conn->command('set', [$rkey, json_encode(['s' => $after->state, 'f' => $after->consecutiveFailures, 'o' => $after->openedAt], JSON_THROW_ON_ERROR)]);
            $result = $conn->command('exec');
            if ($result !== false && $result !== null) {
                return [$before, $after];
            }
        }
        $state = $this->get($key);

        return [$state, $state];
    }

    public function get(string $key): BreakerState
    {
        return $this->decode($this->redis->connection()->command('get', [$this->key($key)]));
    }

    private function key(string $key): string
    {
        return 'fundly:breaker:'.$this->tenant->requireId().':'.$key;
    }

    private function decode(mixed $raw): BreakerState
    {
        $d = is_string($raw) ? json_decode($raw, true) : null;
        if (! is_array($d)) {
            return BreakerState::closed();
        }

        return new BreakerState((string) ($d['s'] ?? BreakerState::CLOSED), (int) ($d['f'] ?? 0), isset($d['o']) && is_int($d['o']) ? $d['o'] : null);
    }
}
