<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Application\Packs;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Compliance\Domain\Packs\DefaultPacks;
use Fundly\Modules\Compliance\Infrastructure\Models\RegulatoryLimitCheckRecord;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Money\Money;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;

final class PackQueries
{
    public function __construct(
        private readonly PackFile $packs,
        private readonly ApplicationReader $applications,
        private readonly AuthorizationGate $gate,
        private readonly AuthorityDirectory $directory,
        private readonly Clock $clock,
    ) {}

    /** @return array{data: array<string, mixed>} the rule register in force for a booking entity (FR-CMP-003) */
    public function register(string $legalEntityId, Principal $principal): array
    {
        $le = $this->packs->legalEntity($legalEntityId) ?? throw new NotFound('Legal entity not found.');
        $this->gate->authorize($principal, 'config:read', new ResourceAttributes(legalEntityId: $le->id, entityType: 'legal_entity', entityId: $le->id));
        $resolved = $this->packs->pack($le->id);
        $prudential = $this->packs->prudential($le->id);
        $now = $this->clock->now();
        $entry = $prudential === null ? null : $prudential[0]->at($now);
        $rules = [];
        if ($resolved !== null) {
            [$pack] = $resolved;
            foreach ($pack->rules() as $r) {
                $inForce = $pack->ruleAt((string) $r['code'], $now);
                $rules[] = [
                    'code' => (string) $r['code'], 'title' => (string) $r['title'], 'kind' => (string) $r['kind'],
                    'parameters' => is_array($r['parameters'] ?? null) ? $r['parameters'] : null, 'citation' => (array) $r['citation'],
                    'effective_from' => (string) $r['effective_from'], 'effective_to' => isset($r['effective_to']) ? (string) $r['effective_to'] : null,
                    'verification_status' => (string) $r['verification_status'], 'verified_by' => isset($r['verified_by']) ? (string) $r['verified_by'] : null,
                    'verified_on' => isset($r['verified_on']) ? (string) $r['verified_on'] : null, 'note' => isset($r['note']) ? (string) $r['note'] : null,
                    'in_force' => $inForce !== null && ($inForce['effective_from'] ?? null) === $r['effective_from'],
                ];
            }
        }

        return ['data' => [
            'legal_entity' => ['id' => $le->id, 'code' => $le->code, 'name' => $le->name, 'jurisdiction' => $le->jurisdiction, 'licence_category' => $le->licenceCategory],
            'pack' => $resolved === null ? null : [
                'key' => $resolved[2], 'name' => $resolved[0]->name(), 'version_id' => $resolved[1]->id, 'version_no' => $resolved[1]->versionNo,
                'activated_at' => $resolved[1]->activatedAt?->format(DATE_ATOM),
                'unverified_rules' => count(array_filter($rules, static fn (array $r): bool => $r['verification_status'] !== 'verified')),
            ],
            'prudential' => $entry === null ? null : ['effective_from' => $entry['effective_from'], 'shareholders_funds_unimpaired' => $entry['base']->toArray(), 'source' => $entry['source'], 'version_id' => $prudential[1]->id ?? null],
            'rules' => $rules,
        ]];
    }

    /** @return array{data: list<array{key: string, content: array<string, mixed>}>} draft packs to author from */
    public function defaults(): array
    {
        return ['data' => array_values(array_map(static fn (string $key, array $content): array => ['key' => $key, 'content' => $content], array_keys(DefaultPacks::all()), DefaultPacks::all()))];
    }

    /** @return array{data: list<array<string, mixed>>} */
    public function limitChecks(string $applicationId, Principal $principal): array
    {
        $app = $this->applications->find($applicationId) ?? throw new NotFound('Application not found.');
        $this->gate->authorize($principal, 'application:view', new ResourceAttributes(legalEntityId: $app->legalEntityId, orgUnitId: $app->orgUnitId, productId: $app->productId, segment: $app->segment, entityType: 'application', entityId: $app->id));
        $rows = RegulatoryLimitCheckRecord::query()->where('application_id', $app->id)->orderByDesc('checked_at')->limit(20)->get();
        $names = $this->directory->names(array_values(array_unique($rows->pluck('checked_by')->all())));
        $m = static fn (?string $amount, ?string $currency): ?array => $amount === null || $currency === null ? null : Money::of($amount, $currency)->toArray();

        return ['data' => array_values($rows->map(static fn (RegulatoryLimitCheckRecord $r): array => [
            'id' => $r->id, 'limit_type' => $r->limit_type, 'stage' => $r->stage, 'outcome' => $r->outcome, 'detail' => $r->detail,
            'rule' => $r->rule_ref, 'base' => $r->base_ref,
            'figures' => $r->limit_amount === null ? null : [
                'base' => $m($r->base_amount, $r->currency), 'limit_percent' => $r->limit_percent, 'limit' => $m($r->limit_amount, $r->currency),
                'existing' => $m($r->existing_amount, $r->currency), 'proposed' => $m($r->proposed_amount, $r->currency), 'total' => $m($r->total_amount, $r->currency),
            ],
            'facilities' => $r->facilities,
            'checked_by' => ['id' => $r->checked_by, 'name' => $names[$r->checked_by] ?? null], 'checked_at' => $r->checked_at->toIso8601String(),
        ])->all())];
    }
}
