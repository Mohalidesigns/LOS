<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Contracts;

/** One rule from the active pack, with what a decision must record to be re-evaluated later (FR-CMP-003). */
final readonly class RegulatoryRule
{
    /**
     * @param  array<string, mixed>  $parameters
     * @param  array<string, mixed>  $citation
     */
    public function __construct(
        public string $code,
        public string $title,
        public array $parameters,
        public array $citation,
        public string $effectiveFrom,
        public ?string $effectiveTo,
        public string $verificationStatus,
        public string $packKey,
        public string $packVersionId,
        public int $packVersionNo,
    ) {}

    /** @return array{code: string, pack: string, pack_version_id: string, pack_version_no: int, effective_from: string, verification_status: string, citation: array<string, mixed>} */
    public function reference(): array
    {
        return ['code' => $this->code, 'pack' => $this->packKey, 'pack_version_id' => $this->packVersionId, 'pack_version_no' => $this->packVersionNo, 'effective_from' => $this->effectiveFrom, 'verification_status' => $this->verificationStatus, 'citation' => $this->citation];
    }

    public function int(string $key, int $default): int
    {
        $v = $this->parameters[$key] ?? null;

        return is_int($v) ? $v : $default;
    }
}
