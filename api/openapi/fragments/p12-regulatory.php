<?php

declare(strict_types=1);

/* P1 Jurisdiction packs: rule register in force per booking entity, draft packs, regulatory limit checks (FR-CMP-001/003/023). Packs and prudential parameters are config artefacts (jurisdiction.pack, prudential.parameters). */

require_once __DIR__.'/_helpers.php';

$person = ['type' => ['object', 'null'], 'properties' => ['id' => OA_STR, 'name' => OA_NSTR], 'required' => ['id', 'name']];
$nmoney = ['oneOf' => [oa_ref('Money'), ['type' => 'null']]];
$obj = ['type' => 'object', 'additionalProperties' => true];
$nobj = ['type' => ['object', 'null'], 'additionalProperties' => true];

$paths = [
    '/api/v1/legal-entities/{id}/regulatory-rules' => ['get' => oa_op('The regulatory rule register in force for a booking legal entity: active pack, rules with citations and verification, prudential base', 'getRegulatoryRules', 'Compliance', 'config:read', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(oa_ref('RegulatoryRegister'))],
    ])],
    '/api/v1/jurisdiction-packs/defaults' => ['get' => oa_op('Draft jurisdiction packs shipped with the product (all rules unverified) to author a pack version from', 'listDefaultJurisdictionPacks', 'Compliance', 'config:read', [oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['key' => OA_STR, 'content' => $obj], 'required' => ['key', 'content']]])],
    ])],
    '/api/v1/applications/{id}/regulatory-limit-checks' => ['get' => oa_op('Recorded regulatory limit checks for an application, newest first', 'listRegulatoryLimitChecks', 'Compliance', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('RegulatoryLimitCheck')])],
    ])],
];

$schemas = [
    'RegulatoryRegister' => ['type' => 'object', 'properties' => [
        'legal_entity' => ['type' => 'object', 'properties' => ['id' => OA_UUID, 'code' => OA_STR, 'name' => OA_STR, 'jurisdiction' => OA_STR, 'licence_category' => OA_STR], 'required' => ['id', 'code', 'name', 'jurisdiction', 'licence_category']],
        'pack' => ['type' => ['object', 'null'], 'properties' => [
            'key' => OA_STR, 'name' => OA_STR, 'version_id' => OA_UUID, 'version_no' => OA_INT, 'activated_at' => OA_NTS, 'unverified_rules' => OA_INT,
        ], 'required' => ['key', 'name', 'version_id', 'version_no', 'activated_at', 'unverified_rules']],
        'prudential' => ['type' => ['object', 'null'], 'properties' => [
            'effective_from' => ['type' => 'string', 'format' => 'date'], 'shareholders_funds_unimpaired' => oa_ref('Money'), 'source' => OA_STR, 'version_id' => OA_NUUID,
        ], 'required' => ['effective_from', 'shareholders_funds_unimpaired', 'source', 'version_id']],
        'rules' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'code' => OA_STR, 'title' => OA_STR, 'kind' => ['type' => 'string', 'enum' => ['parameter', 'obligation']], 'parameters' => $nobj, 'citation' => $obj,
            'effective_from' => ['type' => 'string', 'format' => 'date'], 'effective_to' => ['type' => ['string', 'null'], 'format' => 'date'],
            'verification_status' => ['type' => 'string', 'enum' => ['unverified', 'verified']], 'verified_by' => OA_NSTR, 'verified_on' => OA_NSTR, 'note' => OA_NSTR, 'in_force' => OA_BOOL,
        ], 'required' => ['code', 'title', 'kind', 'parameters', 'citation', 'effective_from', 'effective_to', 'verification_status', 'verified_by', 'verified_on', 'note', 'in_force']]],
    ], 'required' => ['legal_entity', 'pack', 'prudential', 'rules']],
    'RegulatoryLimitCheck' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'limit_type' => OA_STR, 'stage' => ['type' => 'string', 'enum' => ['approval', 'pre_disbursement']],
        'outcome' => ['type' => 'string', 'enum' => ['pass', 'breach', 'unavailable', 'not_applicable']], 'detail' => OA_STR,
        'rule' => $nobj, 'base' => $nobj,
        'figures' => ['type' => ['object', 'null'], 'properties' => [
            'base' => $nmoney, 'limit_percent' => OA_NSTR, 'limit' => $nmoney, 'existing' => $nmoney, 'proposed' => $nmoney, 'total' => $nmoney,
        ], 'required' => ['base', 'limit_percent', 'limit', 'existing', 'proposed', 'total']],
        'facilities' => ['type' => 'array', 'items' => $obj],
        'checked_by' => $person, 'checked_at' => OA_TS,
    ], 'required' => ['id', 'limit_type', 'stage', 'outcome', 'detail', 'rule', 'base', 'figures', 'facilities', 'checked_by', 'checked_at']],
];

return ['tags' => ['Compliance'], 'paths' => $paths, 'schemas' => $schemas];
