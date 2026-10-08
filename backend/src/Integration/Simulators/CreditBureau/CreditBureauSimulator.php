<?php

declare(strict_types=1);

namespace Fundly\Integration\Simulators\CreditBureau;

use Fundly\Integration\Ports\CreditBureau\BureauSubject;
use Fundly\Integration\Ports\CreditBureau\CreditBureauPort;
use Fundly\Integration\Ports\CreditBureau\CreditProfile;
use Fundly\Integration\Runtime\CapabilityManifest;

/**
 * Deterministic synthetic bureau (D-039). The profile is derived from the
 * identifier, so the same subject always gets the same report:
 *   …00 → no hit (thin file); …13 → serious delinquency with a write-off;
 *   …77 → many recent enquiries; otherwise a clean file, score 600–799.
 */
final class CreditBureauSimulator implements CreditBureauPort
{
    public const KEY = 'credit-bureau-simulator';

    public const VERSION = '1.0.0';

    public static function capabilities(): CapabilityManifest
    {
        return new CapabilityManifest(self::KEY, self::VERSION, '1.0', 'on_prem:simulator', [
            self::OP_FETCH => ['support' => 'native', 'idempotency' => 'natural'],
        ]);
    }

    public function fetch(BureauSubject $subject): CreditProfile
    {
        $id = $subject->identifier;
        $seed = (int) sprintf('%u', crc32($id));
        $ref = 'SIM-CRB-'.strtoupper(substr(hash('sha256', $id.$subject->consentReference), 0, 12));
        $suffix = substr($id, -2);
        if ($suffix === '00') {
            return new CreditProfile('Simulated Credit Bureau', $ref, false, null, [], 0, false);
        }
        $facilities = [];
        $count = 1 + $seed % 3;
        for ($i = 0; $i < $count; $i++) {
            $outstanding = 500000 + (($seed >> ($i * 3)) % 40) * 125000;
            $facilities[] = [
                'lender' => ['Lagoon Bank', 'Harmattan MFB', 'Delta Capital'][$i],
                'type' => ['term_loan', 'overdraft', 'asset_finance'][$i],
                'outstanding' => $outstanding.'.00',
                'monthly_instalment' => intdiv($outstanding, 18).'.00',
                'dpd' => $suffix === '13' && $i === 0 ? 120 : 0,
                'status' => $suffix === '13' && $i === 0 ? 'written_off' : 'current',
            ];
        }
        $score = $suffix === '13' ? 480 : 600 + $seed % 200;

        return new CreditProfile('Simulated Credit Bureau', $ref, true, $score, $facilities, $suffix === '77' ? 7 : $seed % 3, $suffix === '13');
    }
}
