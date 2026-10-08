<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Application;

use Fundly\Modules\Application\Contracts\ApplicationReader;
use Fundly\Modules\Document\Infrastructure\Models\ChecklistItem;
use Fundly\Modules\Document\Infrastructure\Models\Document;
use Fundly\Modules\Document\Infrastructure\Models\DocumentVersion;
use Illuminate\Support\Facades\DB;

final class DocumentPresenter
{
    /** @return array<string, mixed> */
    public static function item(ChecklistItem $i, ?string $latestVersionId = null): array
    {
        return [
            'id' => $i->id,
            'application_id' => $i->application_id,
            'code' => $i->code,
            'name' => $i->name,
            'mandatory' => $i->mandatory,
            'status' => $i->status,
            'document_id' => $i->document_id,
            'latest_version_id' => $latestVersionId,
            'valid_until' => $i->valid_until?->toDateString(),
            'rejection_reason' => $i->rejection_reason,
            'waiver_reason' => $i->waiver_reason,
            'waiver_change_request_id' => $i->waiver_change_request_id,
            'waiver_authority' => $i->waiver_authority,
            'verified_by' => $i->verified_by,
            'verified_at' => $i->verified_at?->toIso8601ZuluString('microsecond'),
            'updated_at' => $i->updated_at->toIso8601ZuluString('microsecond'),
        ];
    }

    /**
     * @param  list<DocumentVersion>  $versions  ordered by version_no
     * @return array<string, mixed>
     */
    public static function document(Document $d, array $versions): array
    {
        $out = array_map(static fn (DocumentVersion $v): array => self::version($v, $d), $versions);

        return [
            'id' => $d->id,
            'application_id' => $d->application_id,
            'checklist_item_id' => $d->checklist_item_id,
            'party_id' => $d->party_id,
            'document_type' => $d->document_type,
            'title' => $d->title,
            'latest_version' => $out === [] ? null : $out[count($out) - 1],
            'versions' => $out,
            'created_at' => $d->created_at->toIso8601ZuluString('microsecond'),
        ];
    }

    /** @return array<string, mixed> */
    public static function version(DocumentVersion $v, Document $d): array
    {
        return [
            'id' => $v->id,
            'version_no' => $v->version_no,
            'filename' => $v->filename,
            'mime_type' => $v->mime_type,
            'size_bytes' => $v->size_bytes,
            'sha256' => $v->sha256,
            'scan_status' => $v->scan_status,
            'scan_signature' => $v->scan_signature,
            'scanner' => $v->scanner,
            'uploaded_by' => $v->uploaded_by,
            'uploaded_at' => $v->uploaded_at->toIso8601ZuluString('microsecond'),
            'duplicates' => self::duplicates($v, $d),
        ];
    }

    /**
     * The same bytes anywhere else in the tenant (FR-DOC-006, hash match).
     *
     * @return list<array{document_id: string, application_id: string, application_reference: string, same_application: bool}>
     */
    public static function duplicates(DocumentVersion $v, Document $d): array
    {
        $rows = DB::table('document_versions as dv')
            ->join('documents as doc', 'doc.id', '=', 'dv.document_id')
            ->where('dv.sha256', $v->sha256)->where('dv.id', '<>', $v->id)->where('doc.id', '<>', $d->id)
            ->distinct()->get(['doc.id as document_id', 'doc.application_id']);
        $reader = app(ApplicationReader::class);
        $out = [];
        foreach ($rows as $r) {
            $appId = (string) $r->application_id;
            $out[] = ['document_id' => (string) $r->document_id, 'application_id' => $appId, 'application_reference' => $reader->find($appId)->reference ?? '', 'same_application' => $appId === $d->application_id];
        }

        return $out;
    }
}
