<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime\Resilience;

interface BreakerStore
{
    /**
     * Atomically read-modify-write the breaker state.
     *
     * @param  callable(BreakerState): BreakerState  $mutate
     * @return array{0: BreakerState, 1: BreakerState} [before, after]
     */
    public function update(string $key, callable $mutate): array;

    public function get(string $key): BreakerState;
}
