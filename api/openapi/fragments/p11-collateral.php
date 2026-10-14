<?php

declare(strict_types=1);

/* P1 Collateral: types, cover, valuations, perfection steps, allocations, insurance (FR-COL-001..006, 008). Types are config artefacts of type collateral.type. */

require_once __DIR__.'/_helpers.php';

$person = ['type' => ['object', 'null'], 'properties' => ['id' => OA_STR, 'name' => OA_NSTR], 'required' => ['id', 'name']];
$money = oa_ref('Money');
$nmoney = ['oneOf' => [oa_ref('Money'), ['type' => 'null']]];
$date = ['type' => 'string', 'format' => 'date'];
$ndate = ['type' => ['string', 'null'], 'format' => 'date'];
$doc = ['type' => ['object', 'null'], 'properties' => ['document_id' => OA_UUID, 'version_id' => OA_UUID], 'required' => ['document_id', 'version_id']];
$nbool = ['type' => ['boolean', 'null']];
$p = [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')];
$c201 = static fn (string $d): array => ['201' => ['description' => $d, 'content' => oa_data(oa_ref('Collateral'))]];
$c200 = static fn (string $d): array => ['200' => ['description' => $d, 'content' => oa_data(oa_ref('Collateral'))]];
$cr202 = ['202' => ['description' => 'Change request awaiting a checker holding condition:waive_approve', 'content' => oa_data(oa_ref('ChangeRequest'))]];
$multipart = static fn (string $summary, string $id, array $props, array $required, array $responses): array => [
    'summary' => $summary, 'operationId' => $id, 'tags' => ['Collateral'], 'x-permission' => 'collateral:manage', 'x-idempotency-key' => 'required',
    'parameters' => [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')],
    'requestBody' => ['required' => true, 'content' => [
        'multipart/form-data' => ['schema' => ['type' => 'object', 'properties' => $props + ['file' => ['type' => 'string', 'format' => 'binary']], 'required' => $required]],
        'application/json' => ['schema' => ['type' => 'object', 'properties' => $props, 'required' => $required]],
    ]],
    'responses' => $responses + ['default' => ['$ref' => '#/components/responses/Problem']],
    'security' => [['sessionCookie' => []], ['bearerToken' => []]],
];

$paths = [
    '/api/v1/collateral-types' => ['get' => oa_op('Collateral types the tenant can capture (built-in defaults and tenant versions)', 'listCollateralTypes', 'Collateral', 'application:view', [oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('CollateralType')])],
    ])],
    '/api/v1/applications/{id}/collateral' => ['get' => oa_op('Security file of an application: items allocated, cover and LTV against the product, perfection and insurance blockers', 'getApplicationCollateral', 'Collateral', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(oa_ref('SecurityFile'))],
    ])],
    '/api/v1/collaterals' => [
        'get' => oa_op('Collateral items visible to me, e.g. a party\'s existing security to link to another facility', 'listCollaterals', 'Collateral', 'application:view', [oa_filter('owner_party_id', 'owner party'), oa_filter('application_id', 'items allocated to an application'), oa_param('CorrelationId')], [
            '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('Collateral')])],
        ]),
        'post' => oa_op('Capture a collateral item; its perfection checklist is created and its lending value allocated to the application', 'createCollateral', 'Collateral', 'collateral:manage', [oa_param('IdempotencyKey'), oa_param('CorrelationId')], $c201('Captured'), ['type' => 'object', 'properties' => [
            'application_id' => OA_UUID, 'type_code' => OA_STR, 'description' => OA_STR, 'market_value' => OA_STR,
            'owner_party_id' => OA_NUUID, 'owner_name' => OA_NSTR, 'state_code' => OA_NSTR,
        ], 'required' => ['application_id', 'type_code', 'description', 'market_value']], ['x-idempotency-key' => 'required']),
    ],
    '/api/v1/collaterals/{id}' => ['get' => oa_op('A collateral item with valuations, perfection steps, insurance and allocations', 'getCollateral', 'Collateral', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(oa_ref('Collateral'))],
    ])],
    '/api/v1/collaterals/{id}/valuations' => ['post' => $multipart('Record a valuation (with the report); it becomes the value basis', 'recordCollateralValuation', [
        'valuer' => OA_STR, 'valuation_date' => $date, 'method' => ['type' => 'string', 'enum' => ['market_comparison', 'income', 'cost', 'residual', 'desktop', 'mark_to_market']],
        'market_value' => OA_STR, 'forced_sale_value' => OA_NSTR, 'valid_until' => $ndate, 'note' => OA_NSTR,
    ], ['valuer', 'valuation_date', 'method', 'market_value'], $c201('Recorded'))],
    '/api/v1/collaterals/{id}/insurance-policies' => ['post' => $multipart('Record an insurance policy on the item', 'recordCollateralInsurance', [
        'policy_no' => OA_STR, 'insurer' => OA_STR, 'sum_insured' => OA_STR, 'start_date' => $date, 'expiry_date' => $date, 'bank_interest_noted' => OA_BOOL,
    ], ['policy_no', 'insurer', 'sum_insured', 'start_date', 'expiry_date', 'bank_interest_noted'], $c201('Recorded'))],
    '/api/v1/collaterals/{id}/allocations' => ['post' => oa_op('Set the lending value of the item that secures a facility; 0 releases it; over-allocation is refused', 'allocateCollateral', 'Collateral', 'collateral:manage', $p, $c200('Allocated'), ['type' => 'object', 'properties' => [
        'application_id' => OA_UUID, 'amount' => OA_STR,
    ], 'required' => ['application_id', 'amount']], ['x-idempotency-key' => 'required'])],
    '/api/v1/collaterals/{id}/actions/request-insurance-waiver' => ['post' => oa_op('Request a waiver of the item\'s insurance requirement (maker-checker)', 'requestCollateralInsuranceWaiver', 'Collateral', 'collateral:manage', $p, $cr202, ['type' => 'object', 'properties' => ['reason' => OA_STR], 'required' => ['reason']], ['x-idempotency-key' => 'required'])],
    '/api/v1/perfection-steps/{id}' => ['patch' => oa_op('Assign the responsible party and due date of a perfection step, or start it', 'updatePerfectionStep', 'Collateral', 'collateral:manage', [oa_param('Id'), oa_param('CorrelationId')], $c200('Updated'), ['type' => 'object', 'properties' => [
        'responsible_user_id' => OA_NUUID, 'due_date' => $ndate, 'start' => OA_BOOL,
    ]])],
    '/api/v1/perfection-steps/{id}/actions/complete' => ['post' => $multipart('Complete a perfection step with evidence: an uploaded file and/or a reference', 'completePerfectionStep', [
        'note' => OA_STR, 'evidence_reference' => OA_STR,
    ], ['note'], $c200('Completed'))],
    '/api/v1/perfection-steps/{id}/actions/request-waiver' => ['post' => oa_op('Request a waiver of a perfection step (maker-checker)', 'requestPerfectionStepWaiver', 'Collateral', 'collateral:manage', $p, $cr202, ['type' => 'object', 'properties' => ['reason' => OA_STR], 'required' => ['reason']], ['x-idempotency-key' => 'required'])],
];

