<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime;

use Closure;
use Fundly\Integration\Ports\CoreBanking\CoreBankingPort;
use Fundly\Integration\Ports\CoreBanking\Operations;
use Fundly\Integration\Runtime\Errors\ErrorClass;
use Fundly\Integration\Runtime\Errors\IntegrationException;
use Fundly\Integration\Runtime\Errors\RequiresInterventionError;
use Fundly\Integration\Runtime\Errors\RetryableError;
use Fundly\Integration\Runtime\Resilience\CircuitBreaker;
use Fundly\Integration\Runtime\Resilience\RetryPolicy;
use Fundly\Integration\Runtime\Resilience\Sleeper;
use Fundly\Shared\Clock\Clock;
use Throwable;

/**
 * The only way modules reach an external system (TRD §1.2, §11). For each call:
 * resolve the binding → check the capability manifest → consult the circuit
 * breaker → invoke the adapter → map unknown failures to requires_intervention
 * (never success) → log the call (masked) → inline-retry reads with
 * exponential backoff + full jitter. State-changing operations are never
 * retried inline; they go through the outbox with lookup-before-retry.
 */
final class IntegrationGateway
{
    public function __construct(
        private readonly BindingResolver $bindings,
        private readonly AdapterRegistry $registry,
        private readonly CircuitBreaker $breaker,
        private readonly IntegrationCallLog $log,
        private readonly RetryPolicy $retry,
        private readonly Sleeper $sleeper,
        private readonly Clock $clock,
    ) {
    }

    /**
     * @template T
     *
     * @param  Closure(CoreBankingPort): T  $call
     * @param  array<string, mixed>  $requestForLog
     * @return T
     */
    public function coreBanking(string $operation, Closure $call, array $requestForLog = [], ?string $idempotencyKey = null, ?string $legalEntityId = null): mixed
    {
        return $this->call(CoreBankingPort::PORT, $operation, Operations::policy($operation), function (object $adapter) use ($call): mixed {
            assert($adapter instanceof CoreBankingPort);

            return $call($adapter);
        }, $requestForLog, $idempotencyKey, $legalEntityId);
    }

    /**
     * @template T
     *
     * @param  Closure(object): T  $call
     * @param  array<string, mixed>  $requestForLog
     * @return T
     */
    public function call(string $port, string $operation, OperationPolicy $policy, Closure $call, array $requestForLog = [], ?string $idempotencyKey = null, ?string $legalEntityId = null): mixed
    {
        $binding = $this->bindings->resolve($port, $legalEntityId);
        $definition = $this->registry->get($binding->adapter_key);
        $manifest = $definition->manifest;

        if ($manifest->support($operation) === OperationSupport::Unsupported) {
            // Routed to the configured substitute, never to silent failure (FR-CBA-003/004).
            throw new RequiresInterventionError(
                self::codePrefix($port).'.CAPABILITY.UNSUPPORTED',
                "Operation {$operation} is not supported by adapter {$definition->key}; substitute: {$manifest->substitute($operation)}.",
            );
        }

        $breakerKey = $binding->id.':'.$operation;
        $adapter = ($definition->factory)($binding);
        $maxAttempts = $policy->stateChanging ? 1 : 1 + $policy->inlineRetries;
        $attempt = 0;

        while (true) {
            $attempt++;
            $started = $this->clock->now();
            $t0 = (int) hrtime(true);

            if (! $this->breaker->allows($breakerKey)) {
                $this->log->record($binding->id, $port, $operation, $definition->key, $definition->version, $manifest->contractVersion, $idempotencyKey, $attempt, $requestForLog, null, 'short_circuited', self::codePrefix($port).'.TRANSPORT.CIRCUIT_OPEN', $started, $this->clock->now(), 0);
                throw new RetryableError(self::codePrefix($port).'.TRANSPORT.CIRCUIT_OPEN', "Circuit for {$operation} is open; the provider is failing.");
            }

            try {
                $result = $call($adapter);
            } catch (IntegrationException $e) {
                $this->afterFailure($e, $breakerKey, $binding->id, $port, $operation, $definition, $idempotencyKey, $attempt, $requestForLog, $started, $t0);
                if ($e->errorClass() === ErrorClass::Retryable && $attempt < $maxAttempts && ! $e->unknownOutcome) {
                    $this->sleeper->sleepMs($this->retry->delayMs($attempt));

                    continue;
                }
                throw $e;
            } catch (Throwable $e) {
                // Anything unexpected from an adapter is an unknown outcome: never treated as success.
                $wrapped = new RequiresInterventionError(self::codePrefix($port).'.ADAPTER.UNEXPECTED_ERROR', 'Adapter raised an unexpected error: '.$e::class, $policy->stateChanging, $e);
                $this->afterFailure($wrapped, $breakerKey, $binding->id, $port, $operation, $definition, $idempotencyKey, $attempt, $requestForLog, $started, $t0);
                throw $wrapped;
            }

            $this->breaker->recordSuccess($breakerKey);
            $this->log->record($binding->id, $port, $operation, $definition->key, $definition->version, $manifest->contractVersion, $idempotencyKey, $attempt, $requestForLog, Payload::forLog($result), 'success', null, $started, $this->clock->now(), self::elapsedMs($t0));

            return $result;
        }
    }

    /** @param array<string, mixed> $requestForLog */
    private function afterFailure(IntegrationException $e, string $breakerKey, string $bindingId, string $port, string $operation, AdapterDefinition $def, ?string $idempotencyKey, int $attempt, array $requestForLog, \DateTimeImmutable $started, int $t0): void
    {
        // Business rejections mean the provider is healthy; they do not trip the breaker.
        if ($e->errorClass() !== ErrorClass::BusinessRejection) {
            $this->breaker->recordFailure($breakerKey, $e->canonicalCode);
        }
        $this->log->record($bindingId, $port, $operation, $def->key, $def->version, $def->manifest->contractVersion, $idempotencyKey, $attempt, $requestForLog, ['error' => $e->getMessage()], $e->errorClass()->value, $e->canonicalCode, $started, $this->clock->now(), self::elapsedMs($t0));
    }

    /** Canonical code prefix per port: CBA for core banking, otherwise the port name. */
    public static function codePrefix(string $port): string
    {
        return $port === CoreBankingPort::PORT ? 'CBA' : strtoupper((string) preg_replace('/[^A-Za-z0-9]/', '', $port));
    }

    private static function elapsedMs(int $t0): int
    {
        return intdiv((int) hrtime(true) - $t0, 1_000_000);
    }
}
