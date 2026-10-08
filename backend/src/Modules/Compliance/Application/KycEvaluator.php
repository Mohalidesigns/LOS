<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Application;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Compliance\Domain\KycFacts;
use Fundly\Modules\Compliance\Domain\KycGate;
use Fundly\Modules\Compliance\Infrastructure\Models\ScreeningAlert;
use Fundly\Modules\Party\Contracts\ConsentRegistry;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Illuminate\Support\Facades\DB;

/** Assembles KycFacts from the Party and Application contracts and this module's screening data. */
final class KycEvaluator
{
    public function __construct(
        private readonly ApplicationReader $applications,
        private readonly PartyDirectory $parties,
        private readonly ConsentRegistry $consents,
    ) {}

    /** @return array{outcome: 'clear'|'pending'|'blocked', risk: 'standard'|'enhanced', conditions: list<array{code: string, met: bool, detail: string}>, gate_version: string}|null */
    public function evaluate(string $applicationId): ?array
    {
        $app = $this->applications->find($applicationId);
        if ($app === null) {
            return null;
        }
        $parties = [];
        foreach ($app->applicants as $partyId => $role) {
            $profile = $this->parties->kycProfile($partyId);
            if ($profile === null) {
                continue;
            }
            $directors = [];
            foreach ($profile->relatedIndividuals as $rel) {
                if ($rel['role'] !== 'director') {
                    continue;
                }
                $d = $this->parties->kycProfile($rel['party_id']);
                $directors[] = ['party_id' => $rel['party_id'], 'name' => $rel['display_name'], 'verified_ids' => $d === null ? [] : $d->verifiedIdentityTypes];
            }
            $parties[] = [
                'party_id' => $profile->id,
                'name' => $profile->displayName,
                'role' => $role,
                'type' => $profile->type,
                'verified_ids' => $profile->verifiedIdentityTypes,
                'has_registration' => $profile->registrationNumber !== null && $profile->registrationNumber !== '',
                'directors' => $directors,
                'data_consent' => $this->consents->active($profile->id, ConsentRegistry::DATA_PROCESSING),
            ];
        }
        $screened = array_values(array_map(strval(...), DB::table('screening_runs')->where('application_id', $applicationId)->distinct()->pluck('party_id')->all()));
        $alerts = [];
        foreach (ScreeningAlert::query()->where('application_id', $applicationId)->get(['status', 'category']) as $a) {
            $alerts[] = ['status' => $a->status, 'category' => $a->category];
        }

        return KycGate::evaluate(new KycFacts($parties, $screened, $alerts)) + ['gate_version' => KycGate::VERSION];
    }
}
