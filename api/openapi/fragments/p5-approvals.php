<?php

declare(strict_types=1);

/* P1 Approvals slice: matrix-routed requests, votes with authority basis, conditions, validity (FR-APV-001..012). */

require_once __DIR__.'/_helpers.php';

$person = ['type' => 'object', 'properties' => ['id' => OA_STR, 'name' => OA_NSTR], 'required' => ['id', 'name']];
$nmoney = ['oneOf' => [oa_ref('Money'), ['type' => 'null']]];
$decision = ['type' => 'string', 'enum' => ['approve', 'approve_with_conditions', 'decline', 'counter_offer']];
$condition = ['type' => 'object', 'properties' => ['kind' => ['type' => 'string', 'enum' => ['precedent', 'subsequent']], 'description' => OA_STR], 'required' => ['kind', 'description']];
$counter = ['type' => ['object', 'null'], 'properties' => ['amount' => oa_ref('Money'), 'tenor_months' => OA_INT], 'required' => ['amount', 'tenor_months']];
$basis = ['type' => 'object', 'properties' => [
    'assignment_id' => OA_UUID, 'role_code' => OA_STR, 'role_name' => OA_STR, 'delegation_id' => OA_NUUID, 'delegated_by' => OA_NSTR,
    'effective_limit' => oa_ref('Money'), 'limit_source' => ['type' => 'string', 'enum' => ['assignment_scope', 'user', 'role', 'org_unit']],
    'limit_components' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['source' => OA_STR, 'ref' => OA_STR, 'amount' => oa_ref('Money')], 'required' => ['source', 'ref', 'amount']]],
    'level' => ['type' => 'object', 'properties' => ['index' => OA_INT, 'code' => OA_STR, 'name' => OA_STR, 'added_by' => OA_STR], 'required' => ['index', 'code', 'name', 'added_by']],
    'matrix' => ['type' => 'object', 'properties' => ['key' => OA_STR, 'version_no' => OA_INT, 'row' => ['type' => ['integer', 'null']]], 'required' => ['key', 'version_no', 'row']],
    'exposure' => oa_ref('Money'),
], 'required' => ['assignment_id', 'role_code', 'role_name', 'delegation_id', 'delegated_by', 'effective_limit', 'limit_source', 'limit_components']];

$paths = [
    '/api/v1/applications/{id}/approval-requests' => ['get' => oa_op('Approval requests for an application, newest first', 'listApplicationApprovalRequests', 'Approvals', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('ApprovalRequest')])],
    ])],
    '/api/v1/approval-requests' => ['get' => oa_op('Approval inbox: open requests whose current level one of my roles is eligible for', 'listApprovalInbox', 'Approvals', 'application:approve', [oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('ApprovalRequest')])],
    ])],
    '/api/v1/approval-requests/{id}' => ['get' => oa_op('An approval request with its decision packet (FR-APV-010)', 'getApprovalRequest', 'Approvals', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['allOf' => [oa_ref('ApprovalRequest'), ['type' => 'object', 'properties' => ['packet' => oa_ref('ApprovalPacket')], 'required' => ['packet']]]])],
    ])],
    '/api/v1/approval-requests/{id}/actions/vote' => ['post' => oa_op('Vote at the current authority level. Rationale is mandatory; reason codes are required for conditional approval, decline and counter-offer. Above the matrix threshold a step-up within the last 5 minutes is required.', 'voteOnApprovalRequest', 'Approvals', 'application:approve', [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')], [
        '200' => ['description' => 'Vote recorded; the request moved to the next level or reached its outcome', 'content' => oa_data(oa_ref('ApprovalRequest'))],
    ], ['type' => 'object', 'properties' => [
        'decision' => $decision, 'rationale' => OA_STR, 'reason_codes' => ['type' => 'array', 'items' => OA_STR],
        'conditions' => ['type' => 'array', 'items' => $condition],
        'counter_terms' => ['type' => ['object', 'null'], 'properties' => ['amount' => OA_STR, 'tenor_months' => OA_INT], 'required' => ['amount', 'tenor_months']],
    ], 'required' => ['decision', 'rationale']], ['x-idempotency-key' => 'required'])],
];

