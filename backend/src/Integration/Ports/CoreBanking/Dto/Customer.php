<?php

declare(strict_types=1);

namespace Fundly\Integration\Ports\CoreBanking\Dto;

/**
 * Canonical DTO, CBI v1.0 (integration register §2.1).
 *
 * Canonical customer (identity, contacts, addresses, segment, KYC tier, relationships).
 */
final readonly class Customer
{
    /**
     * @param  array<string, string>  $identities
     * @param  array<string, string>  $contacts
     * @param  list<array<string, string>>  $addresses
     * @param  list<array<string, string>>  $relationships
     */
    public function __construct(
        public string $cbaCustomerId,
        public string $kind,
        public string $name,
        public array $identities = [],
        public array $contacts = [],
        public array $addresses = [],
        public ?string $segment = null,
        public ?string $kycTier = null,
        public array $relationships = [],
        public ?string $losPartyId = null,
    ) {}
}
