<?php

declare(strict_types=1);

/* P1 Credit slice: bureau, decision runs, snapshots + replay, exceptions, credit memo (FR-CRD-003..015, FR-CMP-020/027/043). */

require_once __DIR__.'/_helpers.php';

$reason = ['type' => 'object', 'properties' => ['code' => OA_STR, 'text_key' => OA_STR, 'stage' => ['type' => 'string', 'enum' => ['knockout', 'policy', 'grade', 'affordability', 'pricing', 'outcome']]], 'required' => ['code', 'text_key', 'stage']];
$terms = ['type' => 'object', 'properties' => ['amount' => oa_ref('Money'), 'tenor_months' => OA_INT, 'rate_percent' => OA_STR, 'monthly_instalment' => ['oneOf' => [oa_ref('Money'), ['type' => 'null']]]], 'required' => ['amount', 'tenor_months', 'rate_percent', 'monthly_instalment']];

$paths = [
    '/api/v1/applications/{id}/bureau-reports' => ['get' => oa_op('Bureau reports pulled for an application', 'listBureauReports', 'Credit', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('BureauReport')])],
    ])],
    '/api/v1/applications/{id}/bureau-reports/actions/pull' => ['post' => oa_op('Pull a credit bureau report (requires the party\'s credit_bureau consent)', 'pullBureauReport', 'Credit', 'bureau:pull', [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')], [
        '201' => ['description' => 'Pulled and parsed into the canonical profile', 'content' => oa_data(oa_ref('BureauReport'))],
    ], ['type' => 'object', 'properties' => ['party_id' => ['type' => ['string', 'null'], 'format' => 'uuid', 'description' => 'Defaults to the primary applicant']]], ['x-idempotency-key' => 'required'])],
    '/api/v1/applications/{id}/decisions' => ['get' => oa_op('Decision snapshots for an application, newest first', 'listDecisions', 'Credit', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('Decision')])],
    ])],
    '/api/v1/applications/{id}/decisions/actions/run' => ['post' => oa_op('Run the bound rule set (knock-out → policy → grade → affordability → pricing → outcome) and store an immutable snapshot', 'runDecision', 'Credit', 'credit:analyse', [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')], [
        '201' => ['description' => 'Decision recorded', 'content' => oa_data(oa_ref('Decision'))],
    ], null, ['x-idempotency-key' => 'required'])],
    '/api/v1/decisions/{id}' => ['get' => oa_op('Get a decision snapshot (inputs, versions, outputs, trace)', 'getDecision', 'Credit', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(oa_ref('Decision'))],
    ])],
    '/api/v1/decisions/{id}/actions/replay' => ['post' => oa_op('Replay a snapshot with its recorded rule-set version and evaluator version', 'replayDecision', 'Credit', 'application:view', [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')], [
        '200' => ['description' => 'Replay result', 'content' => oa_data(['type' => 'object', 'properties' => [
            'identical' => OA_BOOL, 'evaluator_version' => OA_STR, 'rule_set_version_id' => OA_UUID,
            'differences' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['path' => OA_STR, 'recorded' => true, 'replayed' => true], 'required' => ['path']]],
        ], 'required' => ['identical', 'evaluator_version', 'rule_set_version_id', 'differences']])],
    ], null, ['x-idempotency-key' => 'required'])],
    '/api/v1/decisions/{id}/exceptions' => ['post' => oa_op('Record a policy exception / override against a decision (escalates approval authority)', 'raiseException', 'Credit', 'exception:raise', [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')], [
        '201' => ['description' => 'Recorded', 'content' => oa_data(oa_ref('PolicyException'))],
    ], ['type' => 'object', 'properties' => [
        'reason_code' => OA_STR, 'justification' => OA_STR, 'evidence_ref' => OA_NSTR, 'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']],
    ], 'required' => ['reason_code', 'justification', 'severity']], ['x-idempotency-key' => 'required'])],
    '/api/v1/applications/{id}/exceptions' => ['get' => oa_op('Policy exceptions on an application', 'listExceptions', 'Credit', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('PolicyException')])],
    ])],
    '/api/v1/applications/{id}/credit-memo' => [
        'get' => oa_op('Credit memo: latest version, all versions, and a draft of the auto-populated sections', 'getCreditMemo', 'Credit', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
            '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'object', 'properties' => [
                'latest' => ['oneOf' => [oa_ref('CreditMemo'), ['type' => 'null']]],
                'versions' => ['type' => 'array', 'items' => oa_ref('CreditMemo')],
                'draft_sections' => ['type' => 'array', 'items' => oa_ref('MemoSection')],
            ], 'required' => ['latest', 'versions', 'draft_sections']])],
        ]),
        'post' => oa_op('Save a new memo version (sections auto-populated from the latest decision, plus the analyst narrative and recommendation)', 'saveCreditMemo', 'Credit', 'credit:analyse', [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')], [
            '201' => ['description' => 'Saved', 'content' => oa_data(oa_ref('CreditMemo'))],
        ], ['type' => 'object', 'properties' => [
            'narrative' => OA_STR, 'recommendation' => ['type' => 'string', 'enum' => ['approve', 'decline', 'counter_offer']],
            'recommended_amount' => OA_NSTR, 'recommended_tenor_months' => ['type' => ['integer', 'null']], 'conditions' => ['type' => 'array', 'items' => OA_STR],
        ], 'required' => ['narrative', 'recommendation']], ['x-idempotency-key' => 'required']),
    ],
];