$step = ['type' => 'object', 'properties' => [
    'id' => OA_UUID, 'sequence' => OA_INT, 'code' => OA_STR, 'name' => OA_STR, 'timing' => ['type' => 'string', 'enum' => ['precedent', 'subsequent']],
    'responsible' => $person, 'due_date' => $ndate, 'is_overdue' => OA_BOOL,
    'status' => ['type' => 'string', 'enum' => ['pending', 'in_progress', 'completed', 'waiver_pending', 'waived']],
    'evidence' => ['type' => ['object', 'null'], 'properties' => ['document_id' => OA_NUUID, 'version_id' => OA_NUUID, 'reference' => OA_NSTR], 'required' => ['document_id', 'version_id', 'reference']],
    'completion' => ['type' => ['object', 'null'], 'properties' => ['by' => $person, 'at' => OA_TS, 'note' => OA_NSTR], 'required' => ['by', 'at', 'note']],
    'waiver' => ['type' => ['object', 'null'], 'properties' => ['change_request_id' => OA_UUID, 'reason' => OA_NSTR], 'required' => ['change_request_id', 'reason']],
], 'required' => ['id', 'sequence', 'code', 'name', 'timing', 'responsible', 'due_date', 'is_overdue', 'status', 'evidence', 'completion', 'waiver']];

