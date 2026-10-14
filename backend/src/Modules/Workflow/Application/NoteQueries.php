<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Application;

use Fundly\Modules\Access\Contracts\AuthorityDirectory;
use Fundly\Modules\Workflow\Infrastructure\Models\CaseNote;

final class NoteQueries
{
    public function __construct(private readonly AuthorityDirectory $directory) {}

    /** @return list<array<string, mixed>> newest first */
    public function forApplication(string $applicationId): array
    {
        $notes = CaseNote::query()->where('application_id', $applicationId)->orderByDesc('created_at')->limit(200)->get()->all();
        $ids = [];
        foreach ($notes as $n) {
            $ids = array_merge($ids, [$n->author_id], $n->mentions);
        }
        $names = $this->directory->names(array_values(array_unique($ids)));

        return array_values(array_map(fn (CaseNote $n): array => $this->present($n, $names), $notes));
    }

    /**
     * @param  array<string, string>|null  $names
     * @return array<string, mixed>
     */
    public function present(CaseNote $n, ?array $names = null): array
    {
        $names ??= $this->directory->names(array_values(array_unique(array_merge([$n->author_id], $n->mentions))));

        return [
            'id' => $n->id, 'application_id' => $n->application_id, 'body' => $n->body, 'visibility' => $n->visibility,
            'author' => ['id' => $n->author_id, 'name' => $names[$n->author_id] ?? null],
            'mentions' => array_values(array_map(static fn (string $id): array => ['id' => $id, 'name' => $names[$id] ?? null], $n->mentions)),
            'created_at' => $n->created_at->toIso8601String(),
        ];
    }
}
