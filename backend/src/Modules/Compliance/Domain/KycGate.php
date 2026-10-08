<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Domain;

/**
 * Risk-based CDD progression gate (FR-CUS-007, TRD §6.2 KycScreening →
 * Documentation "CDD complete + screening clear"). Pure. The MVP rule table
 * is code-defined; tenant-configurable rule tables arrive with the
 * jurisdiction pack (P1-CMP-03).
 *
 * Outcome: `clear` (may progress), `pending` (conditions outstanding) or
 * `blocked` (a confirmed sanctions match: the application cannot progress).
 */
final class KycGate
{
    public const VERSION = 'cdd-mvp-1';

    /** @return array{outcome: 'clear'|'pending'|'blocked', risk: 'standard'|'enhanced', conditions: list<array{code: string, met: bool, detail: string}>} */
    public static function evaluate(KycFacts $f): array
    {
        $conditions = [];
        foreach ($f->parties as $p) {
            if ($p['type'] === 'individual') {
                $ok = array_intersect($p['verified_ids'], ['bvn', 'nin']) !== [];
                $conditions[] = ['code' => 'IDENTITY_VERIFIED', 'met' => $ok, 'detail' => "{$p['name']} ({$p['role']}): BVN or NIN verified"];
            } else {
                $conditions[] = ['code' => 'REGISTRATION_RECORDED', 'met' => $p['has_registration'], 'detail' => "{$p['name']}: CAC registration number recorded"];
                $conditions[] = ['code' => 'DIRECTORS_RECORDED', 'met' => $p['directors'] !== [], 'detail' => "{$p['name']}: at least one director recorded"];
                foreach ($p['directors'] as $d) {
                    $conditions[] = ['code' => 'DIRECTOR_VERIFIED', 'met' => array_intersect($d['verified_ids'], ['bvn', 'nin']) !== [], 'detail' => "{$d['name']} (director of {$p['name']}): BVN or NIN verified"];
                }
            }
            if (in_array($p['role'], ['primary', 'joint', 'guarantor', 'co_signer'], true)) {
                $conditions[] = ['code' => 'DATA_CONSENT', 'met' => $p['data_consent'], 'detail' => "{$p['name']}: consent to data processing"];
            }
        }

        $toScreen = [];
        foreach ($f->parties as $p) {
            $toScreen[] = $p['party_id'];
            foreach ($p['directors'] as $d) {
                $toScreen[] = $d['party_id'];
            }
        }
        $unscreened = array_diff(array_unique($toScreen), $f->screenedPartyIds);
        $conditions[] = ['code' => 'SCREENING_COMPLETE', 'met' => $unscreened === [], 'detail' => $unscreened === [] ? 'All parties screened' : count($unscreened).' part(y/ies) awaiting screening'];

        $open = count(array_filter($f->alerts, static fn (array $a): bool => in_array($a['status'], ['open', 'pending_confirmation'], true)));
        $conditions[] = ['code' => 'ALERTS_RESOLVED', 'met' => $open === 0, 'detail' => $open === 0 ? 'No unresolved screening alerts' : "{$open} screening alert(s) awaiting disposition"];

        $sanction = array_filter($f->alerts, static fn (array $a): bool => $a['status'] === 'confirmed_match' && $a['category'] === 'sanction');
        $pep = array_filter($f->alerts, static fn (array $a): bool => $a['status'] === 'confirmed_match' && $a['category'] === 'pep');
        $risk = $pep !== [] ? 'enhanced' : 'standard';
        if ($pep !== []) {
            // Source of funds / wealth capture (FR-CMP-015, D-038a) lands with the EDD workflow; until then a confirmed PEP holds the gate.
            $conditions[] = ['code' => 'EDD_COMPLETE', 'met' => false, 'detail' => 'Confirmed PEP: enhanced due diligence (source of funds and wealth) required'];
        }

        if ($sanction !== []) {
            return ['outcome' => 'blocked', 'risk' => $risk, 'conditions' => array_merge($conditions, [['code' => 'NO_SANCTIONS_MATCH', 'met' => false, 'detail' => 'Confirmed sanctions match: the application cannot progress']])];
        }
        $met = array_reduce($conditions, static fn (bool $c, array $x): bool => $c && $x['met'], true);

        return ['outcome' => $met ? 'clear' : 'pending', 'risk' => $risk, 'conditions' => $conditions];
    }
}
