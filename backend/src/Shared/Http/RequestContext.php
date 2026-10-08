<?php

declare(strict_types=1);

namespace Fundly\Shared\Http;

use Fundly\Shared\Id\UuidV7;

/**
 * Per-request (or per-job) context propagated to the audit trail, logs and
 * outbound integration calls (NFR-011, FR-AUD-002).
 */
final class RequestContext
{
    private ?string $correlationId = null;

    private ?string $sourceIp = null;

    private ?string $userAgent = null;

    private ?string $deviceId = null;

    public function correlationId(): string
    {
        return $this->correlationId ??= UuidV7::generate();
    }

    public function setCorrelationId(string $id): void
    {
        $this->correlationId = $id;
    }

    public function setClient(?string $ip, ?string $userAgent, ?string $deviceId): void
    {
        $this->sourceIp = $ip;
        $this->userAgent = $userAgent === null ? null : mb_substr($userAgent, 0, 1000);
        $this->deviceId = $deviceId === null ? null : mb_substr($deviceId, 0, 128);
    }

    public function sourceIp(): ?string
    {
        return $this->sourceIp;
    }

    public function userAgent(): ?string
    {
        return $this->userAgent;
    }

    public function deviceId(): ?string
    {
        return $this->deviceId;
    }

    public function reset(): void
    {
        $this->correlationId = null;
        $this->sourceIp = $this->userAgent = $this->deviceId = null;
    }
}
