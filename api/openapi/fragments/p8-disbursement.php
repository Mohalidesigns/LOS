<?php

declare(strict_types=1);

/* P1 Disbursement slice: maker-checker release, booking saga state, exception queue, reconciliation (FR-DSB-001..011, FR-CBA-009/017, LOS-FR-313). */

require_once __DIR__.'/_helpers.php';

$person = ['type' => ['object', 'null'], 'properties' => ['id' => OA_STR, 'name' => OA_NSTR], 'required' => ['id', 'name']];
$money = ['type' => 'object', 'properties' => ['amount' => OA_STR, 'currency' => OA_STR], 'required' => ['amount', 'currency']];
$p = [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')];
$instalment = ['type' => 'object', 'properties' => ['number' => OA_INT, 'due_date' => OA_STR, 'principal' => OA_STR, 'interest' => OA_STR, 'total' => OA_STR, 'closing_balance' => OA_STR], 'required' => ['number', 'due_date', 'principal', 'interest', 'total', 'closing_balance']];

$paths = [
    '/api/v1/applications/{id}/disbursements' => [
        'get' => oa_op('Disbursement instructions for an application, newest first', 'listApplicationDisbursements', 'Disbursement', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
            '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('Disbursement')])],
        ]),
        'post' => oa_op('Make a disbursement instruction (maker). Release is a change request of type disbursement.release for a checker who is neither the maker nor an approver, with step-up.', 'createDisbursement', 'Disbursement', 'disbursement:make', $p, [
            '201' => ['description' => 'Instruction made; awaiting release', 'content' => oa_data(oa_ref('Disbursement'))],
        ], ['type' => 'object', 'properties' => ['value_date' => ['type' => ['string', 'null'], 'format' => 'date']]], ['x-idempotency-key' => 'required']),
    ],
    '/api/v1/disbursements/{id}/actions/cancel' => ['post' => oa_op('Withdraw an instruction before release', 'cancelDisbursement', 'Disbursement', 'disbursement:make', $p, [
        '200' => ['description' => 'Cancelled', 'content' => oa_data(oa_ref('Disbursement'))],
    ], ['type' => 'object', 'properties' => ['reason' => OA_STR], 'required' => ['reason']], ['x-idempotency-key' => 'required'])],
    '/api/v1/disbursements/{id}/actions/retry' => ['post' => oa_op('Run the booking saga again with the same instruction and step keys (never a second posting)', 'retryDisbursement', 'Disbursement', 'disbursement:retry', $p, [
        '200' => ['description' => 'Queued', 'content' => oa_data(oa_ref('Disbursement'))],
    ], null, ['x-idempotency-key' => 'required'])],
    '/api/v1/disbursement-exceptions' => ['get' => oa_op('Disbursement exception queue: failed (retryable / needing intervention) and compensated instructions', 'listDisbursementExceptions', 'Disbursement', 'disbursement:check', [oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('Disbursement')])],
    ])],
    '/api/v1/reconciliation-runs' => [
        'get' => oa_op('Recent reconciliation runs with their breaks', 'listReconciliationRuns', 'Disbursement', 'reconciliation:run', [oa_param('CorrelationId')], [
            '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('ReconciliationRun')])],
        ]),
        'post' => oa_op('Reconcile LOS disbursement postings with the CBA for a period (default: yesterday and today)', 'runReconciliation', 'Disbursement', 'reconciliation:run', [oa_param('IdempotencyKey'), oa_param('CorrelationId')], [
            '201' => ['description' => 'Run recorded', 'content' => oa_data(oa_ref('ReconciliationRun'))],
        ], ['type' => 'object', 'properties' => ['from' => ['type' => ['string', 'null'], 'format' => 'date'], 'to' => ['type' => ['string', 'null'], 'format' => 'date']]], ['x-idempotency-key' => 'required']),
    ],
    '/api/v1/reconciliation-breaks' => ['get' => oa_op('Open reconciliation breaks', 'listReconciliationBreaks', 'Disbursement', 'reconciliation:run', [oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('ReconciliationBreak')])],
    ])],
    '/api/v1/reconciliation-breaks/{id}/actions/resolve' => ['post' => oa_op('Resolve a break with the investigation outcome', 'resolveReconciliationBreak', 'Disbursement', 'reconciliation:run', $p, [
        '200' => ['description' => 'Resolved', 'content' => oa_data(oa_ref('ReconciliationBreak'))],
    ], ['type' => 'object', 'properties' => ['note' => OA_STR], 'required' => ['note']], ['x-idempotency-key' => 'required'])],
];

