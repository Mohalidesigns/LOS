<?php

declare(strict_types=1);

namespace Fundly\Modules\Credit\Application\Commands;

use Brick\Math\BigDecimal;
use Fundly\Integration\Ports\CreditBureau\BureauSubject;
use Fundly\Integration\Ports\CreditBureau\CreditBureauOperations;
use Fundly\Integration\Ports\CreditBureau\CreditBureauPort;
use Fundly\Integration\Ports\CreditBureau\CreditProfile;
use Fundly\Integration\Runtime\IntegrationGateway;
use Fundly\Modules\Credit\Application\CreditFileLoader;
use Fundly\Modules\Credit\Application\CreditPresenter;
use Fundly\Modules\Credit\Infrastructure\Models\BureauReport;
use Fundly\Modules\Party\Contracts\ConsentRegistry;
use Fundly\Modules\Party\Contracts\IdentityNumberResolver;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Exceptions\DomainRuleViolation;
use Fundly\Shared\Exceptions\ValidationFailed;

final class PullBureauReportHandler implements CommandHandler
{
    public function __construct(
        private readonly CreditFileLoader $file,
        private readonly PartyDirectory $parties,
        private readonly ConsentRegistry $consents,
        private readonly IdentityNumberResolver $identities,
        private readonly IntegrationGateway $gateway,
    ) {}

    /** @return array{data: array<string, mixed>} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof PullBureauReport);
        $app = $this->file->application($command->applicationId);
        if ($app->status->isTerminal()) {
            throw new DomainRuleViolation("The application is {$app->status->value}.");
        }
        $partyId = $command->partyId ?? $this->file->primaryPartyId($app);
        if (! array_key_exists($partyId, $app->applicants)) {
            throw ValidationFailed::with(['party_id' => 'The party is not an applicant on this application.']);
        }
        $party = $this->parties->kycProfile($partyId) ?? throw ValidationFailed::with(['party_id' => 'Unknown party.']);
        if (! $this->consents->active($partyId, ConsentRegistry::CREDIT_BUREAU)) {
            throw new class("{$party->displayName} has not consented to a credit bureau enquiry.") extends DomainRuleViolation
            {
                public function code(): string
                {
                    return 'bureau-consent-missing';
                }
            };
        }
        $identifier = $this->identities->resolve($partyId, $party->type === 'individual' ? ['bvn', 'nin'] : ['rc_number'])
            ?? throw new DomainRuleViolation("{$party->displayName} has no BVN, NIN or RC number to enquire with.");

        /** @var CreditProfile $profile */
        $profile = $this->gateway->call(
            CreditBureauPort::PORT,
            CreditBureauPort::OP_FETCH,
            CreditBureauOperations::policy(),
            static function (object $adapter) use ($party, $identifier, $app): CreditProfile {
                assert($adapter instanceof CreditBureauPort);

                return $adapter->fetch(new BureauSubject($party->type === 'individual' ? 'individual' : 'organisation', $identifier['value'], $identifier['type'], $party->displayName, $party->dateOfBirth, 'consent:'.$app->reference));
            },
            ['party_id' => $partyId, 'identifier_type' => $identifier['type'], $identifier['type'] => $identifier['value'], 'application_reference' => $app->reference],
        );

        $now = $this->file->now();
        $report = new BureauReport;
        $report->forceFill([
            'application_id' => $app->id,
            'party_id' => $partyId,
            'bureau' => $profile->bureau,
            'report_reference' => $profile->reportReference,
            'identifier_type' => $identifier['type'],
            'hit' => $profile->hit,
            'profile' => self::canonical($profile),
            'consent_reference' => 'consent:'.$app->reference,
            'pulled_by' => $context->principal->id,
            'pulled_at' => $now,
            'valid_until' => $now->copy()->addDays((int) config('fundly.credit.bureau_validity_days', 30)),
        ])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'application', entityId: $app->id, after: [
            'party_id' => $partyId, 'bureau' => $profile->bureau, 'report_reference' => $profile->reportReference, 'hit' => $profile->hit, 'score' => $profile->score,
        ]));

        return ['data' => CreditPresenter::bureau($report, $party->displayName, $now)];
    }

    /**
     * Canonical credit profile (FR-CRD-004) with derived totals.
     *
     * @return array<string, mixed>
     */
    private static function canonical(CreditProfile $p): array
    {
        $outstanding = BigDecimal::zero();
        $obligations = BigDecimal::zero();
        $maxDpd = 0;
        $delinquent = 0;
        $facilities = [];
        foreach ($p->facilities as $f) {
            $outstanding = $outstanding->plus($f['outstanding']);
            $obligations = $obligations->plus($f['monthly_instalment']);
            $maxDpd = max($maxDpd, $f['dpd']);
            $delinquent += $f['dpd'] > 30 ? 1 : 0;
            $facilities[] = [
                'lender' => $f['lender'], 'type' => $f['type'], 'dpd' => $f['dpd'], 'status' => $f['status'],
                'outstanding' => ['amount' => (string) BigDecimal::of($f['outstanding'])->toScale(4), 'currency' => 'NGN'],
                'monthly_instalment' => ['amount' => (string) BigDecimal::of($f['monthly_instalment'])->toScale(4), 'currency' => 'NGN'],
            ];
        }

        return [
            'score' => $p->score,
            'active_facilities' => count($p->facilities),
            'total_outstanding' => ['amount' => (string) $outstanding->toScale(4), 'currency' => 'NGN'],
            'monthly_obligations' => ['amount' => (string) $obligations->toScale(4), 'currency' => 'NGN'],
            'max_dpd_12m' => $maxDpd,
            'delinquent_facilities' => $delinquent,
            'enquiries_6m' => $p->enquiries6m,
            'has_write_off' => $p->hasWriteOff,
            'facilities' => $facilities,
        ];
    }
}
