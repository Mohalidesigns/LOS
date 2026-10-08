<?php

declare(strict_types=1);

/*
 * P1 core contract fragments (products, parties, applications). Run
 * `php api/openapi/fragments/build.php` to splice every fragment into
 * openapi.yaml; the YAML stays the single source of truth for clients and
 * the response validator.
 */

$ref = static fn (string $name): array => ['$ref' => '#/components/schemas/'.$name];
$param = static fn (string $name): array => ['$ref' => '#/components/parameters/'.$name];
$problem = ['$ref' => '#/components/responses/Problem'];
$security = [['sessionCookie' => []], ['bearerToken' => []]];
$data = static fn (array $schema): array => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => $schema], 'required' => ['data']]]];
$etag = ['ETag' => ['$ref' => '#/components/headers/ETag']];
$list = static fn (string $item) => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
    'data' => ['type' => 'array', 'items' => ['$ref' => '#/components/schemas/'.$item]],
    'meta' => ['$ref' => '#/components/schemas/PageMeta'],
], 'required' => ['data', 'meta']]]];
$op = static function (string $summary, string $id, string $tag, string $perm, array $params, array $responses, ?array $body = null, array $extra = []) use ($problem, $security): array {
    $o = ['summary' => $summary, 'operationId' => $id, 'tags' => [$tag], 'x-permission' => $perm, 'parameters' => $params];
    if ($body !== null) {
        $o['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => $body]]];
    }
    $o['responses'] = $responses + ['default' => $problem];
    $o['security'] = $security;

    return $o + $extra;
};
$str = ['type' => 'string'];
$nstr = ['type' => ['string', 'null']];
$uuid = ['type' => 'string', 'format' => 'uuid'];
$nuuid = ['type' => ['string', 'null'], 'format' => 'uuid'];
$ts = ['type' => 'string', 'format' => 'date-time'];
$nts = ['type' => ['string', 'null'], 'format' => 'date-time'];
$dec = ['type' => 'string', 'pattern' => '^[0-9]{1,16}(\\.[0-9]{1,4})?$'];
$statuses = ['draft', 'submitted', 'pre_qualified', 'kyc_screening', 'documentation', 'assessment', 'recommended', 'approval', 'approved', 'counter_offered', 'offer_issued', 'accepted', 'conditions_precedent', 'ready_for_disbursement', 'disbursing', 'booked', 'declined', 'expired', 'on_hold', 'returned_for_rework', 'withdrawn', 'cancelled'];
$status = ['type' => 'string', 'enum' => $statuses];
$nstatus = ['type' => ['string', 'null'], 'enum' => array_merge($statuses, [null])];
$filter = static fn (string $name, string $desc): array => ['name' => "filter[{$name}]", 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => $desc];
$pathParam = static fn (string $name, array $schema): array => ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => $schema];

