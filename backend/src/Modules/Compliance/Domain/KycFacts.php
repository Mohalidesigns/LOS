<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Domain;

/** Everything the CDD gate needs, assembled before evaluation (no I/O in the gate). */
final readonly class KycFacts
{
    /**
     * @param  list<array{party_id: string, name: string, role: string, type: string, verified_ids: list<string>, has_registration: bool, directors: list<array{party_id: string, name: string, verified_ids: list<string>}>, data_consent: bool}>  $parties
     * @param  list<string>  $screenedPartyIds  parties with a completed screening run for this application
     * @param  list<array{status: string, category: string}>  $alerts
     */
    public function __construct(
        public array $parties,
        public array $screenedPartyIds,
        public array $alerts,
    ) {}
}
