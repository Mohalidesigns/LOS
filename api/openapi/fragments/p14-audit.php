<?php

declare(strict_types=1);

/* P1 Audit (P1-AUD-01): read-access log, overrides/waivers report, evidence pack v1 (FR-AUD-006/009/010/016). The audit explorer itself is /api/v1/audit-events; integration calls are /api/v1/integration-calls. */

require_once __DIR__.'/_helpers.php';

$person = ['type' => ['object', 'null'], 'properties' => ['id' => OA_STR, 'name' => OA_NSTR], 'required' => ['id', 'name']];
$page = [['$ref' => '#/components/parameters/PageSize'], ['$ref' => '#/components/parameters/PageAfter']];
$period = [oa_filter('from', 'ISO 8601, inclusive'), oa_filter('to', 'ISO 8601, exclusive')];
$purpose = ['name' => 'X-Access-Purpose', 'in' => 'header', 'required' => false, 'schema' => ['type' => 'string', 'pattern' => '^[a-z][a-z0-9_]{1,63}$'], 'description' => 'Why the data is read (recorded in the read-access log)'];

$paths = [
    '/api/v1/audit/read-access' => ['get' => oa_op('Read-access log: who read application or PII data, when and for what purpose; newest first', 'searchReadAccess', 'Audit', 'audit:read', array_merge($page, [
        oa_filter('actor_id', 'user id'), oa_filter('resource_type', 'application | party | document | decision'), oa_filter('resource_id', 'id'), oa_filter('purpose', 'purpose code'),
        oa_filter('pii', 'true: PII reads only'), oa_filter('correlation_id', 'request correlation id'),
    ], $period, [oa_param('CorrelationId')]), ['200' => ['description' => 'OK', 'content' => oa_list('ReadAccessRecord')]])],
    '/api/v1/audit/overrides' => ['get' => oa_op('Overrides, waivers, policy exceptions and manual interventions across a period, with counts by type and by user', 'overridesReport', 'Audit', 'audit:read', array_merge($page, [
        oa_filter('type', 'policy_exception | waiver | manual_intervention'), oa_filter('actor_id', 'who performed or approved it'),
    ], $period, [oa_param('CorrelationId')]), ['200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
        'data' => ['type' => 'array', 'items' => oa_ref('OverrideRecord')],
        'meta' => ['type' => 'object', 'properties' => [
            'page' => ['type' => 'object'],
            'summary' => ['type' => 'object', 'properties' => [
                'by_type' => ['type' => 'object', 'properties' => ['policy_exception' => OA_INT, 'waiver' => OA_INT, 'manual_intervention' => OA_INT], 'required' => ['policy_exception', 'waiver', 'manual_intervention']],
                'by_user' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['user' => $person, 'count' => OA_INT], 'required' => ['user', 'count']]],
            ], 'required' => ['by_type', 'by_user']],
        ], 'required' => ['page', 'summary']],
    ], 'required' => ['data', 'meta']]]]]])],
    '/api/v1/applications/{id}/evidence-pack' => ['get' => oa_op('Evidence pack v1 (ZIP): one JSON file per section (application, timeline, documents, checklist, decisions, exceptions, bureau reports, approvals, screening, conditions, offers, communications), the latest clean version of every document, and manifest.json with the SHA-256 of every file. Unsigned until P5. The export is audited.', 'exportEvidencePack', 'Audit', 'audit:read', [oa_param('Id'), $purpose, oa_param('CorrelationId')], [
        '200' => ['description' => 'The pack', 'headers' => ['X-Manifest-SHA256' => ['schema' => OA_STR, 'description' => 'SHA-256 of manifest.json']], 'content' => ['application/zip' => ['schema' => ['type' => 'string', 'format' => 'binary']]]],
    ])],
];

$schemas = [
    'ReadAccessRecord' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'occurred_at' => OA_TS, 'actor_type' => OA_STR, 'actor_id' => OA_STR, 'on_behalf_of' => OA_NSTR,
        'resource_type' => OA_STR, 'resource_id' => OA_NSTR, 'route' => OA_STR, 'contains_pii' => OA_BOOL, 'purpose' => OA_NSTR,
        'source_ip' => OA_NSTR, 'correlation_id' => OA_NSTR,
    ], 'required' => ['id', 'occurred_at', 'actor_type', 'actor_id', 'on_behalf_of', 'resource_type', 'resource_id', 'route', 'contains_pii', 'purpose', 'source_ip', 'correlation_id']],
    'OverrideRecord' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'occurred_at' => OA_TS, 'type' => ['type' => 'string', 'enum' => ['policy_exception', 'waiver', 'manual_intervention']], 'action' => OA_STR,
        'entity_type' => OA_NSTR, 'entity_id' => OA_NSTR, 'application_id' => OA_NSTR, 'performed_by' => $person, 'requested_by' => $person,
        'change_request_id' => OA_NSTR, 'reason_code' => OA_NSTR, 'reason' => OA_NSTR, 'details' => ['type' => 'object'],
    ], 'required' => ['id', 'occurred_at', 'type', 'action', 'entity_type', 'entity_id', 'application_id', 'performed_by', 'requested_by', 'change_request_id', 'reason_code', 'reason', 'details']],
];

return ['tags' => [], 'paths' => $paths, 'schemas' => $schemas];