$schemas = [
    'CollateralType' => ['type' => 'object', 'properties' => [
        'code' => OA_STR, 'name' => OA_STR, 'category' => OA_STR, 'haircut_percent' => OA_STR, 'requires_valuation' => OA_BOOL,
        'valuation_validity_months' => ['type' => ['integer', 'null']], 'requires_insurance' => OA_BOOL,
        'perfection_steps' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['code' => OA_STR, 'name' => OA_STR, 'timing' => OA_STR, 'due_days' => ['type' => ['integer', 'null']]], 'required' => ['code', 'name', 'timing', 'due_days']]],
        'source' => ['type' => 'string', 'enum' => ['default', 'tenant']], 'version_id' => OA_NUUID,
    ], 'required' => ['code', 'name', 'category', 'haircut_percent', 'requires_valuation', 'valuation_validity_months', 'requires_insurance', 'perfection_steps', 'source', 'version_id']],
    'Collateral' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'origin_application_id' => OA_UUID,
        'type' => ['type' => 'object', 'properties' => ['code' => OA_STR, 'name' => OA_STR, 'category' => OA_STR, 'source' => OA_STR, 'version_id' => OA_NUUID], 'required' => ['code', 'name', 'category', 'source', 'version_id']],
        'description' => OA_STR,
        'owner' => ['type' => 'object', 'properties' => ['party_id' => OA_NUUID, 'name' => OA_STR], 'required' => ['party_id', 'name']],
        'state_code' => OA_NSTR, 'market_value' => $money, 'haircut_percent' => OA_STR, 'forced_sale_value' => $money,
        'value_basis' => ['type' => 'string', 'enum' => ['declared', 'valuation']], 'status' => ['type' => 'string', 'enum' => ['active', 'released']],
        'allocation' => ['type' => 'object', 'properties' => [
            'total' => $money, 'available' => $money, 'over_allocated' => OA_BOOL,
            'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'id' => OA_UUID, 'application_id' => OA_UUID, 'application_reference' => OA_NSTR, 'amount' => $money, 'status' => ['type' => 'string', 'enum' => ['active', 'released']],
            ], 'required' => ['id', 'application_id', 'application_reference', 'amount', 'status']]],
        ], 'required' => ['total', 'available', 'over_allocated', 'items']],
        'valuation' => ['type' => 'object', 'properties' => [
            'required' => OA_BOOL, 'status' => ['type' => 'string', 'enum' => ['not_required', 'none', 'current', 'due_soon', 'expired']], 'valid_until' => $ndate,
            'history' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'id' => OA_UUID, 'sequence' => OA_INT, 'valuer' => OA_STR, 'valuation_date' => $date, 'method' => OA_STR,
                'market_value' => $money, 'forced_sale_value' => $money, 'valid_until' => $ndate, 'report' => $doc, 'note' => OA_NSTR,
                'recorded_by' => $person, 'recorded_at' => OA_TS,
            ], 'required' => ['id', 'sequence', 'valuer', 'valuation_date', 'method', 'market_value', 'forced_sale_value', 'valid_until', 'report', 'note', 'recorded_by', 'recorded_at']]],
        ], 'required' => ['required', 'status', 'valid_until', 'history']],
        'perfection' => ['type' => 'object', 'properties' => ['precedent_complete' => OA_BOOL, 'steps' => ['type' => 'array', 'items' => $step]], 'required' => ['precedent_complete', 'steps']],
        'insurance' => ['type' => 'object', 'properties' => [
            'requirement' => ['type' => 'string', 'enum' => ['not_required', 'required', 'waiver_pending', 'waived']],
            'status' => ['type' => 'string', 'enum' => ['not_required', 'waived', 'in_force', 'expiring', 'missing', 'expired', 'not_started', 'interest_not_noted']],
            'current_policy_id' => OA_NUUID,
            'waiver' => ['type' => ['object', 'null'], 'properties' => ['change_request_id' => OA_UUID, 'reason' => OA_NSTR], 'required' => ['change_request_id', 'reason']],
            'policies' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
                'id' => OA_UUID, 'policy_no' => OA_STR, 'insurer' => OA_STR, 'sum_insured' => $money, 'start_date' => $date, 'expiry_date' => $date,
                'bank_interest_noted' => OA_BOOL, 'under_insured' => OA_BOOL, 'document' => $doc, 'recorded_by' => $person, 'recorded_at' => OA_TS,
            ], 'required' => ['id', 'policy_no', 'insurer', 'sum_insured', 'start_date', 'expiry_date', 'bank_interest_noted', 'under_insured', 'document', 'recorded_by', 'recorded_at']]],
        ], 'required' => ['requirement', 'status', 'current_policy_id', 'waiver', 'policies']],
        'created_by' => $person, 'created_at' => OA_TS,
    ], 'required' => ['id', 'origin_application_id', 'type', 'description', 'owner', 'state_code', 'market_value', 'haircut_percent', 'forced_sale_value', 'value_basis', 'status', 'allocation', 'valuation', 'perfection', 'insurance', 'created_by', 'created_at']],
    'SecurityFile' => ['type' => 'object', 'properties' => [
        'application_id' => OA_UUID, 'status' => OA_STR, 'exposure' => $money,
        'policy' => ['type' => 'object', 'properties' => ['required' => OA_BOOL, 'min_cover_percent' => OA_NSTR, 'max_ltv_percent' => OA_NSTR], 'required' => ['required', 'min_cover_percent', 'max_ltv_percent']],
        'cover' => ['type' => ['object', 'null'], 'properties' => [
            'lending_value' => $money, 'market_value' => $money, 'cover_percent' => OA_NSTR, 'ltv_percent' => OA_NSTR, 'meets_cover' => $nbool, 'meets_ltv' => $nbool,
        ], 'required' => ['lending_value', 'market_value', 'cover_percent', 'ltv_percent', 'meets_cover', 'meets_ltv']],
        'security' => ['type' => 'object', 'properties' => [
            'applicable' => OA_BOOL, 'perfected' => OA_BOOL, 'insurance_in_force' => OA_BOOL, 'blockers' => ['type' => 'array', 'items' => OA_STR], 'open_subsequent_steps' => OA_INT,
        ], 'required' => ['applicable', 'perfected', 'insurance_in_force', 'blockers', 'open_subsequent_steps']],
        'items' => ['type' => 'array', 'items' => ['allOf' => [oa_ref('Collateral'), ['type' => 'object', 'properties' => ['allocated_here' => $money], 'required' => ['allocated_here']]]]],
    ], 'required' => ['application_id', 'status', 'exposure', 'policy', 'cover', 'security', 'items']],
    'ApprovalPacketCollateral' => ['type' => 'object', 'properties' => [
        'cover_percent' => OA_NSTR, 'ltv_percent' => OA_NSTR, 'min_cover_percent' => OA_NSTR, 'max_ltv_percent' => OA_NSTR, 'meets_cover' => $nbool, 'meets_ltv' => $nbool,
        'lending_value' => $nmoney, 'outstanding_perfection' => OA_INT,
        'items' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'collateral_id' => OA_UUID, 'type_code' => OA_STR, 'description' => OA_STR, 'owner_name' => OA_STR,
            'market_value' => $money, 'forced_sale_value' => $money, 'allocated' => $money, 'open_steps' => OA_INT,
        ], 'required' => ['collateral_id', 'type_code', 'description', 'owner_name', 'market_value', 'forced_sale_value', 'allocated', 'open_steps']]],
    ], 'required' => ['cover_percent', 'ltv_percent', 'min_cover_percent', 'max_ltv_percent', 'meets_cover', 'meets_ltv', 'lending_value', 'outstanding_perfection', 'items']],
];

return ['tags' => ['Collateral'], 'paths' => $paths, 'schemas' => $schemas];