$schemas = [
    'BureauReport' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'application_id' => OA_UUID, 'party_id' => OA_UUID, 'party_name' => OA_STR, 'bureau' => OA_STR, 'report_reference' => OA_STR,
        'pulled_at' => OA_TS, 'valid_until' => OA_TS, 'is_valid' => OA_BOOL, 'hit' => OA_BOOL,
        'profile' => ['type' => 'object', 'properties' => [
            'score' => ['type' => ['integer', 'null']], 'active_facilities' => OA_INT, 'total_outstanding' => oa_ref('Money'), 'monthly_obligations' => oa_ref('Money'),
            'max_dpd_12m' => OA_INT, 'delinquent_facilities' => OA_INT, 'enquiries_6m' => OA_INT, 'has_write_off' => OA_BOOL,
            'facilities' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'lender' => OA_STR, 'type' => OA_STR, 'outstanding' => oa_ref('Money'), 'monthly_instalment' => oa_ref('Money'), 'dpd' => OA_INT, 'status' => OA_STR,
            ], 'required' => ['lender', 'type', 'outstanding', 'monthly_instalment', 'dpd', 'status']]],
        ], 'required' => ['score', 'active_facilities', 'total_outstanding', 'monthly_obligations', 'max_dpd_12m', 'delinquent_facilities', 'enquiries_6m', 'has_write_off', 'facilities']],
        'pulled_by' => OA_STR,
    ], 'required' => ['id', 'application_id', 'party_id', 'bureau', 'report_reference', 'pulled_at', 'valid_until', 'is_valid', 'hit', 'profile', 'pulled_by']],
    'Decision' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'application_id' => OA_UUID, 'sequence' => OA_INT,
        'outcome' => ['type' => 'string', 'enum' => ['approve', 'refer', 'decline', 'counter_offer']], 'risk_grade' => OA_STR,
        'requested_terms' => $terms, 'recommended_terms' => $terms,
        'reason_codes' => ['type' => 'array', 'items' => $reason],
        'affordability' => ['type' => 'object', 'properties' => [
            'monthly_income' => ['oneOf' => [oa_ref('Money'), ['type' => 'null']]], 'existing_obligations' => ['oneOf' => [oa_ref('Money'), ['type' => 'null']]],
            'new_instalment' => ['oneOf' => [oa_ref('Money'), ['type' => 'null']]], 'dsr_percent' => OA_NSTR, 'max_dsr_percent' => OA_NSTR, 'passed' => ['type' => ['boolean', 'null']],
            'max_affordable_amount' => ['oneOf' => [oa_ref('Money'), ['type' => 'null']]],
        ], 'required' => ['dsr_percent', 'max_dsr_percent', 'passed']],
        'rule_set' => ['type' => 'object', 'properties' => ['key' => OA_STR, 'version_id' => OA_UUID, 'version_no' => OA_INT], 'required' => ['key', 'version_id', 'version_no']],
        'evaluator_version' => OA_STR, 'bureau_report_id' => OA_NUUID,
        'facts' => ['type' => 'object'], 'trace' => ['type' => 'array', 'items' => ['type' => 'object']],
        'exceptions' => ['type' => 'array', 'items' => oa_ref('PolicyException')],
        'decided_by' => OA_STR, 'decided_at' => OA_TS, 'is_latest' => OA_BOOL,
    ], 'required' => ['id', 'application_id', 'sequence', 'outcome', 'risk_grade', 'requested_terms', 'recommended_terms', 'reason_codes', 'affordability', 'rule_set', 'evaluator_version', 'bureau_report_id', 'facts', 'trace', 'exceptions', 'decided_by', 'decided_at', 'is_latest']],
    'PolicyException' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'application_id' => OA_UUID, 'decision_id' => OA_UUID, 'reason_code' => OA_STR, 'justification' => OA_STR, 'evidence_ref' => OA_NSTR,
        'severity' => ['type' => 'string', 'enum' => ['low', 'medium', 'high']], 'raised_by' => OA_STR, 'raised_at' => OA_TS,
    ], 'required' => ['id', 'application_id', 'decision_id', 'reason_code', 'justification', 'severity', 'raised_by', 'raised_at']],
    'MemoSection' => ['type' => 'object', 'properties' => ['key' => OA_STR, 'title' => OA_STR, 'content' => ['type' => 'object']], 'required' => ['key', 'title', 'content']],
    'CreditMemo' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'application_id' => OA_UUID, 'version_no' => OA_INT, 'decision_id' => OA_UUID,
        'sections' => ['type' => 'array', 'items' => oa_ref('MemoSection')], 'narrative' => OA_STR,
        'recommendation' => ['type' => 'string', 'enum' => ['approve', 'decline', 'counter_offer']],
        'recommended_amount' => ['oneOf' => [oa_ref('Money'), ['type' => 'null']]], 'recommended_tenor_months' => ['type' => ['integer', 'null']],
        'conditions' => ['type' => 'array', 'items' => OA_STR], 'authored_by' => OA_STR, 'authored_at' => OA_TS,
    ], 'required' => ['id', 'application_id', 'version_no', 'decision_id', 'sections', 'narrative', 'recommendation', 'recommended_amount', 'recommended_tenor_months', 'conditions', 'authored_by', 'authored_at']],
];

return ['tags' => ['Credit'], 'paths' => $paths, 'schemas' => $schemas];
