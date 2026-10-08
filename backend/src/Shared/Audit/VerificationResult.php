<?php

declare(strict_types=1);

namespace Fundly\Shared\Audit;

final class VerificationResult
{
    /** @var list<array{seq: int, kind: string, detail: string}> */
    private array $breaks = [];

    public int $eventsChecked = 0;

    public int $checkpointsChecked = 0;

    public ?string $headHash = null;

    public int $headSeq = 0;

    public function __construct(public readonly string $tenantId)
    {
    }

    public function addBreak(int $seq, string $kind, string $detail): void
    {
        $this->breaks[] = ['seq' => $seq, 'kind' => $kind, 'detail' => $detail];
    }

    public function ok(): bool
    {
        return $this->breaks === [];
    }

    /** @return list<array{seq: int, kind: string, detail: string}> */
    public function breaks(): array
    {
        return $this->breaks;
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'tenant_id' => $this->tenantId,
            'ok' => $this->ok(),
            'events_checked' => $this->eventsChecked,
            'checkpoints_checked' => $this->checkpointsChecked,
            'head_seq' => $this->headSeq,
            'head_hash' => $this->headHash,
            'breaks' => $this->breaks,
        ];
    }
}
