<?php

declare(strict_types=1);

namespace Fundly\Modules\Offer\Infrastructure;

use DateTimeImmutable;
use Fundly\Modules\Offer\Contracts\AcceptedOffer;
use Fundly\Modules\Offer\Contracts\ExpiringOffer;
use Fundly\Modules\Offer\Contracts\OfferRegister;
use Fundly\Modules\Offer\Infrastructure\Models\Agreement;
use Fundly\Modules\Offer\Infrastructure\Models\Offer;
use Fundly\Shared\Money\Money;

final class DatabaseOfferRegister implements OfferRegister
{
    public function expiring(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return array_values(Offer::query()->where('status', 'issued')->whereBetween('valid_until', [$from, $to])->orderBy('valid_until')->get()
            ->map(static fn (Offer $o): ExpiringOffer => new ExpiringOffer($o->id, $o->application_id, $o->sequence, $o->valid_until->toDateTimeImmutable()))->all());
    }

    public function accepted(string $applicationId): ?AcceptedOffer
    {
        $o = Offer::query()->where('application_id', $applicationId)->where('status', 'accepted')->orderByDesc('sequence')->first();
        $a = $o === null ? null : Agreement::query()->where('offer_id', $o->id)->first();
        if ($o === null || $a === null) {
            return null;
        }
        $t = $o->terms;

        /** @var list<array{number: int, due_date: string, principal: string, interest: string, total: string, closing_balance: string}> $schedule */
        $schedule = $o->schedule;
        /** @var list<array{code: string, name: string, type: string, amount: string, costed: bool}> $fees */
        $fees = array_values((array) ($o->key_facts['fees'] ?? []));

        return new AcceptedOffer(
            offerId: $o->id,
            agreementId: $a->id,
            principal: Money::fromArray((array) $t['amount']),
            tenorMonths: (int) $t['tenor_months'],
            ratePercent: (string) $t['rate_percent'],
            repaymentFrequency: (string) $t['repayment_frequency'],
            schedule: $schedule,
            agreementSha256: $a->sha256,
            templateVersionId: $a->template_version_id,
            executedAt: $a->executed_at->toDateTimeImmutable(),
            fees: $fees,
            netDisbursed: isset($o->key_facts['net_disbursed']) ? Money::of((string) $o->key_facts['net_disbursed'], (string) $t['amount']['currency']) : null,
        );
    }
}
