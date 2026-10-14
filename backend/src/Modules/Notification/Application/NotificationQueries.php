<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Application;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Notification\Infrastructure\Models\Communication;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;

final class NotificationQueries
{
    public function __construct(private readonly ApplicationReader $applications, private readonly AuthorizationGate $gate) {}

    /**
     * My in-app notifications, newest first, with the unread count.
     *
     * @return array{data: list<array<string, mixed>>, meta: array{unread: int}}
     */
    public function inbox(Principal $principal, bool $unreadOnly): array
    {
        $q = Communication::query()->where('channel', 'in_app')->where('recipient_user_id', $principal->id)->orderByDesc('created_at')->limit(50);
        if ($unreadOnly) {
            $q->whereNull('read_at');
        }
        $unread = Communication::query()->where('channel', 'in_app')->where('recipient_user_id', $principal->id)->whereNull('read_at')->count();

        return ['data' => array_values($q->get()->map(fn (Communication $c): array => $this->inboxItem($c))->all()), 'meta' => ['unread' => $unread]];
    }

    /**
     * The communication log of an application (FR-NTF-005).
     *
     * @return array{data: list<array<string, mixed>>}
     */
    public function forApplication(string $applicationId, Principal $principal): array
    {
        $a = $this->applications->find($applicationId) ?? throw new NotFound('Application not found.');
        $this->gate->authorize($principal, 'application:view', new ResourceAttributes(legalEntityId: $a->legalEntityId, orgUnitId: $a->orgUnitId, productId: $a->productId, segment: $a->segment, entityType: 'application', entityId: $a->id));

        return ['data' => array_values(Communication::query()->where('application_id', $a->id)->orderByDesc('created_at')->get()->map(static fn (Communication $c): array => [
            'id' => $c->id, 'event' => $c->event, 'channel' => $c->channel, 'audience' => $c->audience,
            'recipient' => ['user_id' => $c->recipient_user_id, 'party_id' => $c->recipient_party_id, 'hint' => $c->recipient_hint],
            'template' => ['source' => $c->template_source, 'version_id' => $c->template_version_id, 'language' => $c->language],
            'subject' => $c->subject, 'body' => $c->body, 'attachments' => array_map(static fn (array $x): string => (string) $x['filename'], $c->attachments),
            'status' => $c->status, 'attempts' => $c->attempts, 'error' => $c->error,
            'created_at' => $c->created_at->toIso8601String(), 'sent_at' => $c->sent_at?->toIso8601String(), 'read_at' => $c->read_at?->toIso8601String(),
        ])->all())];
    }

    /** @return array<string, mixed> */
    public function inboxItem(Communication $c): array
    {
        return [
            'id' => $c->id, 'event' => $c->event, 'title' => $c->subject, 'body' => $c->body, 'link' => $c->link, 'application_id' => $c->application_id,
            'created_at' => $c->created_at->toIso8601String(), 'read_at' => $c->read_at?->toIso8601String(),
        ];
    }
}
