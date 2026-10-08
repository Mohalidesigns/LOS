<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Application;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Application\Contracts\ApplicationSummary;
use Fundly\Modules\Document\Domain\ChecklistSummary;
use Fundly\Modules\Document\Infrastructure\DocumentVault;
use Fundly\Modules\Document\Infrastructure\Models\ChecklistItem;
use Fundly\Modules\Document\Infrastructure\Models\Document;
use Fundly\Modules\Document\Infrastructure\Models\DocumentVersion;
use Fundly\Shared\Audit\AuditEntry;
use Fundly\Shared\Audit\AuditTrail;
use Fundly\Shared\Exceptions\NotFound;
use Fundly\Shared\Exceptions\ProblemException;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\Principal;
use Fundly\Shared\Security\ResourceAttributes;
use Illuminate\Support\Facades\DB;

final class DocumentQueries
{
    public function __construct(
        private readonly ApplicationReader $applications,
        private readonly AuthorizationGate $gate,
        private readonly ChecklistSync $sync,
        private readonly DocumentVault $vault,
        private readonly AuditTrail $audit,
    ) {}

    /** @return array{data: list<array<string, mixed>>} */
    public function forApplication(string $applicationId, Principal $principal): array
    {
        $this->authorizedApplication($applicationId, $principal);
        $docs = Document::query()->where('application_id', $applicationId)->orderBy('created_at')->get();
        $versions = DocumentVersion::query()->whereIn('document_id', $docs->pluck('id')->all())->orderBy('version_no')->get()->groupBy('document_id');
        $out = [];
        foreach ($docs as $d) {
            $out[] = DocumentPresenter::document($d, array_values(($versions[$d->id] ?? collect())->all()));
        }

        return ['data' => $out];
    }

    /** @return array{data: array<string, mixed>} */
    public function document(string $id, Principal $principal): array
    {
        $d = $this->authorizedDocument($id, $principal);

        return ['data' => DocumentPresenter::document($d, array_values(DocumentVersion::query()->where('document_id', $d->id)->orderBy('version_no')->get()->all()))];
    }

    /**
     * Content of a clean version. Quarantined versions are never served
     * (FR-DOC-004); every read is audited.
     *
     * @return array{bytes: string, filename: string, mime_type: string, sha256: string}
     */
    public function content(string $id, string $versionId, Principal $principal): array
    {
        $d = $this->authorizedDocument($id, $principal);
        $v = DocumentVersion::query()->where('document_id', $d->id)->whereKey($versionId)->first() ?? throw new NotFound('Document version not found.');
        if ($v->scan_status !== 'clean' || $v->storage_key === null || $v->key_version === null) {
            throw new class('This version failed the malware scan and is quarantined; it can never be read.') extends ProblemException
            {
                public function status(): int
                {
                    return 423;
                }

                public function type(): string
                {
                    return 'document-quarantined';
                }

                public function title(): string
                {
                    return 'Document quarantined';
                }
            };
        }
        $bytes = $this->vault->get($v->id, $v->storage_key, $v->key_version);
        if (! hash_equals($v->sha256, hash('sha256', $bytes))) {
            throw new \RuntimeException('Stored content does not match its recorded SHA-256.');
        }
        DB::transaction(fn () => $this->audit->record(new AuditEntry(action: 'document.content.read', entityType: 'document', entityId: $d->id, after: ['version_id' => $v->id, 'sha256' => $v->sha256], permission: 'application:view')));

        return ['bytes' => $bytes, 'filename' => $v->filename, 'mime_type' => $v->mime_type, 'sha256' => $v->sha256];
    }

    /** @return array{data: array{items: list<array<string, mixed>>, summary: array{mandatory_total: int, mandatory_satisfied: int, outstanding: list<string>, complete: bool}}} */
    public function checklist(string $applicationId, Principal $principal): array
    {
        $this->authorizedApplication($applicationId, $principal);
        if (ChecklistItem::query()->where('application_id', $applicationId)->doesntExist()) {
            DB::transaction(fn () => $this->sync->sync($applicationId));
        }
        $items = ChecklistItem::query()->where('application_id', $applicationId)->orderBy('position')->orderBy('created_at')->get();
        $latest = DocumentVersion::query()->whereIn('document_id', $items->pluck('document_id')->filter()->all())->where('scan_status', 'clean')
            ->orderBy('version_no')->get()->keyBy('document_id');
        $out = [];
        $summaryInput = [];
        foreach ($items as $i) {
            $out[] = DocumentPresenter::item($i, $i->document_id === null ? null : ($latest[$i->document_id]->id ?? null));
            $summaryInput[] = ['name' => $i->name, 'mandatory' => $i->mandatory, 'status' => $i->status];
        }

        return ['data' => ['items' => $out, 'summary' => ChecklistSummary::of($summaryInput)]];
    }

    private function authorizedApplication(string $applicationId, Principal $principal): ApplicationSummary
    {
        $app = $this->applications->find($applicationId) ?? throw new NotFound('Application not found.');
        $this->gate->authorize($principal, 'application:view', new ResourceAttributes(legalEntityId: $app->legalEntityId, orgUnitId: $app->orgUnitId, productId: $app->productId, entityType: 'application', entityId: $app->id));

        return $app;
    }

    private function authorizedDocument(string $id, Principal $principal): Document
    {
        $d = preg_match('/^[0-9a-f-]{36}$/', $id) === 1 ? Document::query()->find($id) : null;
        if ($d === null) {
            throw new NotFound('Document not found.');
        }
        $this->authorizedApplication($d->application_id, $principal);

        return $d;
    }
}
