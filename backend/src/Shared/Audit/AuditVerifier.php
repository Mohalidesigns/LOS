<?php

declare(strict_types=1);

namespace Fundly\Shared\Audit;

use DateTimeImmutable;
use DateTimeZone;
use Fundly\Shared\Tenancy\TenantContext;
use Illuminate\Database\ConnectionInterface;

/**
 * Recomputes a tenant's hash chain and compares it with stored hashes and
 * checkpoints (FR-AUD-004). Detects edited rows, deleted rows (seq gaps),
 * re-ordered rows (broken links) and a rewritten chain (checkpoint mismatch).
 */
final class AuditVerifier
{
    public function __construct(
        private readonly ConnectionInterface $db,
        private readonly TenantContext $tenant,
    ) {
    }

    public function verify(string $tenantId, ?int $fromSeq = null, ?int $toSeq = null): VerificationResult
    {
        return $this->tenant->run($tenantId, fn (): VerificationResult => $this->verifyCurrent($tenantId, $fromSeq, $toSeq));
    }

    private function verifyCurrent(string $tenantId, ?int $fromSeq, ?int $toSeq): VerificationResult
    {
        $result = new VerificationResult($tenantId);
        $expectedSeq = $fromSeq ?? 1;
        $prevHash = null;
        if ($expectedSeq > 1) {
            $prior = $this->db->selectOne('select hash from audit_events where tenant_id = ? and seq = ?', [$tenantId, $expectedSeq - 1]);
            $prevHash = is_object($prior) ? (string) $prior->hash : null;
        }
        $prevHash ??= HashChain::GENESIS;

        $query = $this->db->table('audit_events')->where('tenant_id', $tenantId)->where('seq', '>=', $expectedSeq);
        if ($toSeq !== null) {
            $query->where('seq', '<=', $toSeq);
        }

        foreach ($query->orderBy('seq')->lazy(500) as $row) {
            $r = (array) $row;
            $seq = (int) $r['seq'];
            if ($seq !== $expectedSeq) {
                $result->addBreak($expectedSeq, 'missing', sprintf('Expected seq %d but found %d: events deleted.', $expectedSeq, $seq));
            }
            if ($r['prev_hash'] !== $prevHash) {
                $result->addBreak($seq, 'link', 'prev_hash does not match the preceding event hash.');
            }
            $recomputed = HashChain::compute(self::hydrate($r));
            if (! hash_equals((string) $r['hash'], $recomputed)) {
                $result->addBreak($seq, 'content', 'Stored hash does not match recomputed content hash: event altered.');
            }
            $prevHash = (string) $r['hash'];
            $expectedSeq = $seq + 1;
            $result->eventsChecked++;
            $result->headSeq = $seq;
            $result->headHash = $prevHash;
        }

        $checkpoints = $this->db->table('audit_checkpoints')->where('tenant_id', $tenantId)->orderBy('seq')->get();
        foreach ($checkpoints as $cp) {
            $cpSeq = (int) $cp->seq;
            if (($fromSeq !== null && $cpSeq < $fromSeq) || ($toSeq !== null && $cpSeq > $toSeq)) {
                continue;
            }
            $row = $this->db->selectOne('select hash from audit_events where tenant_id = ? and seq = ?', [$tenantId, $cpSeq]);
            if (! is_object($row)) {
                $result->addBreak($cpSeq, 'checkpoint', 'Checkpointed event no longer exists.');
            } elseif (! hash_equals((string) $cp->hash, (string) $row->hash)) {
                $result->addBreak($cpSeq, 'checkpoint', 'Event hash differs from the anchored checkpoint: chain rewritten.');
            }
            $result->checkpointsChecked++;
        }

        return $result;
    }

    /**
     * Converts a database row back into the exact structure that was hashed.
     *
     * @param  array<string, mixed>  $r
     * @return array<string, mixed>
     */
    public static function hydrate(array $r): array
    {
        $occurred = new DateTimeImmutable((string) $r['occurred_at']);
        $r['occurred_at'] = $occurred->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d\TH:i:s.uP');
        $r['seq'] = (int) $r['seq'];
        foreach (['actor_roles', 'before', 'after'] as $json) {
            $r[$json] = is_string($r[$json])
                ? json_decode($r[$json], true, 512, JSON_THROW_ON_ERROR | JSON_BIGINT_AS_STRING)
                : null;
        }

        return $r;
    }
}
