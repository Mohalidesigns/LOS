<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Infrastructure;

use DateTimeImmutable;
use Fundly\Modules\Compliance\Application\Packs\PackFile;
use Fundly\Modules\Compliance\Contracts\RegulatoryRule;
use Fundly\Modules\Compliance\Contracts\RegulatoryRules;
use Fundly\Shared\Clock\Clock;

final class ConfigBackedRegulatoryRules implements RegulatoryRules
{
    public function __construct(private readonly PackFile $packs, private readonly Clock $clock) {}

    public function rule(string $legalEntityId, string $code, ?DateTimeImmutable $at = null): ?RegulatoryRule
    {
        $resolved = $this->packs->pack($legalEntityId);
        if ($resolved === null) {
            return null;
        }
        [$pack, $version, $key] = $resolved;
        $r = $pack->ruleAt($code, $at ?? $this->clock->now());

        return $r === null ? null : new RegulatoryRule(
            $code, (string) $r['title'], is_array($r['parameters'] ?? null) ? $r['parameters'] : [], is_array($r['citation'] ?? null) ? $r['citation'] : [],
            (string) $r['effective_from'], isset($r['effective_to']) ? (string) $r['effective_to'] : null, (string) $r['verification_status'],
            $key, $version->id, $version->versionNo,
        );
    }
}
