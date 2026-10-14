<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Domain;

/**
 * Built-in English templates for every P1 event. Plain, factual wording;
 * customer messages never include decision reasons (adverse-action notices
 * are FR-CMP-026, P5) or account numbers in full.
 */
final class DefaultTemplates
{
    /** @return array<string, array<string, mixed>> */
    public static function all(): array
    {
        // WhatsApp: the template name and wording to submit for approval (docs/whatsapp-templates.md)
        $wa = [
            'application.received' => 'Hello {{ applicant_name }}, {{ bank_name }} has received your loan application {{ reference }} for {{ amount }}. We will contact you if we need anything else.',
            'offer.issued' => 'Hello {{ applicant_name }}, your loan offer {{ reference }} for {{ amount }} over {{ tenor_months }} months is ready and valid until {{ valid_until }}. Your relationship manager will share the offer letter.',
            'offer.expiring' => 'Hello {{ applicant_name }}, your loan offer {{ reference }} expires on {{ valid_until }}. Contact your relationship manager if you would like to accept it.',
            'facility.booked' => 'Hello {{ applicant_name }}, {{ net_amount }} has been disbursed for your loan {{ reference }} on {{ value_date }}. Your advice and repayment schedule have been sent to your email.',
        ];
        $customer = static fn (string $event, string $subject, string $body, string $sms): array => ['event' => $event, 'audience' => 'customer', 'languages' => ['en' => [
            'email' => ['subject' => $subject, 'body' => $body], 'sms' => ['body' => $sms],
            'whatsapp' => ['template' => 'fundly_'.str_replace('.', '_', $event), 'body' => $wa[$event]],
        ]]];
        $staff = static fn (string $event, string $title, string $body): array => ['event' => $event, 'audience' => 'staff', 'languages' => ['en' => ['in_app' => ['title' => $title, 'body' => $body]]]];

        return [
            'application.received' => $customer('application.received', 'We have received your application {{ reference }}',
                "Dear {{ applicant_name }},\n\nThank you for applying for a {{ product_name }} of {{ amount }}. Your reference is {{ reference }}. We will contact you if we need anything else.\n\n{{ bank_name }}",
                '{{ bank_name }}: we have received your loan application {{ reference }} for {{ amount }}. We will contact you if we need anything else.'),
            'offer.issued' => $customer('offer.issued', 'Your loan offer {{ reference }} is ready',
                "Dear {{ applicant_name }},\n\nYour offer for {{ amount }} over {{ tenor_months }} months is ready and valid until {{ valid_until }}. Your relationship manager will share the offer letter with you; please read the key facts before signing.\n\n{{ bank_name }}",
                '{{ bank_name }}: your loan offer {{ reference }} is ready and valid until {{ valid_until }}. Your relationship manager will share the offer letter.'),
            'offer.expiring' => $customer('offer.expiring', 'Your loan offer {{ reference }} expires on {{ valid_until }}',
                "Dear {{ applicant_name }},\n\nThis is a reminder that offer {{ offer_sequence }} on application {{ reference }} expires on {{ valid_until }}. Contact your relationship manager if you would like to accept it.\n\n{{ bank_name }}",
                '{{ bank_name }}: your loan offer {{ reference }} expires on {{ valid_until }}. Contact your relationship manager to accept it.'),
            'facility.booked' => $customer('facility.booked', 'Your loan {{ reference }} has been disbursed',
                "Dear {{ applicant_name }},\n\nWe have disbursed {{ net_amount }} on {{ value_date }}. Your disbursement advice and repayment schedule are attached.\n\n{{ bank_name }}",
                '{{ bank_name }}: {{ net_amount }} has been disbursed for loan {{ reference }} on {{ value_date }}. Your advice and schedule are in your email.'),
            'approval.awaiting' => $staff('approval.awaiting', 'Approval needed: {{ reference }}', '{{ applicant_name }} · {{ amount }} is waiting at an approval level you can vote on.'),
            'screening.alert' => $staff('screening.alert', 'Screening alert on {{ reference }}', 'Screening returned a possible match for a party on {{ reference }}. Review it in Compliance alerts.'),
            'disbursement.ready' => $staff('disbursement.ready', 'Ready to disburse: {{ reference }}', '{{ applicant_name }} · {{ amount }}: conditions are met; raise the disbursement instruction.'),
            'disbursement.awaiting_release' => $staff('disbursement.awaiting_release', 'Release needed: {{ reference }}', 'A disbursement instruction for {{ applicant_name }} · {{ amount }} is waiting for a checker.'),
            'disbursement.failed' => $staff('disbursement.failed', 'Disbursement needs attention: {{ reference }}', 'Booking with the core banking system stopped ({{ error_code }}). Open the case to retry or raise a new instruction.'),
            'collateral.revaluation_due' => $staff('collateral.revaluation_due', 'Re-valuation due: {{ reference }}', 'The valuation of {{ collateral }} securing {{ reference }} lapses on {{ due_date }}. Instruct a valuer and record the new valuation.'),
            'collateral.insurance_expiring' => $staff('collateral.insurance_expiring', 'Insurance expiring: {{ reference }}', 'The insurance on {{ collateral }} securing {{ reference }} expires on {{ due_date }}. Record the renewed policy with the bank\'s interest noted.'),
            'facility.booked.staff' => $staff('facility.booked.staff', 'Booked: {{ reference }}', '{{ applicant_name }} · loan account {{ loan_account_no }} is booked and handed over to servicing.'),
        ];
    }
}
