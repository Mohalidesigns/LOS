<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Application\Commands;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Notification\Application\TemplateResolver;
use Fundly\Modules\Notification\Domain\Format;
use Fundly\Modules\Notification\Infrastructure\Models\Communication;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Modules\Platform\Contracts\OrganisationDirectory;
use Fundly\Modules\Product\Contracts\ProductCatalogue;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Bus\OutboxIntent;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Security\ResourceAttributes;

/**
 * Who hears about what (P1 rules):
 *   customer email  application.received, offer.issued, offer.expiring, facility.booked
 *   staff in-app    holders of the permission that acts next, in the application's scope
 *                   (approval.awaiting, screening.alert, disbursement.*), or the originator
 *                   (facility.booked.staff)
 * Every message is logged (FR-NTF-005); a repeat of the same message to the
 * same recipient is suppressed by its dedupe key (FR-NTF-006).
 */
final class NotifyHandler implements CommandHandler
{
    public const EMAIL_TOPIC = 'notification.email';

    /** @var array<string, array{to: 'customer'|'originator'|'permission', permission?: string, exclude_originator?: bool}> */
    private const RULES = [
        'application.received' => ['to' => 'customer'],
        'offer.issued' => ['to' => 'customer'],
        'offer.expiring' => ['to' => 'customer'],
        'facility.booked' => ['to' => 'customer'],
        'facility.booked.staff' => ['to' => 'originator'],
        'approval.awaiting' => ['to' => 'permission', 'permission' => 'application:approve', 'exclude_originator' => true],
        'screening.alert' => ['to' => 'permission', 'permission' => 'screening:review'],
        'disbursement.ready' => ['to' => 'permission', 'permission' => 'disbursement:make'],
        'disbursement.awaiting_release' => ['to' => 'permission', 'permission' => 'disbursement:check'],
        'disbursement.failed' => ['to' => 'permission', 'permission' => 'disbursement:check'],
    ];

    public function __construct(
        private readonly ApplicationReader $applications,
        private readonly ProductCatalogue $products,
        private readonly PartyDirectory $parties,
        private readonly OrganisationDirectory $organisation,
        private readonly AuthorityDirectory $authority,
        private readonly TemplateResolver $templates,
        private readonly Clock $clock,
    ) {}

    /** @return array{sent: int, suppressed: int} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof Notify);
        $rule = self::RULES[$command->event] ?? null;
        $app = $this->applications->find($command->applicationId) ?? throw new NotFound('Application not found.');
        if ($rule === null) {
            return ['sent' => 0, 'suppressed' => 0];
        }
        [$template, $source, $versionId] = $this->templates->resolve($command->event);
        $vars = $this->vars($app, $command->context);
        $sent = 0;
        $suppressed = 0;
        $base = "{$command->event}:{$app->id}:{$command->discriminator}";
        $attachments = array_values(array_filter((array) ($command->context['attachments'] ?? []), 'is_array'));

        if ($rule['to'] === 'customer') {
            $partyId = (string) array_search('primary', $app->applicants, true);
            $rendered = $template->render('email', 'en', $vars);
            if ($rendered !== null) {
                $email = $this->parties->contactEmail($partyId);
                $c = $this->log($app, $command->event, 'email', 'customer', null, $partyId, $email === null ? null : Format::maskEmail($email), $source, $versionId, $rendered, null, $attachments, $email === null ? 'failed' : 'queued', $email === null ? 'No email address on file for the applicant.' : null, "{$base}:email:{$partyId}");
                if ($c === null) {
                    $suppressed++;
                } else {
                    $sent++;
                    if ($c->status === 'queued') {
                        $context->outbox(new OutboxIntent(topic: self::EMAIL_TOPIC, payload: ['communication_id' => $c->id], aggregateType: 'communication', aggregateId: $c->id, idempotencyKey: "{$context->principal->tenantId}:ntf:{$c->id}", maxAttempts: 8));
                    }
                }
            }
        } else {
            $rendered = $template->render('in_app', 'en', $vars);
            if ($rendered !== null) {
                $exclude = array_map('strval', (array) ($command->context['exclude_user_ids'] ?? []));
                if ($rule['exclude_originator'] ?? false) {
                    $exclude[] = $app->originatorId;
                }
                $users = $rule['to'] === 'originator' ? [$app->originatorId] : $this->holders((string) $rule['permission'], $app);
                $names = $this->authority->names(array_values(array_diff($users, $exclude)));
                foreach (array_diff($users, $exclude) as $userId) {
                    $c = $this->log($app, $command->event, 'in_app', 'staff', $userId, null, $names[$userId] ?? null, $source, $versionId, $rendered, "/applications/{$app->id}", [], 'delivered', null, "{$base}:in_app:{$userId}");
                    $c === null ? $suppressed++ : $sent++;
                }
            }
        }
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'application', entityId: $app->id, after: ['event' => $command->event, 'sent' => $sent, 'suppressed' => $suppressed, 'template' => $source]));

        return ['sent' => $sent, 'suppressed' => $suppressed];
    }

    /** @return list<string> active users holding the permission for this application */
    private function holders(string $permission, ApplicationSummary $app): array
    {
        $resource = new ResourceAttributes(legalEntityId: $app->legalEntityId, orgUnitId: $app->orgUnitId, productId: $app->productId, segment: $app->segment, entityType: 'application', entityId: $app->id);

        return array_values(array_unique(array_map(static fn ($g): string => $g->userId, $this->authority->holders($permission, $resource))));
    }