$schemas = [
    'ApprovalLevel' => ['type' => 'object', 'properties' => [
        'index' => OA_INT, 'code' => OA_STR, 'name' => OA_STR, 'roles' => ['type' => 'array', 'items' => OA_STR], 'mode' => ['type' => 'string', 'enum' => ['single']],
        'added_by' => ['type' => 'string', 'enum' => ['matrix', 'exception', 'no_eligible_approver']], 'note' => OA_NSTR,
        'state' => ['type' => 'string', 'enum' => ['awaiting_vote', 'queued', 'not_reached', 'approve', 'approve_with_conditions', 'decline', 'counter_offer']],
    ], 'required' => ['index', 'code', 'name', 'roles', 'mode', 'added_by', 'note', 'state']],
    'ApprovalVote' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'level_index' => OA_INT, 'level_code' => OA_STR, 'voter' => $person, 'decision' => $decision, 'rationale' => OA_STR,
        'reason_codes' => ['type' => 'array', 'items' => OA_STR], 'conditions' => ['type' => 'array', 'items' => $condition], 'counter_terms' => $counter,
        'authority_basis' => $basis, 'stepped_up' => OA_BOOL, 'step_up_ref' => OA_NSTR, 'voted_at' => OA_TS,
    ], 'required' => ['id', 'level_index', 'level_code', 'voter', 'decision', 'rationale', 'reason_codes', 'conditions', 'counter_terms', 'authority_basis', 'stepped_up', 'step_up_ref', 'voted_at']],
    'ApprovalCondition' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'kind' => ['type' => 'string', 'enum' => ['precedent', 'subsequent']], 'description' => OA_STR,
        'status' => ['type' => 'string', 'enum' => ['open', 'satisfied', 'waived']], 'vote_id' => OA_UUID,
    ], 'required' => ['id', 'kind', 'description', 'status', 'vote_id']],
    'ApprovalRequest' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'application_id' => OA_UUID, 'application_reference' => OA_STR, 'sequence' => OA_INT,
        'status' => ['type' => 'string', 'enum' => ['pending', 'approved', 'declined', 'counter_offered', 'expired', 'superseded']],
        'matrix' => ['type' => 'object', 'properties' => ['key' => OA_STR, 'version_id' => OA_UUID, 'version_no' => OA_INT, 'row' => ['type' => ['integer', 'null']]], 'required' => ['key', 'version_id', 'version_no', 'row']],
        'levels' => ['type' => 'array', 'items' => oa_ref('ApprovalLevel')], 'current_level' => ['type' => ['integer', 'null']],
        'exposure' => oa_ref('Money'), 'decision_id' => OA_NUUID, 'originator' => $person, 'recommended_by' => $person,
        'opened_at' => OA_TS, 'decided_at' => OA_NTS, 'validity_days' => OA_INT, 'valid_until' => OA_NTS, 'counter_terms' => $counter,
        'votes' => ['type' => 'array', 'items' => oa_ref('ApprovalVote')], 'conditions' => ['type' => 'array', 'items' => oa_ref('ApprovalCondition')],
        'facts' => ['type' => 'object'], 'routing_trace' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['row' => OA_INT, 'when' => OA_STR, 'result' => OA_BOOL], 'required' => ['row', 'when', 'result']]],
        'my_eligibility' => ['type' => 'object', 'properties' => [
            'can_vote' => OA_BOOL, 'blockers' => ['type' => 'array', 'items' => OA_STR], 'basis' => ['oneOf' => [['type' => 'null'], ['type' => 'object']]], 'step_up_required' => OA_BOOL,
        ], 'required' => ['can_vote', 'blockers', 'basis', 'step_up_required']],
    ], 'required' => ['id', 'application_id', 'application_reference', 'sequence', 'status', 'matrix', 'levels', 'current_level', 'exposure', 'decision_id', 'originator', 'recommended_by', 'opened_at', 'decided_at', 'validity_days', 'valid_until', 'counter_terms', 'votes', 'conditions', 'facts', 'routing_trace', 'my_eligibility']],
    'ApprovalPacket' => ['type' => 'object', 'properties' => [
        'application' => ['type' => 'object', 'properties' => [
            'id' => OA_UUID, 'reference' => OA_STR, 'status' => OA_STR,
            'product' => ['type' => 'object', 'properties' => ['key' => OA_STR, 'name' => OA_STR, 'version_no' => OA_INT], 'required' => ['key', 'name', 'version_no']],
            'segment' => OA_STR, 'channel' => OA_STR, 'requested_amount' => $nmoney, 'tenor_months' => ['type' => ['integer', 'null']], 'purpose' => OA_NSTR, 'applicant_count' => OA_INT,
        ], 'required' => ['id', 'reference', 'status', 'product', 'segment', 'channel', 'requested_amount', 'tenor_months', 'applicant_count']],
        'credit' => ['oneOf' => [['type' => 'null'], ['type' => 'object', 'properties' => [
            'decision_id' => OA_UUID, 'outcome' => OA_STR, 'risk_grade' => OA_STR,
            'recommended_terms' => ['type' => 'object', 'properties' => ['amount' => oa_ref('Money'), 'tenor_months' => OA_INT, 'rate_percent' => OA_STR], 'required' => ['amount', 'tenor_months', 'rate_percent']],
            'exception_count' => OA_INT, 'max_exception_severity' => OA_NSTR, 'memo_recommendation' => OA_NSTR, 'is_routed_decision' => OA_BOOL,
        ], 'required' => ['decision_id', 'outcome', 'risk_grade', 'recommended_terms', 'exception_count', 'max_exception_severity', 'memo_recommendation', 'is_routed_decision']]]],
        'bureau_valid' => OA_BOOL,
        'history' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'id' => OA_UUID, 'sequence' => OA_INT, 'status' => OA_STR, 'opened_at' => OA_TS, 'decided_at' => OA_NTS,
            'votes' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['level_code' => OA_STR, 'decision' => OA_STR, 'voter_id' => OA_STR, 'voted_at' => OA_TS], 'required' => ['level_code', 'decision', 'voter_id', 'voted_at']]],
        ], 'required' => ['id', 'sequence', 'status', 'opened_at', 'decided_at', 'votes']]],
    ], 'required' => ['application', 'credit', 'bureau_valid', 'history']],
];

return ['tags' => ['Approvals'], 'paths' => $paths, 'schemas' => $schemas];
