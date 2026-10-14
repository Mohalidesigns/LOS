<?php

declare(strict_types=1);

/* P1 Conditions slice: CP/CS register, waivers via change requests, final pre-disbursement check (FR-CPR-001..005). */

require_once __DIR__.'/_helpers.php';

$person = ['type' => ['object', 'null'], 'properties' => ['id' => OA_STR, 'name' => OA_NSTR], 'required' => ['id', 'name']];
$p = [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')];

$paths = [
    '/api/v1/applications/{id}/conditions' => [
        'get' => oa_op('Conditions, recent pre-disbursement checks and release readiness for an application', 'getApplicationConditions', 'Conditions', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
            '200' => ['description' => 'OK', 'content' => oa_data(oa_ref('ConditionRegister'))],
        ]),
        'post' => oa_op('Add a condition precedent or subsequent', 'addCondition', 'Conditions', 'condition:manage', $p, [
            '201' => ['description' => 'Added', 'content' => oa_data(oa_ref('Condition'))],
        ], ['type' => 'object', 'properties' => [
            'kind' => ['type' => 'string', 'enum' => ['precedent', 'subsequent']], 'description' => OA_STR, 'mandatory' => OA_BOOL,
            'owner_id' => OA_NUUID, 'due_date' => ['type' => ['string', 'null'], 'format' => 'date'],
        ], 'required' => ['kind', 'description']], ['x-idempotency-key' => 'required']),
    ],
    '/api/v1/conditions/{id}/actions/satisfy' => ['post' => [
        'summary' => 'Sign off a condition with evidence: an uploaded file and/or a reference',
        'operationId' => 'satisfyCondition', 'tags' => ['Conditions'], 'x-permission' => 'condition:manage', 'x-idempotency-key' => 'required',
        'parameters' => $p,
        'requestBody' => ['required' => true, 'content' => [
            'multipart/form-data' => ['schema' => ['type' => 'object', 'properties' => ['file' => ['type' => 'string', 'format' => 'binary'], 'note' => OA_STR, 'evidence_reference' => OA_STR], 'required' => ['note']]],
            'application/json' => ['schema' => ['type' => 'object', 'properties' => ['note' => OA_STR, 'evidence_reference' => OA_STR], 'required' => ['note', 'evidence_reference']]],
        ]],
        'responses' => ['200' => ['description' => 'Satisfied', 'content' => oa_data(oa_ref('Condition'))], 'default' => ['$ref' => '#/components/responses/Problem']],
        'security' => [['sessionCookie' => []], ['bearerToken' => []]],
    ]],
    '/api/v1/conditions/{id}/actions/request-waiver' => ['post' => oa_op('Request a waiver; a checker holding condition:waive_approve decides (maker-checker)', 'requestConditionWaiver', 'Conditions', 'condition:manage', $p, [
        '202' => ['description' => 'Change request awaiting a checker', 'content' => oa_data(oa_ref('ChangeRequest'))],
    ], ['type' => 'object', 'properties' => ['reason' => OA_STR], 'required' => ['reason']], ['x-idempotency-key' => 'required'])],
    '/api/v1/applications/{id}/pre-disbursement-checks' => ['post' => oa_op('Run the final pre-disbursement check: KYC, re-screen, executed agreement, CPs, approval validity and destination name enquiry. Pass or fail, the result is recorded.', 'runPreDisbursementCheck', 'Conditions', 'condition:manage', $p, [
        '201' => ['description' => 'Recorded (see passed)', 'content' => oa_data(oa_ref('PreDisbursementCheck'))],
    ], ['type' => 'object', 'properties' => ['bank_code' => OA_STR, 'account_no' => OA_STR], 'required' => ['bank_code', 'account_no']], ['x-idempotency-key' => 'required'])],
];

$schemas = [
    'Condition' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'application_id' => OA_UUID, 'kind' => ['type' => 'string', 'enum' => ['precedent', 'subsequent']],
        'source' => ['type' => 'string', 'enum' => ['approval', 'manual']], 'description' => OA_STR, 'mandatory' => OA_BOOL,
        'owner' => $person, 'due_date' => ['type' => ['string', 'null'], 'format' => 'date'], 'is_overdue' => OA_BOOL,
        'status' => ['type' => 'string', 'enum' => ['open', 'waiver_pending', 'satisfied', 'waived']],
        'evidence' => ['type' => ['object', 'null'], 'properties' => ['document_id' => OA_NUUID, 'version_id' => OA_NUUID, 'reference' => OA_NSTR], 'required' => ['document_id', 'version_id', 'reference']],
        'signoff' => ['type' => ['object', 'null'], 'properties' => ['by' => $person, 'at' => OA_TS, 'note' => OA_NSTR], 'required' => ['by', 'at', 'note']],
        'waiver' => ['type' => ['object', 'null'], 'properties' => ['change_request_id' => OA_UUID, 'reason' => OA_NSTR], 'required' => ['change_request_id', 'reason']],
        'created_by' => $person, 'created_at' => OA_TS,
    ], 'required' => ['id', 'application_id', 'kind', 'source', 'description', 'mandatory', 'owner', 'due_date', 'is_overdue', 'status', 'evidence', 'signoff', 'waiver', 'created_by', 'created_at']],
    'PreDisbursementCheck' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'sequence' => OA_INT, 'passed' => OA_BOOL,
        'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'code' => ['type' => 'string', 'enum' => ['kyc_current', 'screening_clear', 'documentation_executed', 'conditions_precedent', 'approval_valid', 'account_verified', 'security_perfected', 'insurance_in_force', 'regulatory_limits']],
            'label' => OA_STR, 'status' => ['type' => 'string', 'enum' => ['pass', 'fail', 'not_applicable']], 'detail' => OA_STR,
        ], 'required' => ['code', 'label', 'status', 'detail']]],
        'destination' => ['type' => 'object', 'properties' => ['bank_code' => OA_STR, 'account' => OA_STR, 'account_name' => OA_NSTR, 'name_match_score' => ['type' => ['integer', 'null']]], 'required' => ['bank_code', 'account', 'account_name', 'name_match_score']],
        'screening' => ['type' => 'object', 'properties' => ['runs' => OA_INT, 'new_hits' => OA_INT, 'open_alerts' => OA_INT], 'required' => ['runs', 'new_hits', 'open_alerts']],
        'checked_by' => $person, 'checked_at' => OA_TS, 'valid_until' => OA_TS, 'is_current' => OA_BOOL,
    ], 'required' => ['id', 'sequence', 'passed', 'items', 'destination', 'screening', 'checked_by', 'checked_at', 'valid_until', 'is_current']],
    'ConditionRegister' => ['type' => 'object', 'properties' => [
        'application_id' => OA_UUID, 'status' => OA_STR,
        'conditions' => ['type' => 'array', 'items' => oa_ref('Condition')],
        'checks' => ['type' => 'array', 'items' => oa_ref('PreDisbursementCheck')],
        'readiness' => ['type' => 'object', 'properties' => ['ready_for_release' => OA_BOOL, 'blockers' => ['type' => 'array', 'items' => OA_STR]], 'required' => ['ready_for_release', 'blockers']],
    ], 'required' => ['application_id', 'status', 'conditions', 'checks', 'readiness']],
];

return ['tags' => ['Conditions'], 'paths' => $paths, 'schemas' => $schemas];