$paths = [
    '/api/v1/products' => ['get' => $op('List active products (capture catalogue)', 'listProducts', 'Products', 'application:view', [$param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => $ref('ProductSummary')]], 'required' => ['data']]]]],
    ])],
    '/api/v1/products/{key}' => ['get' => $op('Get the active version of a product', 'getProduct', 'Products', 'application:view', [$pathParam('key', ['type' => 'string']), $param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => $data($ref('Product'))],
    ])],
    '/api/v1/parties' => [
        'get' => $op('List parties in scope', 'listParties', 'Parties', 'application:view', [$param('PageSize'), $param('PageAfter'), $filter('type', 'individual | limited_company'), $filter('q', 'Name (fuzzy) or registration number'), $param('CorrelationId')], [
            '200' => ['description' => 'OK', 'content' => $list('PartySummary')],
        ]),
        'post' => $op('Create a party (individual or limited company)', 'createParty', 'Parties', 'party:manage', [$param('IdempotencyKey'), $param('CorrelationId')], [
            '201' => ['description' => 'Created; possible_duplicates lists intake dedupe candidates (FR-CHN-007)', 'content' => $data(['allOf' => [$ref('Party'), ['type' => 'object', 'properties' => ['possible_duplicates' => ['type' => 'array', 'items' => $ref('PartyMatch')]], 'required' => ['possible_duplicates']]]]), 'headers' => $etag],
        ], $ref('PartyCreate'), ['x-idempotency-key' => 'required']),
    ],
    '/api/v1/parties/actions/match' => ['post' => $op('Find existing parties matching identity, contact or name (dedupe)', 'matchParties', 'Parties', 'party:manage', [$param('CorrelationId')], [
        '200' => ['description' => 'Candidates', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => $ref('PartyMatch')]], 'required' => ['data']]]]],
    ], ['type' => 'object', 'properties' => [
        'name' => $str, 'phone' => $str, 'email' => $str, 'tin' => $str, 'registration_number' => $str,
        'identities' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['type' => $str, 'value' => $str], 'required' => ['type', 'value']]],
    ]])],
    '/api/v1/parties/{id}' => ['get' => $op('Get a party (PII masked)', 'getParty', 'Parties', 'application:view', [$param('Id'), $param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => $data($ref('Party')), 'headers' => $etag],
    ])],
    '/api/v1/parties/{id}/relationships' => [
        'get' => $op('Directors, shareholders, signatories and look-through beneficial owners', 'listPartyRelationships', 'Parties', 'application:view', [$param('Id'), $param('CorrelationId')], [
            '200' => ['description' => 'OK', 'content' => $data(['type' => 'object', 'properties' => [
                'relationships' => ['type' => 'array', 'items' => $ref('PartyRelationship')],
                'beneficial_owners' => ['type' => 'array', 'items' => $ref('BeneficialOwner')],
            ], 'required' => ['relationships', 'beneficial_owners']])],
        ]),
        'post' => $op('Record a relationship on a company', 'addPartyRelationship', 'Parties', 'party:manage', [$param('Id'), $param('IdempotencyKey'), $param('CorrelationId')], [
            '201' => ['description' => 'Created', 'content' => $data($ref('PartyRelationship'))],
        ], ['type' => 'object', 'properties' => [
            'related_party_id' => $uuid, 'role' => ['type' => 'string', 'enum' => ['director', 'shareholder', 'beneficial_owner', 'signatory', 'company_secretary']],
            'ownership_percent' => ['type' => ['string', 'null']], 'notes' => $nstr,
        ], 'required' => ['related_party_id', 'role']], ['x-idempotency-key' => 'required']),
    ],
    '/api/v1/applications' => [
        'get' => $op('List applications in scope', 'listApplications', 'Applications', 'application:view', [
            $param('PageSize'), $param('PageAfter'), $filter('status', 'Comma-separated canonical statuses'), $filter('open', 'true: exclude terminal'),
            $filter('mine', 'true: originated by me'), $filter('product_key', 'Product key'), $filter('party_id', 'Applications with this party'), $filter('q', 'Reference or applicant name'), $param('CorrelationId'),
        ], ['200' => ['description' => 'OK', 'content' => $list('ApplicationSummary')]]),
        'post' => $op('Create a draft application', 'createApplication', 'Applications', 'application:originate', [$param('IdempotencyKey'), $param('CorrelationId')], [
            '201' => ['description' => 'Created', 'content' => $data($ref('Application')), 'headers' => $etag],
        ], $ref('ApplicationCreate'), ['x-idempotency-key' => 'required']),
    ],
    '/api/v1/applications/stats' => ['get' => $op('Application counts by canonical status in scope', 'applicationStats', 'Applications', 'application:view', [$param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => $data(['type' => 'object', 'properties' => [
            'by_status' => ['type' => 'object', 'additionalProperties' => ['type' => 'integer']], 'open' => ['type' => 'integer'], 'total' => ['type' => 'integer'],
        ], 'required' => ['by_status', 'open', 'total']])],
    ])],
    '/api/v1/applications/{id}' => [
        'get' => $op('Get an application', 'getApplication', 'Applications', 'application:view', [$param('Id'), $param('CorrelationId')], [
            '200' => ['description' => 'OK', 'content' => $data($ref('Application')), 'headers' => $etag],
        ]),
        'patch' => $op('Amend captured fields before approval (field history + provenance)', 'amendApplication', 'Applications', 'application:originate', [$param('Id'), $param('IfMatch'), $param('CorrelationId')], [
            '200' => ['description' => 'OK', 'content' => $data($ref('Application')), 'headers' => $etag],
        ], ['type' => 'object', 'properties' => [
            'requested_amount' => ['type' => ['string', 'null']], 'tenor_months' => ['type' => ['integer', 'null']], 'purpose' => $nstr, 'repayment_frequency' => $nstr,
            'data' => ['type' => 'object'], 'source' => ['type' => 'string', 'enum' => ['cba', 'extraction', 'self_service', 'staff', 'partner'], 'default' => 'staff'], 'source_ref' => $nstr,
        ]]),
    ],
    '/api/v1/applications/{id}/applicants' => ['post' => $op('Add a joint applicant, guarantor or co-signer', 'addApplicant', 'Applications', 'application:originate', [$param('Id'), $param('IfMatch'), $param('IdempotencyKey'), $param('CorrelationId')], [
        '201' => ['description' => 'Added', 'content' => $data($ref('Application')), 'headers' => $etag],
    ], ['type' => 'object', 'properties' => ['party_id' => $uuid, 'role' => ['type' => 'string', 'enum' => ['joint', 'guarantor', 'co_signer']]], 'required' => ['party_id', 'role']], ['x-idempotency-key' => 'required'])],
    '/api/v1/applications/{id}/applicants/{partyId}' => ['delete' => $op('Remove a non-primary applicant', 'removeApplicant', 'Applications', 'application:originate', [$param('Id'), $pathParam('partyId', $uuid), $param('IfMatch'), $param('CorrelationId')], [
        '200' => ['description' => 'Removed', 'content' => $data($ref('Application')), 'headers' => $etag],
    ])],
    '/api/v1/applications/{id}/actions/{action}' => ['post' => $op('Lifecycle action (submit, recommend, hold, resume, return, resubmit, withdraw, cancel)', 'actOnApplication', 'Applications', 'application:view', [
        $param('Id'), $pathParam('action', ['type' => 'string', 'enum' => ['submit', 'withdraw', 'cancel', 'hold', 'resume', 'return', 'resubmit', 'recommend']]), $param('IfMatch'), $param('IdempotencyKey'), $param('CorrelationId'),
    ], ['200' => ['description' => 'Transitioned', 'content' => $data($ref('Application')), 'headers' => $etag]], ['type' => 'object', 'properties' => [
        'reason_code' => ['type' => ['string', 'null'], 'pattern' => '^[A-Z0-9_]{2,64}$'], 'reason_text' => $nstr, 'return_to' => $nstr,
    ]], ['x-idempotency-key' => 'required', 'description' => 'The command checks the action-specific permission: application:originate (submit, hold, resume, resubmit, withdraw, cancel) or application:recommend (recommend, return). withdraw, cancel, hold and return need reason_code.'])],
    '/api/v1/applications/{id}/timeline' => ['get' => $op('Event timeline (the event-sourced history)', 'applicationTimeline', 'Applications', 'application:view', [$param('Id'), $param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => ['type' => 'array', 'items' => $ref('ApplicationEvent')]], 'required' => ['data']]]]],
    ])],
    '/api/v1/applications/{id}/as-at' => ['get' => $op('Reconstruct the application state at an instant (replay)', 'applicationAsAt', 'Applications', 'application:view', [
        $param('Id'), ['name' => 't', 'in' => 'query', 'required' => true, 'schema' => $ts], $param('CorrelationId'),
    ], ['200' => ['description' => 'OK', 'content' => $data(['type' => 'object', 'properties' => ['as_at' => $str, 'state' => ['type' => ['object', 'null']]], 'required' => ['as_at', 'state']])]])],
];

