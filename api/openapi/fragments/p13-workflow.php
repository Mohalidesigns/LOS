<?php

declare(strict_types=1);

/* P1 Workflow: task inbox (mine / queue / team), claim, release, reassign, bulk assign, saved views, application workflow + migration, case notes (FR-WFL-001/003..010/012). Workflows are config artefacts of type workflow.definition. */

require_once __DIR__.'/_helpers.php';

$person = ['type' => ['object', 'null'], 'properties' => ['id' => OA_STR, 'name' => OA_NSTR], 'required' => ['id', 'name']];
$p = [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')];
$task200 = ['200' => ['description' => 'OK', 'content' => oa_data(oa_ref('WorkflowTask'))]];

$paths = [
    '/api/v1/me/tasks' => ['get' => oa_op('My consolidated task inbox: my tasks, the queues I can work, or (with task:reassign) my team\'s tasks; soonest SLA breach first', 'listMyTasks', 'Workflow', 'application:view', [
        oa_filter('view', 'mine (default) | queue | team'), oa_filter('stage', 'stage code'), oa_filter('sla', 'on_track | at_risk | breached | paused'),
        ['name' => 'sort', 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string', 'enum' => ['due', '-opened_at']]], oa_param('CorrelationId'),
    ], [
        '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
            'data' => ['type' => 'array', 'items' => oa_ref('WorkflowTask')],
            'meta' => ['type' => 'object', 'properties' => [
                'view' => OA_STR, 'counts' => ['type' => 'object', 'properties' => ['mine' => OA_INT, 'mine_breached' => OA_INT], 'required' => ['mine', 'mine_breached']], 'can_reassign' => OA_BOOL,
            ], 'required' => ['view', 'counts', 'can_reassign']],
        ], 'required' => ['data', 'meta']]]]],
    ])],
    '/api/v1/tasks/actions/bulk-assign' => ['post' => oa_op('Bulk claim (no assignee) or, with task:reassign, assign several tasks to one person; all or nothing', 'bulkAssignTasks', 'Workflow', 'application:view', [oa_param('IdempotencyKey'), oa_param('CorrelationId')], [
        '200' => ['description' => 'Assigned', 'content' => oa_data(['type' => 'object', 'properties' => ['assigned' => OA_INT, 'task_ids' => ['type' => 'array', 'items' => OA_UUID]], 'required' => ['assigned', 'task_ids']])],
    ], ['type' => 'object', 'properties' => ['task_ids' => ['type' => 'array', 'items' => OA_UUID], 'assignee_id' => OA_NUUID, 'reason' => OA_NSTR], 'required' => ['task_ids']], ['x-idempotency-key' => 'required'])],
    '/api/v1/tasks/{id}/candidates' => ['get' => oa_op('People who could take this task (hold its permission in its scope), with their open task count', 'listTaskCandidates', 'Workflow', 'task:reassign', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('WorkflowPerson')])],
    ])],
    '/api/v1/applications/{id}/mentionable' => ['get' => oa_op('People who can see the application and so can be @mentioned', 'listMentionable', 'Workflow', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('WorkflowPerson')])],
    ])],
    '/api/v1/tasks/{id}/actions/claim' => ['post' => oa_op('Claim an unassigned task you can work', 'claimTask', 'Workflow', 'application:view', $p, $task200, null, ['x-idempotency-key' => 'required'])],
    '/api/v1/tasks/{id}/actions/release' => ['post' => oa_op('Put your task back in its queue', 'releaseTask', 'Workflow', 'application:view', $p, $task200, ['type' => 'object', 'properties' => ['reason' => OA_NSTR]], ['x-idempotency-key' => 'required'])],
    '/api/v1/tasks/{id}/actions/reassign' => ['post' => oa_op('Reassign a task to someone who can work it, or to the queue (null)', 'reassignTask', 'Workflow', 'task:reassign', $p, $task200, ['type' => 'object', 'properties' => ['assignee_id' => OA_NUUID, 'reason' => OA_STR], 'required' => ['reason']], ['x-idempotency-key' => 'required'])],
    '/api/v1/me/task-views' => [
        'get' => oa_op('My saved inbox views', 'listTaskViews', 'Workflow', 'application:view', [oa_param('CorrelationId')], ['200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('TaskView')])]]),
        'post' => oa_op('Save (or replace by name) an inbox view', 'saveTaskView', 'Workflow', 'application:view', [oa_param('IdempotencyKey'), oa_param('CorrelationId')], ['201' => ['description' => 'Saved', 'content' => oa_data(oa_ref('TaskView'))]],
            ['type' => 'object', 'properties' => ['name' => OA_STR, 'filters' => oa_ref('TaskViewFilters')], 'required' => ['name']], ['x-idempotency-key' => 'required']),
    ],
    '/api/v1/me/task-views/{id}' => ['delete' => oa_op('Delete a saved view', 'deleteTaskView', 'Workflow', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], ['204' => ['description' => 'Deleted']])],
    '/api/v1/applications/{id}/workflow' => ['get' => oa_op('The workflow an application runs on (pinned version), its current task and task history with assignments', 'getApplicationWorkflow', 'Workflow', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(oa_ref('ApplicationWorkflow'))],
    ])],
    '/api/v1/applications/{id}/workflow/actions/migrate' => ['post' => oa_op('Move an in-flight application to the active workflow version (effective from its next stage)', 'migrateApplicationWorkflow', 'Workflow', 'config:activate_request', $p, [
        '200' => ['description' => 'Migrated', 'content' => oa_data(oa_ref('ApplicationWorkflow'))],
    ], ['type' => 'object', 'properties' => ['reason' => OA_STR], 'required' => ['reason']], ['x-idempotency-key' => 'required'])],
    '/api/v1/applications/{id}/notes' => [
        'get' => oa_op('Case notes, newest first', 'listCaseNotes', 'Workflow', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], ['200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('CaseNote')])]]),
        'post' => oa_op('Add a case note; @mentioned users are notified', 'addCaseNote', 'Workflow', 'application:view', $p, ['201' => ['description' => 'Added', 'content' => oa_data(oa_ref('CaseNote'))]],
            ['type' => 'object', 'properties' => ['body' => OA_STR, 'visibility' => ['type' => 'string', 'enum' => ['internal', 'customer']], 'mention_user_ids' => ['type' => 'array', 'items' => OA_UUID]], 'required' => ['body', 'visibility']], ['x-idempotency-key' => 'required']),
    ],
];