    /**
     * @param  array<string, mixed>  $extra
     * @return array<string, string>
     */
    private function vars(ApplicationSummary $app, array $extra): array
    {
        $product = $this->products->version($app->productVersionId);
        $primary = (string) array_search('primary', $app->applicants, true);
        $vars = [
            'reference' => $app->reference,
            'applicant_name' => $this->parties->find($primary)->displayName ?? 'Customer',
            'product_name' => $product->name ?? 'loan',
            'amount' => $app->requestedAmount === null ? '' : Format::money((string) $app->requestedAmount->amount, $app->requestedAmount->currency->code),
            'tenor_months' => (string) ($app->tenorMonths ?? ''),
            'status' => str_replace('_', ' ', $app->status->value),
            'link' => "/applications/{$app->id}",
            'bank_name' => $this->organisation->legalEntity($app->legalEntityId)->name ?? 'Your bank',
        ];
        foreach ($extra as $k => $v) {
            if (is_string($v) || is_int($v)) {
                $vars[(string) $k] = (string) $v;
            }
        }

        return $vars;
    }

    /**
     * Writes the log row; returns null when the same message already went to this recipient.
     *
     * @param  array{subject: string, body: string}  $rendered
     * @param  list<array<string, mixed>>  $attachments
     */
    private function log(ApplicationSummary $app, string $event, string $channel, string $audience, ?string $userId, ?string $partyId, ?string $hint, string $source, ?string $versionId, array $rendered, ?string $link, array $attachments, string $status, ?string $error, string $dedupe): ?Communication
    {
        if (Communication::query()->where('dedupe_key', $dedupe)->exists()) {
            return null;
        }
        $c = new Communication;
        $c->forceFill([
            'application_id' => $app->id, 'event' => $event, 'channel' => $channel, 'audience' => $audience,
            'recipient_user_id' => $userId, 'recipient_party_id' => $partyId, 'recipient_hint' => $hint,
            'template_source' => $source, 'template_version_id' => $versionId, 'language' => 'en',
            'subject' => mb_substr($rendered['subject'], 0, 300), 'body' => $rendered['body'], 'link' => $link, 'attachments' => $attachments,
            'status' => $status, 'attempts' => 0, 'error' => $error, 'dedupe_key' => $dedupe, 'created_at' => $this->clock->now(),
            'sent_at' => $status === 'delivered' ? $this->clock->now() : null,
        ])->save();

        return $c;
    }
}
