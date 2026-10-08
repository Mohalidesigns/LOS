<?php

declare(strict_types=1);

namespace Fundly\Modules\Licensing\Application;

use Fundly\Integration\Ports\Licensing\LicensingPort;
use Fundly\Integration\Ports\Licensing\SignedLicence;
use Fundly\Modules\Access\Contracts\ChangeAction;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\Payload;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Id\UuidV7;
use Fundly\Shared\Json\CanonicalJson;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceRef;
use Illuminate\Database\ConnectionInterface;

/** Licence import via maker-checker (TRD §2.5 / §6.4). Signature verified at request and at execution. */
final class LicenceImportAction implements ChangeAction
{
    public const TYPE = 'licensing.licence.import';

    public function __construct(
        private readonly LicensingPort $port,
        private readonly ConnectionInterface $db,
        private readonly Clock $clock,
    ) {}

    public function type(): string
    {
        return self::TYPE;
    }

    public function makerPermission(): string
    {
        return 'licence:import_request';
    }

    public function checkerPermission(): string
    {
        return 'licence:import_approve';
    }

    public function entity(array $payload): ?ResourceRef
    {
        return null;
    }

    public function validate(array $payload, Principal $maker): void
    {
        $licence = $this->port->verify(new SignedLicence(Payload::string($payload, 'document'), Payload::string($payload, 'signature')));
        if ($licence->validTo <= $this->clock->now()) {
            throw new DomainRuleViolation('The licence has already expired.');
        }
        $current = $this->port->currentLicence();
        if ($current !== null && $current->licenceId === $licence->licenceId) {
            throw new DomainRuleViolation('This licence is already installed.');
        }
    }

    public function fingerprint(array $payload): string
    {
        return CanonicalJson::hash(['active' => $this->port->currentLicence()?->licenceId]);
    }

    public function execute(array $payload, string $changeRequestId, CommandContext $context): array
    {
        $previous = $this->port->currentLicence();
        $licence = $this->port->install(
            new SignedLicence(Payload::string($payload, 'document'), Payload::string($payload, 'signature')),
            $context->principal->tenantId,
            $context->principal->id,
            $changeRequestId,
        );
        $this->db->table('licence_events')->insert([
            'id' => UuidV7::generate(),
            'licence_id' => $licence->licenceId,
            'event' => 'imported',
            'details' => json_encode(['previous' => $previous?->licenceId, 'change_request_id' => $changeRequestId, 'valid_to' => $licence->validTo->format(DATE_ATOM)], JSON_THROW_ON_ERROR),
            'occurred_at' => $this->clock->now(),
        ]);
        $context->audit(new AuditEntry(
            action: 'licensing.licence.imported',
            entityType: 'licence',
            entityId: $licence->licenceId,
            before: $previous === null ? null : ['licence_id' => $previous->licenceId, 'valid_to' => $previous->validTo->format(DATE_ATOM)],
            after: $licence->toArray() + ['change_request_id' => $changeRequestId],
        ));

        return ['licence_id' => $licence->licenceId, 'valid_to' => $licence->validTo->format(DATE_ATOM)];
    }

    public function excludedCheckers(array $payload): array
    {
        return [];
    }
}
