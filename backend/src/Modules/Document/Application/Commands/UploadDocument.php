<?php

declare(strict_types=1);

namespace Fundly\Modules\Document\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Upload a document or a new version of one (FR-DOC-001/002/004/005/006). */
#[HandledBy(UploadDocumentHandler::class)]
final readonly class UploadDocument implements Command, ValidatesInput
{
    public function __construct(
        public string $applicationId,
        public string $tempPath,
        public string $originalName,
        public string $documentType,
        public ?string $checklistItemId,
        public ?string $partyId,
        public ?string $title,
    ) {}

    public function action(): string
    {
        return 'document.uploaded';
    }

    public function permission(): string
    {
        return 'document:upload';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }

    public function data(): array
    {
        return ['document_type' => $this->documentType, 'checklist_item_id' => $this->checklistItemId, 'party_id' => $this->partyId, 'title' => $this->title, 'file' => $this->tempPath];
    }

    public function rules(): array
    {
        return [
            'document_type' => ['required', 'string', 'regex:/^[A-Za-z0-9_-]{2,48}$/'],
            'checklist_item_id' => ['nullable', 'uuid'],
            'party_id' => ['nullable', 'uuid'],
            'title' => ['nullable', 'string', 'max:200'],
            'file' => ['required', 'string'],
        ];
    }
}
