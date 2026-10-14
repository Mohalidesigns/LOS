<?php

declare(strict_types=1);

namespace Fundly\Modules\Compliance\Domain\Packs;

/**
 * Draft packs shipped as starting points. They are NOT active: a tenant
 * authors a `jurisdiction.pack` version from one, the compliance team
 * verifies each rule against its primary source and records the URL,
 * retrieval and verification (P1-CMP-04, TRD §9.4), and only then can it be
 * activated. Every rule below is unverified; figures and sections are to
 * be confirmed.
 */
final class DefaultPacks
{
    /** @return array<string, array<string, mixed>> key → content */
    public static function all(): array
    {
        return ['ng.commercial_bank' => self::nigeriaCommercialBankV0()];
    }

    /** @return array<string, mixed> */
    public static function nigeriaCommercialBankV0(): array
    {
        $rule = static fn (string $code, string $title, string $kind, array $citation, array $parameters = [], string $note = ''): array => array_filter([
            'code' => $code, 'title' => $title, 'kind' => $kind, 'parameters' => $parameters === [] ? null : $parameters,
            'citation' => $citation, 'effective_from' => '2020-11-12', 'verification_status' => 'unverified', 'note' => $note === '' ? null : $note,
        ], static fn (mixed $v): bool => $v !== null);

        return [
            'jurisdiction' => 'NG',
            'licence_category' => 'commercial_bank',
            'name' => 'Nigeria × commercial bank, v0 (draft)',
            'rules' => [
                $rule('single_obligor_limit', 'Single-obligor limit', 'parameter', ['instrument' => 'Banks and Other Financial Institutions Act 2020 (BOFIA); CBN prudential guidelines', 'section' => 'to be confirmed'],
                    ['limit_percent' => '20', 'base' => 'shareholders_funds_unimpaired'], 'Aggregate exposure to one obligor as a share of shareholders\' funds unimpaired by losses [verify percentage, base and any regional/national/international licence variation].'),
                $rule('origination_record_retention', 'Retention of origination and customer records', 'parameter', ['instrument' => 'Money Laundering (Prevention and Prohibition) Act 2022', 'section' => 'record-keeping, to be confirmed'],
                    ['years' => 5], 'Minimum period after the transaction or end of the relationship [verify start event, G-29].'),
                $rule('bureau_report_validity', 'Credit bureau enquiry before credit', 'parameter', ['instrument' => 'Credit Reporting Act 2017; CBN guidelines on credit bureaus', 'section' => 'to be confirmed'],
                    ['days' => 30], 'The Act requires the enquiry; the validity window is bank policy recorded here [verify whether two bureaus must be consulted].'),
                $rule('cdd_aml', 'Customer due diligence and AML/CFT/CPF screening', 'obligation', ['instrument' => 'CBN (Anti-Money Laundering, Combating the Financing of Terrorism and Countering Proliferation Financing of Weapons of Mass Destruction in Financial Institutions) Regulations 2022']),
                $rule('data_protection_lawful_basis', 'Lawful basis, consent and data-subject rights', 'obligation', ['instrument' => 'Nigeria Data Protection Act 2023; NDPC General Application and Implementation Directive 2025']),
                $rule('key_facts_disclosure', 'Pre-contract disclosure of the cost of credit (key facts)', 'obligation', ['instrument' => 'CBN Consumer Protection Regulations', 'section' => 'to be confirmed']),
                $rule('insider_related_lending', 'Insider and related-party lending limits and approval path', 'obligation', ['instrument' => 'Banks and Other Financial Institutions Act 2020 (BOFIA); CBN prudential guidelines'], [], 'Enforcement arrives with the insider register (P3).'),
                $rule('crms_reporting', 'Credit Risk Management System enquiry and reporting', 'obligation', ['instrument' => 'CBN Credit Risk Management System (CRMS) circulars'], [], 'Origination-time obligations per D-023 (P5).'),
            ],
        ];
    }
}
