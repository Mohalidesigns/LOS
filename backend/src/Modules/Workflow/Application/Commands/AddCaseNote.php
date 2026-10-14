<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

#[HandledBy(AddCaseNoteHandler::class)]
final readonly class AddCaseNote implements Command, ValidatesInput
{
    public function __construct(public string $applicationId, public ?string $body, public string $visibility, /** @var list<string> */ public array $mentionUserIds) {}

    public function action(): string
    {
        return 'workflow.note_added';
    }

    public function permission(): string
    {
        return 'application:view';
    }

    public function resource(): ResourceRef
    {
        return new ResourceRef('application', $this->applicationId);
    }

    public function data(): array
    {
        return ['body' => $this->body, 'visibility' => $this->visibility, 'mention_user_ids' => $this->mentionUserIds];
    }

    public function rules(): array
    {
        return ['body' => ['required', 'string', 'min:2', 'max:4000'], 'visibility' => ['required', 'in:internal,customer'], 'mention_user_ids' => ['array', 'max:10'], 'mention_user_ids.*' => ['uuid']];
    }
}
