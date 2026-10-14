<?php

declare(strict_types=1);

namespace Fundly\Modules\Workflow\Http\Controllers;

use Fundly\Modules\Workflow\Application\Commands\AddCaseNote;
use Fundly\Modules\Workflow\Application\Commands\BulkAssignTasks;
use Fundly\Modules\Workflow\Application\Commands\ClaimTask;
use Fundly\Modules\Workflow\Application\Commands\DeleteTaskView;
use Fundly\Modules\Workflow\Application\Commands\MigrateWorkflow;
use Fundly\Modules\Workflow\Application\Commands\ReassignTask;
use Fundly\Modules\Workflow\Application\Commands\ReleaseTask;
use Fundly\Modules\Workflow\Application\Commands\SaveTaskView;
use Fundly\Modules\Workflow\Application\NoteQueries;
use Fundly\Modules\Workflow\Application\TaskQueries;
use Fundly\Modules\Workflow\Application\WorkflowFile;
use Fundly\Shared\Bus\CommandBus;
use Fundly\Shared\Http\ApiResponse;
use Fundly\Shared\Security\AuthorizationGate;
use Fundly\Shared\Security\CurrentPrincipal;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class WorkflowController
{
    public function __construct(
        private readonly CommandBus $bus,
        private readonly CurrentPrincipal $principal,
        private readonly TaskQueries $tasks,
        private readonly NoteQueries $notes,
        private readonly WorkflowFile $file,
        private readonly AuthorizationGate $gate,
    ) {}

    public function inbox(Request $request): JsonResponse
    {
        $filter = (array) $request->query('filter', []);

        return ApiResponse::json($this->tasks->inbox($this->principal->require(), $filter, (string) $request->query('sort', 'due')));
    }

    public function claim(string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new ClaimTask($id), $this->principal->require()));
    }

    public function release(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new ReleaseTask($id, self::str($request->input('reason'))), $this->principal->require()));
    }

    public function reassign(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new ReassignTask($id, self::str($request->input('assignee_id')), self::str($request->input('reason'))), $this->principal->require()));
    }

    public function bulkAssign(Request $request): JsonResponse
    {
        $ids = array_values(array_filter((array) $request->input('task_ids', []), 'is_string'));

        return ApiResponse::resource($this->bus->dispatch(new BulkAssignTasks($ids, self::str($request->input('assignee_id')), self::str($request->input('reason'))), $this->principal->require()));
    }

    public function candidates(string $id): JsonResponse
    {
        $task = $this->file->task($id);
        $this->gate->authorize($this->principal->require(), 'task:reassign', $task->resource());

        return ApiResponse::json(['data' => $this->tasks->candidates($task)]);
    }

    public function mentionable(string $id): JsonResponse
    {
        $app = $this->file->application($id);
        $this->gate->authorize($this->principal->require(), 'application:view', $this->file->resource($app));

        return ApiResponse::json(['data' => $this->tasks->people('application:view', $this->file->resource($app))]);
    }

    public function forApplication(string $id): JsonResponse
    {
        $app = $this->file->application($id);
        $this->gate->authorize($this->principal->require(), 'application:view', $this->file->resource($app));

        return ApiResponse::json(['data' => $this->tasks->forApplication($app->id)]);
    }

    public function migrate(Request $request, string $id): JsonResponse
    {
        return ApiResponse::resource($this->bus->dispatch(new MigrateWorkflow($id, self::str($request->input('reason'))), $this->principal->require()));
    }

    public function notes(string $id): JsonResponse
    {
        $app = $this->file->application($id);
        $this->gate->authorize($this->principal->require(), 'application:view', $this->file->resource($app));

        return ApiResponse::json(['data' => $this->notes->forApplication($app->id)]);
    }

    public function addNote(Request $request, string $id): JsonResponse
    {
        $mentions = array_values(array_filter((array) $request->input('mention_user_ids', []), 'is_string'));

        return ApiResponse::resource($this->bus->dispatch(new AddCaseNote($id, self::str($request->input('body')), (string) $request->input('visibility', 'internal'), $mentions), $this->principal->require()), 201);
    }

    public function views(): JsonResponse
    {
        return ApiResponse::json($this->tasks->views($this->principal->require()));
    }

    public function saveView(Request $request): JsonResponse
    {
        $filters = array_filter((array) $request->input('filters', []), 'is_string');

        return ApiResponse::resource($this->bus->dispatch(new SaveTaskView(self::str($request->input('name')), $filters), $this->principal->require()), 201);
    }

    public function deleteView(string $id): JsonResponse
    {
        $this->bus->dispatch(new DeleteTaskView($id), $this->principal->require());

        return ApiResponse::noContent();
    }

    private static function str(mixed $v): ?string
    {
        return is_string($v) && trim($v) !== '' ? trim($v) : null;
    }
}
