<?php

declare(strict_types=1);

namespace Fundly\Modules\Collateral\Domain;

/**
 * Built-in collateral types covering FR-COL-001. Haircuts, validity periods
 * and perfection steps are indicative Nigerian practice [verify, G-34]:
 * real-estate consent and registration are conditions subsequent by default,
 * movables are registered on the National Collateral Registry before
 * disbursement, and company charges are filed with the CAC after.
 */
final class DefaultCollateralTypes
{
    /** @return array<string, array<string, mixed>> type code → content */
    public static function all(): array
    {
        $step = static fn (string $code, string $name, string $timing = 'precedent', ?int $due = null): array => ['code' => $code, 'name' => $name, 'timing' => $timing, 'due_days' => $due];
        $type = static fn (string $name, string $category, string $haircut, bool $valuation, ?int $months, bool $insurance, array $steps, array $byState = []): array => [
            'name' => $name, 'category' => $category, 'haircut_percent' => $haircut, 'requires_valuation' => $valuation,
            'valuation_validity_months' => $months, 'requires_insurance' => $insurance,
            'perfection' => $byState === [] ? ['default' => $steps] : ['default' => $steps, 'by_state' => $byState],
        ];
        $movable = [
            $step('ncr_search', 'Search on the National Collateral Registry', 'precedent', 7),
            $step('security_agreement', 'Security agreement executed', 'precedent', 7),
            $step('ncr_registration', 'Financing statement registered on the National Collateral Registry', 'precedent', 14),
            $step('cac_charge', 'Charge registered with the CAC (company owners)', 'subsequent', 90),
        ];
        $land = static fn (string $consent): array => [
            $step('title_search', 'Search at the Lands Registry', 'precedent', 14),
            $step('mortgage_executed', 'Deed of legal mortgage executed', 'precedent', 14),
            $step('stamping', 'Deed stamped (FIRS stamp duties)', 'precedent', 30),
            $step('consent', $consent, 'subsequent', 180),
            $step('registration', 'Mortgage registered at the Lands Registry', 'subsequent', 180),
        ];

        return [
            'real_estate' => $type('Real estate (legal mortgage)', 'real_estate', '30', true, 36, true, $land("Governor's consent to the mortgage"), [
                'FC' => $land("Minister's consent (FCT, AGIS)"),
            ]),
            'vehicle' => $type('Motor vehicle', 'vehicle', '40', true, 12, true, [
                $step('ncr_registration', 'Financing statement registered on the National Collateral Registry', 'precedent', 7),
                $step('papers_in_custody', 'Original vehicle papers in the bank\'s custody', 'precedent', 7),
                $step('interest_noted', 'Bank\'s interest noted with the licensing authority', 'subsequent', 30),
            ]),
            'plant_equipment' => $type('Plant and equipment', 'equipment', '40', true, 24, true, $movable),
            'inventory' => $type('Inventory / stock', 'inventory', '50', true, 6, true, $movable),
            'receivables' => $type('Receivables (domiciliation)', 'receivables', '40', true, 3, false, [
                $step('domiciliation_letter', 'Irrevocable domiciliation letter acknowledged by the payer', 'precedent', 14),
                $step('ncr_registration', 'Financing statement registered on the National Collateral Registry', 'precedent', 14),
            ]),
            'cash_deposit' => $type('Cash / deposit lien', 'cash_deposit', '0', false, null, false, [
                $step('lien_placed', 'Lien placed on the deposit in the core banking system', 'precedent', 1),
                $step('set_off_letter', 'Letter of set-off executed', 'precedent', 1),
            ]),
            'quoted_securities' => $type('Quoted securities', 'securities', '30', true, 1, false, [
                $step('lien_registrar', 'Lien noted with the registrar / CSCS', 'precedent', 7),
                $step('transfer_forms', 'Signed share transfer forms in custody', 'precedent', 7),
            ]),
            'personal_guarantee' => $type('Personal guarantee', 'guarantee', '50', false, null, false, [
                $step('guarantee_executed', 'Guarantee executed and witnessed', 'precedent', 7),
                $step('guarantor_statement', 'Guarantor statement of means on file', 'precedent', 7),
            ]),
            'corporate_guarantee' => $type('Corporate guarantee', 'guarantee', '50', false, null, false, [
                $step('board_resolution', 'Board resolution authorising the guarantee', 'precedent', 7),
                $step('guarantee_executed', 'Guarantee executed under seal', 'precedent', 7),
            ]),
            'institutional_guarantee' => $type('Institutional / bank guarantee', 'guarantee', '10', false, null, false, [
                $step('guarantee_received', 'Original guarantee received', 'precedent', 7),
                $step('issuer_confirmation', 'Authenticity confirmed with the issuer', 'precedent', 7),
            ]),
            'insurance_assignment' => $type('Assignment of insurance policy', 'insurance_assignment', '20', false, null, false, [
                $step('assignment_executed', 'Deed of assignment executed', 'precedent', 7),
                $step('insurer_acknowledgement', 'Insurer\'s acknowledgement of the assignment', 'precedent', 30),
            ]),
        ];
    }
}