$schemas = [
    'WorkflowTask' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID,
        'application' => ['type' => 'object', 'properties' => ['id' => OA_UUID, 'reference' => OA_STR, 'applicant_name' => OA_NSTR], 'required' => ['id', 'reference', 'applicant_name']],
        'stage' => ['type' => 'object', 'properties' => ['code' => OA_STR, 'name' => OA_STR, 'status' => OA_STR], 'required' => ['code', 'name', 'status']],
        'permission' => OA_STR, 'strategy' => ['type' => 'string', 'enum' => ['queue', 'round_robin', 'least_loaded', 'originator']],
        'status' => ['type' => 'string', 'enum' => ['open', 'completed', 'cancelled']], 'assignee' => $person,
        'sla' => ['type' => 'object', 'properties' => [
            'state' => ['type' => 'string', 'enum' => ['none', 'on_track', 'at_risk', 'breached', 'paused']], 'warn_at' => OA_NTS, 'breach_at' => OA_NTS, 'paused_minutes' => OA_INT,
        ], 'required' => ['state', 'warn_at', 'breach_at', 'paused_minutes']],
        'opened_at' => OA_TS, 'closed_at' => OA_NTS, 'close_reason' => OA_NSTR,
        'history' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'kind' => ['type' => 'string', 'enum' => ['routed', 'claimed', 'released', 'reassigned']], 'from' => $person, 'to' => $person, 'by' => $person, 'on_behalf_of' => $person, 'reason' => OA_NSTR, 'at' => OA_TS,
        ], 'required' => ['kind', 'from', 'to', 'by', 'on_behalf_of', 'reason', 'at']]],
    ], 'required' => ['id', 'application', 'stage', 'permission', 'strategy', 'status', 'assignee', 'sla', 'opened_at', 'closed_at', 'close_reason']],
    'ApplicationWorkflow' => ['type' => 'object', 'properties' => [
        'application_id' => OA_UUID,
        'workflow' => ['type' => ['object', 'null'], 'properties' => [
            'key' => OA_STR, 'name' => OA_STR, 'source' => ['type' => 'string', 'enum' => ['default', 'tenant']], 'version_id' => OA_NUUID, 'version_no' => ['type' => ['integer', 'null']], 'pinned_at' => OA_TS, 'migrated_at' => OA_NTS,
        ], 'required' => ['key', 'name', 'source', 'version_id', 'version_no', 'pinned_at', 'migrated_at']],
        'current' => ['oneOf' => [oa_ref('WorkflowTask'), ['type' => 'null']]],
        'tasks' => ['type' => 'array', 'items' => oa_ref('WorkflowTask')],
    ], 'required' => ['application_id', 'workflow', 'current', 'tasks']],
    'WorkflowPerson' => ['type' => 'object', 'properties' => ['id' => OA_UUID, 'name' => OA_NSTR, 'open_tasks' => OA_INT], 'required' => ['id', 'name', 'open_tasks']],
    'TaskViewFilters' => ['type' => 'object', 'properties' => ['view' => ['type' => 'string', 'enum' => ['mine', 'queue', 'team']], 'stage' => OA_STR, 'sla' => ['type' => 'string', 'enum' => ['on_track', 'at_risk', 'breached', 'paused']]]],
    'TaskView' => ['type' => 'object', 'properties' => ['id' => OA_UUID, 'name' => OA_STR, 'filters' => oa_ref('TaskViewFilters')], 'required' => ['id', 'name', 'filters']],
    'CaseNote' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'application_id' => OA_UUID, 'body' => OA_STR, 'visibility' => ['type' => 'string', 'enum' => ['internal', 'customer']],
        'author' => $person, 'mentions' => ['type' => 'array', 'items' => $person], 'created_at' => OA_TS,
    ], 'required' => ['id', 'application_id', 'body', 'visibility', 'author', 'mentions', 'created_at']],
];

return ['tags' => ['Workflow'], 'paths' => $paths, 'schemas' => $schemas];
