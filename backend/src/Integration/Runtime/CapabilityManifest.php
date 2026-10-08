<?php

declare(strict_types=1);

namespace Fundly\Integration\Runtime;

use InvalidArgumentException;

/**
 * What an adapter supports, per operation (TRD §11, register §2.4):
 * native / emulated / unsupported, with the substitution strategy
 * (manual_task, batch_file, los_compute) and idempotency mode.
 */
final readonly class CapabilityManifest
{
    public const SUBSTITUTES = ['manual_task', 'batch_file', 'los_compute'];

    /** @param array<string, array{support: string, idempotency?: string, substitute?: string}> $operations */
    public function __construct(
        public string $adapter,
        public string $version,
        public string $contractVersion,
        public string $processingLocation,
        public array $operations,
    ) {
        foreach ($operations as $op => $spec) {
            $support = OperationSupport::tryFrom($spec['support']) ?? throw new InvalidArgumentException("Operation {$op}: unknown support level.");
            if ($support === OperationSupport::Unsupported && ! in_array($spec['substitute'] ?? null, self::SUBSTITUTES, true)) {
                throw new InvalidArgumentException("Operation {$op} is unsupported and must declare a substitute.");
            }
        }
    }

    public function support(string $operation): OperationSupport
    {
        $spec = $this->operations[$operation] ?? null;

        return $spec === null ? OperationSupport::Unsupported : OperationSupport::from($spec['support']);
    }

    public function substitute(string $operation): string
    {
        return $this->operations[$operation]['substitute'] ?? 'manual_task';
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'adapter' => $this->adapter,
            'version' => $this->version,
            'cbi' => $this->contractVersion,
            'processing_location' => $this->processingLocation,
            'operations' => $this->operations,
        ];
    }
}
