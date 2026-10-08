<?php

declare(strict_types=1);

namespace Fundly\Modules\Application\Infrastructure;

use DateTimeImmutable;
use Fundly\Modules\Application\Contracts\CanonicalStatus;
use Fundly\Modules\Application\Domain\Application;
use Fundly\Modules\Application\Domain\PendingEvent;
use Fundly\Modules\Application\Infrastructure\Models\ApplicantRecord;
use Fundly\Modules\Application\Infrastructure\Models\ApplicationEventRecord;
use Fundly\Modules\Application\Infrastructure\Models\ApplicationRecord;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\CodedConflict;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Http\RequestContext;
use Fundly\Shared\Security\Principal;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use stdClass;

/**
 * Appends aggregate events and keeps the projection in step, in the caller's
 * transaction. Loads by replaying the event stream.
 */
final class ApplicationStore
{
    public function __construct(private readonly Clock $clock, private readonly RequestContext $request) {}

    /** Loads and row-locks the projection so concurrent commands serialise. */
    public function load(string $id, bool $lock = true): Application
    {
        $q = ApplicationRecord::query()->whereKey($id);
        if ($lock) {
            $q->lockForUpdate();
        }
        if (preg_match('/^[0-9a-f-]{36}$/', $id) !== 1 || ! $q->exists()) {
            throw new NotFound('Application not found.');
        }

        return Application::replay($id, $this->events($id));
    }

    /** @return list<array{type: string, payload: array<string, mixed>, version: int, occurred_at: DateTimeImmutable, actor_type: string, actor_id: string, correlation_id: ?string}> */
    public function events(string $id): array
    {
        $out = [];
        foreach (ApplicationEventRecord::query()->where('application_id', $id)->orderBy('version')->get() as $e) {
            $out[] = [
                'type' => $e->type,
                'payload' => $e->payload,
                'version' => $e->version,
                'occurred_at' => $e->occurred_at->toDateTimeImmutable(),
                'actor_type' => $e->actor_type,
                'actor_id' => $e->actor_id,
                'correlation_id' => $e->correlation_id,
            ];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $projectionSeed  extra projection columns on creation (product, org, names …)
     * @return list<array{type: string, payload: array<string, mixed>, version: int}>
     */
    public function save(Application $app, Principal $actor, array $projectionSeed = []): array
    {
        $events = $app->releaseEvents();
        if ($events === []) {
            return [];
        }
        $now = $this->clock->now();
        $record = ApplicationRecord::query()->find($app->id());
        if ($record === null) {
            $record = new ApplicationRecord;
            $record->id = $app->id();
            $record->forceFill($projectionSeed + ['status_changed_at' => $now, 'data' => []]);
        }
        $ops = $this->project($record, $app, $events, $now);
        $record->save();
        $this->applyApplicantOps($app->id(), $ops);

        $written = [];
        try {
            foreach ($events as $e) {
                $row = new ApplicationEventRecord;
                $row->forceFill([
                    'application_id' => $app->id(),
                    'version' => $e->version,
                    'type' => $e->type,
                    'payload' => $e->payload,
                    'actor_type' => $actor->kind->value,
                    'actor_id' => $actor->id,
                    'correlation_id' => $this->request->correlationId(),
                    'occurred_at' => $now,
                ])->save();
                $written[] = ['type' => $e->type, 'payload' => $e->payload, 'version' => $e->version];
            }
        } catch (UniqueConstraintViolationException) {
            throw new CodedConflict('application-concurrent-update', 'The application was changed by someone else; reload and try again.');
        }

        return $written;
    }

    /**
     * Updates the projection row in memory and returns applicant changes to
     * apply once the row exists.
     *
     * @param  list<PendingEvent>  $events
     * @return list<array{op: 'add'|'remove', party_id: string, role?: string, party_type?: string, display_name?: string}>
     */
    private function project(ApplicationRecord $record, Application $app, array $events, DateTimeImmutable $now): array
    {
        $s = $app->snapshot();
        $record->forceFill([
            'reference' => $s['reference'],
            'currency' => $s['currency'],
            'requested_amount' => $s['requested_amount'],
            'tenor_months' => $s['tenor_months'],
            'purpose' => $s['purpose'],
            'repayment_frequency' => $s['repayment_frequency'],
            'data' => $s['data'] === [] ? new stdClass : $s['data'],
            'canonical_status' => $s['status'],
            'resume_to' => $s['resume_to'],
            'return_to' => $s['return_to'],
            'version' => $s['version'],
        ]);
        $ops = [];
        foreach ($events as $e) {
            $p = $e->payload;
            switch ($e->type) {
                case 'application.created':
                    $ops[] = ['op' => 'add', 'party_id' => (string) $p['primary_party_id'], 'role' => 'primary', 'party_type' => (string) $p['primary_party_type'], 'display_name' => (string) $p['primary_party_name']];
                    break;
                case 'application.applicant_added':
                    $ops[] = ['op' => 'add', 'party_id' => (string) $p['party_id'], 'role' => (string) $p['role'], 'party_type' => (string) $p['party_type'], 'display_name' => (string) $p['display_name']];
                    break;
                case 'application.applicant_removed':
                    $ops[] = ['op' => 'remove', 'party_id' => (string) $p['party_id']];
                    break;
                case 'application.status_changed':
                    $to = CanonicalStatus::from((string) $p['to']);
                    $at = Carbon::instance($now);
                    $record->status_changed_at = $at;
                    if ($to === CanonicalStatus::Submitted && $record->submitted_at === null) {
                        $record->submitted_at = $at;
                    }
                    if ($to->isTerminal()) {
                        $record->closed_at = $at;
                        $record->close_reason_code = is_string($p['reason_code'] ?? null) ? $p['reason_code'] : null;
                    }
                    break;
            }
        }

        return $ops;
    }

    /** @param list<array{op: 'add'|'remove', party_id: string, role?: string, party_type?: string, display_name?: string}> $ops */
    private function applyApplicantOps(string $applicationId, array $ops): void
    {
        foreach ($ops as $op) {
            if ($op['op'] === 'remove') {
                ApplicantRecord::query()->where('application_id', $applicationId)->where('party_id', $op['party_id'])->delete();

                continue;
            }
            $a = new ApplicantRecord;
            $a->forceFill(['application_id' => $applicationId, 'party_id' => $op['party_id'], 'role' => $op['role'] ?? 'joint', 'party_type' => $op['party_type'] ?? '', 'display_name' => $op['display_name'] ?? ''])->save();
        }
    }
}