$schemas = [
    'Disbursement' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'application_id' => OA_UUID, 'application_reference' => OA_STR, 'sequence' => OA_INT,
        'status' => ['type' => 'string', 'enum' => ['pending_release', 'pending_cba', 'failed_retryable', 'failed_intervention', 'compensated', 'booked', 'cancelled']],
        'principal' => $money, 'net' => $money,
        'fees' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['code' => OA_STR, 'name' => OA_STR, 'amount' => OA_STR], 'required' => ['code', 'name', 'amount']]],
        'value_date' => ['type' => 'string', 'format' => 'date'],
        'destination' => ['type' => 'object', 'properties' => ['bank_code' => OA_STR, 'account' => OA_STR, 'account_name' => OA_NSTR, 'check_id' => OA_UUID], 'required' => ['bank_code', 'account', 'account_name', 'check_id']],
        'change_request_id' => OA_NUUID, 'made_by' => $person, 'made_at' => OA_TS, 'released_by' => $person, 'released_at' => OA_NTS,
        'cba' => ['type' => 'object', 'properties' => ['customer_id' => OA_NSTR, 'loan_account_no' => OA_NSTR, 'posting_ref' => OA_NSTR, 'fee_posting_refs' => ['type' => 'array', 'items' => OA_STR]], 'required' => ['customer_id', 'loan_account_no', 'posting_ref', 'fee_posting_refs']],
        'variance' => ['type' => ['object', 'null'], 'properties' => [
            'within_tolerance' => OA_BOOL, 'tolerance' => OA_STR, 'max_difference' => OA_STR, 'offered_count' => OA_INT, 'booked_count' => OA_INT,
            'mismatches' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['number' => OA_INT, 'field' => OA_STR, 'offered' => OA_NSTR, 'booked' => OA_NSTR, 'difference' => OA_STR], 'required' => ['number', 'field', 'offered', 'booked', 'difference']]],
        ], 'required' => ['within_tolerance', 'tolerance', 'max_difference', 'offered_count', 'booked_count', 'mismatches']],
        'schedule' => ['type' => 'array', 'items' => $instalment],
        'last_error' => ['type' => ['object', 'null'], 'properties' => ['code' => OA_STR, 'message' => OA_NSTR], 'required' => ['code', 'message']],
        'saga_runs' => OA_INT, 'booked_at' => OA_NTS,
        'advice' => ['type' => ['object', 'null'], 'properties' => ['document_id' => OA_UUID, 'version_id' => OA_UUID], 'required' => ['document_id', 'version_id']],
        'cancelled' => ['type' => ['object', 'null'], 'properties' => ['by' => $person, 'at' => OA_TS, 'reason' => OA_NSTR], 'required' => ['by', 'at', 'reason']],
        'steps' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'run' => OA_INT, 'step' => OA_STR, 'outcome' => ['type' => 'string', 'enum' => ['succeeded', 'skipped', 'failed', 'compensated']],
            'detail' => ['type' => 'object'], 'error_code' => OA_NSTR, 'occurred_at' => OA_TS,
        ], 'required' => ['run', 'step', 'outcome', 'detail', 'error_code', 'occurred_at']]],
    ], 'required' => ['id', 'application_id', 'application_reference', 'sequence', 'status', 'principal', 'net', 'fees', 'value_date', 'destination', 'change_request_id', 'made_by', 'made_at', 'released_by', 'released_at', 'cba', 'variance', 'schedule', 'last_error', 'saga_runs', 'booked_at', 'advice', 'cancelled', 'steps']],
    'ReconciliationBreak' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'run_id' => OA_UUID, 'kind' => ['type' => 'string', 'enum' => ['missing_in_cba', 'missing_in_los', 'amount_mismatch']],
        'reference' => OA_STR, 'posting_ref' => OA_NSTR, 'account_no' => OA_NSTR, 'los_amount' => OA_NSTR, 'cba_amount' => OA_NSTR, 'currency' => OA_NSTR,
        'disbursement_id' => OA_NUUID, 'status' => ['type' => 'string', 'enum' => ['open', 'resolved']], 'resolved_by' => OA_NSTR, 'resolved_at' => OA_NTS, 'resolution_note' => OA_NSTR,
    ], 'required' => ['id', 'run_id', 'kind', 'reference', 'posting_ref', 'account_no', 'los_amount', 'cba_amount', 'currency', 'disbursement_id', 'status', 'resolved_by', 'resolved_at', 'resolution_note']],
    'ReconciliationRun' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'from' => ['type' => 'string', 'format' => 'date'], 'to' => ['type' => 'string', 'format' => 'date'],
        'los_postings' => OA_INT, 'cba_postings' => OA_INT, 'matched' => OA_INT, 'breaks' => OA_INT, 'ran_by' => OA_STR, 'ran_at' => OA_TS,
        'break_items' => ['type' => 'array', 'items' => oa_ref('ReconciliationBreak')],
    ], 'required' => ['id', 'from', 'to', 'los_postings', 'cba_postings', 'matched', 'breaks', 'ran_by', 'ran_at', 'break_items']],
];

return ['tags' => ['Disbursement'], 'paths' => $paths, 'schemas' => $schemas];
