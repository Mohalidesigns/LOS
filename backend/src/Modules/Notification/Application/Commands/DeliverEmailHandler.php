<?php

declare(strict_types=1);

namespace Fundly\Modules\Notification\Application\Commands;

use Fundly\Modules\Document\Contracts\DocumentArchive;
use Fundly\Modules\Notification\Infrastructure\Models\Communication;
use Fundly\Modules\Party\Contracts\PartyDirectory;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\CommandContext;
use Fundly\Shared\Bus\CommandHandler;
use Fundly\Shared\Clock\Clock;
use Fundly\Shared\Exceptions\NotFound;
use Illuminate\Contracts\Mail\Mailer;
use Illuminate\Mail\Message;
use Throwable;

/**
 * Sends through the installation's mailer (the bank's SMTP relay). The
 * address is read at send time, never stored in the log; a send failure is
 * recorded and reported as `retry` so the outbox tries again with backoff.
 */
final class DeliverEmailHandler implements CommandHandler
{
    public function __construct(private readonly PartyDirectory $parties, private readonly DocumentArchive $archive, private readonly Mailer $mailer, private readonly Clock $clock) {}

    /** @return array{outcome: 'sent'|'retry'|'skipped'|'failed'} */
    public function handle(Command $command, CommandContext $context): array
    {
        assert($command instanceof DeliverEmail);
        $c = Communication::query()->find($command->communicationId) ?? throw new NotFound('Communication not found.');
        if ($c->status !== 'queued') {
            return ['outcome' => 'skipped'];
        }
        $email = $c->recipient_party_id === null ? null : $this->parties->contactEmail($c->recipient_party_id);
        if ($email === null) {
            $c->forceFill(['status' => 'failed', 'error' => 'No email address on file.'])->save();

            return ['outcome' => 'failed'];
        }
        $files = [];
        foreach ($c->attachments as $a) {
            $bytes = $this->archive->content((string) $a['version_id']);
            if ($bytes !== null) {
                $files[] = [$bytes, (string) $a['filename']];
            }
        }
        try {
            $this->mailer->raw($c->body, static function (Message $m) use ($email, $c, $files): void {
                $m->to($email)->subject($c->subject);
                foreach ($files as [$bytes, $name]) {
                    $m->attachData($bytes, $name, ['mime' => 'application/pdf']);
                }
            });
        } catch (Throwable $e) {
            $c->forceFill(['attempts' => $c->attempts + 1, 'error' => mb_substr($e->getMessage(), 0, 500)])->save();

            return ['outcome' => 'retry'];
        }
        $c->forceFill(['status' => 'sent', 'attempts' => $c->attempts + 1, 'error' => null, 'sent_at' => $this->clock->now()])->save();
        $context->audit(new AuditEntry(action: $command->action(), entityType: 'communication', entityId: $c->id, after: ['event' => $c->event, 'recipient' => $c->recipient_hint, 'attachments' => count($files)]));

        return ['outcome' => 'sent'];
    }
}