$money = $ref('Money');
$schemas = [
    'ProductSummary' => ['type' => 'object', 'properties' => [
        'id' => $uuid, 'key' => $str, 'name' => $str, 'version_id' => $uuid, 'version_no' => ['type' => 'integer'], 'category' => $str, 'segment' => $str, 'currency' => $str,
        'amount' => ['type' => 'object', 'properties' => ['min' => $money, 'max' => $money], 'required' => ['min', 'max']],
        'tenor_months' => ['type' => 'object', 'properties' => ['min' => ['type' => 'integer'], 'max' => ['type' => 'integer']], 'required' => ['min', 'max']],
        'applicant_types' => ['type' => 'array', 'items' => $str],
    ], 'required' => ['id', 'key', 'name', 'version_id', 'version_no', 'category', 'segment', 'currency', 'amount', 'tenor_months', 'applicant_types']],
    'Product' => ['allOf' => [$ref('ProductSummary'), ['type' => 'object', 'properties' => ['definition' => ['type' => 'object', 'description' => 'Full product definition (see the `product` configuration type)']], 'required' => ['definition']]]],
    'PartySummary' => ['type' => 'object', 'properties' => [
        'id' => $uuid, 'type' => ['type' => 'string', 'enum' => ['individual', 'limited_company']], 'display_name' => $str, 'org_unit_id' => $nuuid, 'status' => $str,
        'registration_number' => $nstr, 'created_at' => $ts,
    ], 'required' => ['id', 'type', 'display_name', 'org_unit_id', 'status', 'registration_number', 'created_at']],
    'Party' => ['allOf' => [$ref('PartySummary'), ['type' => 'object', 'properties' => [
        'cba_customer_id' => $nstr, 'first_name' => $nstr, 'middle_name' => $nstr, 'last_name' => $nstr, 'gender' => $nstr, 'date_of_birth_masked' => $nstr,
        'nationality' => $nstr, 'incorporation_date' => $nstr, 'sector' => $nstr, 'phone_masked' => $nstr, 'email_masked' => $nstr, 'tin_masked' => $nstr,
        'address' => ['type' => ['object', 'null']],
        'identities' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'id' => $uuid, 'type' => $str, 'value_masked' => $str, 'verification_status' => ['type' => 'string', 'enum' => ['unverified', 'verified', 'failed']],
            'verified_at' => $nts, 'provider' => $nstr, 'match_score' => $nstr,
        ], 'required' => ['id', 'type', 'value_masked', 'verification_status']]],
        'updated_at' => $ts,
    ], 'required' => ['identities', 'phone_masked', 'email_masked']]]],
    'PartyCreate' => ['type' => 'object', 'properties' => [
        'type' => ['type' => 'string', 'enum' => ['individual', 'limited_company']], 'org_unit_id' => $nuuid,
        'first_name' => $nstr, 'middle_name' => $nstr, 'last_name' => $nstr, 'gender' => $nstr, 'date_of_birth' => ['type' => ['string', 'null'], 'format' => 'date'], 'nationality' => $nstr,
        'company_name' => $nstr, 'registration_number' => $nstr, 'incorporation_date' => ['type' => ['string', 'null'], 'format' => 'date'], 'sector' => $nstr,
        'phone' => $nstr, 'email' => $nstr, 'tin' => $nstr, 'cba_customer_id' => $nstr,
        'address' => ['type' => ['object', 'null'], 'properties' => ['line1' => $str, 'city' => $str, 'state' => $str, 'country' => $str]],
        'identities' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['type' => ['type' => 'string', 'enum' => ['bvn', 'nin', 'passport', 'drivers_licence', 'voters_card']], 'value' => $str], 'required' => ['type', 'value']]],
    ], 'required' => ['type']],
    'PartyMatch' => ['type' => 'object', 'properties' => [
        'party_id' => $uuid, 'display_name' => $str, 'type' => $str, 'matched_fields' => ['type' => 'array', 'items' => $str], 'name_similarity' => $nstr,
    ], 'required' => ['party_id', 'display_name', 'type', 'matched_fields', 'name_similarity']],
    'PartyRelationship' => ['type' => 'object', 'properties' => [
        'id' => $uuid, 'role' => $str, 'ownership_percent' => $nstr, 'notes' => $nstr,
        'party' => ['type' => 'object', 'properties' => ['id' => $uuid, 'type' => $str, 'display_name' => $str], 'required' => ['id', 'type', 'display_name']], 'created_at' => $ts,
    ], 'required' => ['id', 'role', 'ownership_percent', 'party', 'created_at']],
    'BeneficialOwner' => ['type' => 'object', 'properties' => [
        'party_id' => $uuid, 'display_name' => $str, 'effective_percent' => $str, 'source' => ['type' => 'string', 'enum' => ['look_through', 'declared']],
    ], 'required' => ['party_id', 'display_name', 'effective_percent', 'source']],
    'ApplicationCreate' => ['type' => 'object', 'properties' => [
        'legal_entity_id' => $uuid, 'org_unit_id' => $uuid, 'product_key' => $str, 'primary_party_id' => $uuid,
        'channel' => ['type' => 'string', 'enum' => ['staff', 'api'], 'default' => 'staff'],
        'requested_amount' => ['type' => ['string', 'null']], 'tenor_months' => ['type' => ['integer', 'null']], 'purpose' => $nstr, 'repayment_frequency' => $nstr,
        'data' => ['type' => 'object', 'description' => 'Product-specific captured fields (flat, scalar values)'],
    ], 'required' => ['legal_entity_id', 'org_unit_id', 'product_key', 'primary_party_id']],
    'ApplicationSummary' => ['type' => 'object', 'properties' => [
        'id' => $uuid, 'reference' => $str, 'status' => $status,
        'product' => ['type' => 'object', 'properties' => ['id' => $uuid, 'key' => $str, 'name' => $str, 'version_id' => $uuid], 'required' => ['id', 'key', 'name', 'version_id']],
        'segment' => $str, 'channel' => $str,
        'primary_applicant' => ['type' => 'object', 'properties' => ['party_id' => $uuid, 'display_name' => $str], 'required' => ['party_id', 'display_name']],
        'requested_amount' => ['oneOf' => [$money, ['type' => 'null']]], 'currency' => $str, 'tenor_months' => ['type' => ['integer', 'null']],
        'legal_entity_id' => $uuid, 'org_unit_id' => $uuid, 'originator_id' => $uuid, 'submitted_at' => $nts, 'status_changed_at' => $ts, 'created_at' => $ts, 'version' => ['type' => 'integer'],
    ], 'required' => ['id', 'reference', 'status', 'product', 'segment', 'channel', 'primary_applicant', 'requested_amount', 'currency', 'tenor_months', 'legal_entity_id', 'org_unit_id', 'originator_id', 'submitted_at', 'status_changed_at', 'created_at', 'version']],
    'Application' => ['allOf' => [$ref('ApplicationSummary'), ['type' => 'object', 'properties' => [
        'purpose' => $nstr, 'repayment_frequency' => $nstr, 'data' => ['type' => 'object'], 'resume_to' => $nstatus, 'return_to' => $nstatus,
        'closed_at' => $nts, 'close_reason_code' => $nstr, 'expires_at' => $nts,
        'applicants' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'party_id' => $uuid, 'role' => ['type' => 'string', 'enum' => ['primary', 'joint', 'guarantor', 'co_signer']], 'party_type' => $str, 'display_name' => $str,
        ], 'required' => ['party_id', 'role', 'party_type', 'display_name']]],
        'completeness' => ['type' => 'object', 'properties' => [
            'percent' => ['type' => 'integer', 'minimum' => 0, 'maximum' => 100], 'missing' => ['type' => 'array', 'items' => $str],
            'checklist' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['code' => $str, 'name' => $str, 'mandatory' => ['type' => 'boolean']], 'required' => ['code', 'name', 'mandatory']]],
        ], 'required' => ['percent', 'missing', 'checklist']],
    ], 'required' => ['purpose', 'data', 'applicants', 'completeness']]]],
    'ApplicationEvent' => ['type' => 'object', 'properties' => [
        'version' => ['type' => 'integer'], 'type' => $str, 'occurred_at' => $str,
        'actor' => ['type' => 'object', 'properties' => ['type' => $str, 'id' => $str], 'required' => ['type', 'id']], 'correlation_id' => $nstr, 'payload' => ['type' => 'object'],
    ], 'required' => ['version', 'type', 'occurred_at', 'actor', 'payload']],
];

return ['tags' => ['Products', 'Parties', 'Applications'], 'paths' => $paths, 'schemas' => $schemas];
