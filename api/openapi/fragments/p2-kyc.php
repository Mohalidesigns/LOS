<?php

declare(strict_types=1);

/* P1 KYC slice: consents, identity verification, CDD gate, screening alerts. */

require_once __DIR__.'/_helpers.php';

$consentState = ['type' => 'object', 'properties' => [
    'current' => ['type' => 'object', 'additionalProperties' => ['type' => 'object', 'properties' => [
        'status' => ['type' => 'string', 'enum' => ['granted', 'withdrawn']], 'since' => OA_STR, 'channel' => OA_STR, 'terms_version' => OA_STR,
    ], 'required' => ['status', 'since', 'channel', 'terms_version']]],
    'history' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
        'purpose' => OA_STR, 'action' => ['type' => 'string', 'enum' => ['grant', 'withdraw']], 'channel' => OA_STR, 'terms_version' => OA_STR,
        'evidence_ref' => OA_NSTR, 'application_id' => OA_NUUID, 'recorded_by' => OA_STR, 'recorded_at' => OA_STR,
    ], 'required' => ['purpose', 'action', 'channel', 'terms_version', 'recorded_by', 'recorded_at']]],
], 'required' => ['current', 'history']];

$paths = [
    '/api/v1/parties/{id}/consents' => [
        'get' => oa_op('Current consents and full history', 'listPartyConsents', 'Parties', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
            '200' => ['description' => 'OK', 'content' => oa_data($consentState)],
        ]),
        'post' => oa_op('Grant or withdraw consent for a purpose', 'recordPartyConsent', 'Parties', 'party:manage', [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')], [
            '201' => ['description' => 'Recorded', 'content' => oa_data($consentState)],
        ], ['type' => 'object', 'properties' => [
            'purpose' => ['type' => 'string', 'enum' => ['data_processing', 'credit_bureau', 'credit_reporting', 'marketing', 'third_party_sharing']],
            'action' => ['type' => 'string', 'enum' => ['grant', 'withdraw']],
            'channel' => ['type' => 'string', 'enum' => ['branch', 'digital', 'call_centre', 'agent', 'api']],
            'terms_version' => OA_STR, 'evidence_ref' => OA_NSTR, 'application_id' => OA_NUUID,
        ], 'required' => ['purpose', 'action', 'channel', 'terms_version']], ['x-idempotency-key' => 'required']),
    ],
    '/api/v1/parties/{id}/identities/{type}/actions/verify' => ['post' => oa_op('Verify a recorded identity number through the identity port', 'verifyPartyIdentity', 'Parties', 'party:manage', [
        oa_param('Id'), oa_path_param('type', ['type' => 'string', 'enum' => ['bvn', 'nin', 'passport', 'drivers_licence', 'voters_card']]), oa_param('IdempotencyKey'), oa_param('CorrelationId'),
    ], ['200' => ['description' => 'Verification recorded (verified or failed)', 'content' => oa_data(oa_ref('Party')), 'headers' => oa_etag()]], null, ['x-idempotency-key' => 'required'])],
    '/api/v1/applications/{id}/kyc' => ['get' => oa_op('KYC / CDD gate status with screening evidence', 'applicationKyc', 'Compliance', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(oa_ref('KycStatus'))],
    ])],
    '/api/v1/applications/{id}/kyc/actions/rescreen' => ['post' => oa_op('Re-run screening for every party on the application', 'rescreenApplication', 'Compliance', 'screening:review', [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')], [
        '200' => ['description' => 'Screened', 'content' => oa_data(oa_ref('KycStatus'))],
    ], null, ['x-idempotency-key' => 'required'])],
    '/api/v1/screening-alerts' => ['get' => oa_op('Screening alerts in scope', 'listScreeningAlerts', 'Compliance', 'screening:review', [
        oa_param('PageSize'), oa_param('PageAfter'), oa_filter('status', 'Comma-separated: open, pending_confirmation, cleared, confirmed_match'), oa_filter('application_id', 'Application'), oa_param('CorrelationId'),
    ], ['200' => ['description' => 'OK', 'content' => oa_list('ScreeningAlert')]])],
    '/api/v1/screening-alerts/{id}' => ['get' => oa_op('Get a screening alert', 'getScreeningAlert', 'Compliance', 'screening:review', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(oa_ref('ScreeningAlert'))],
    ])],
    '/api/v1/screening-alerts/{id}/actions/{step}' => ['post' => oa_op('Four-eyes disposition: propose (screening:review) then confirm by a different officer (screening:clear)', 'dispositionScreeningAlert', 'Compliance', 'screening:review', [
        oa_param('Id'), oa_path_param('step', ['type' => 'string', 'enum' => ['propose', 'confirm']]), oa_param('IdempotencyKey'), oa_param('CorrelationId'),
    ], ['200' => ['description' => 'Recorded', 'content' => oa_data(oa_ref('ScreeningAlert'))]], ['type' => 'object', 'properties' => [
        'decision' => ['type' => ['string', 'null'], 'enum' => ['clear', 'true_match', null]], 'reason' => OA_STR, 'evidence_ref' => OA_NSTR,
    ], 'required' => ['reason']], ['x-idempotency-key' => 'required'])],
];

$schemas = [
    'ScreeningAlert' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'application_id' => OA_NUUID, 'party_id' => OA_UUID, 'party_name' => OA_NSTR,
        'category' => ['type' => 'string', 'enum' => ['sanction', 'pep', 'adverse_media', 'watchlist']], 'list_name' => OA_STR, 'list_version' => OA_STR,
        'entry_id' => OA_STR, 'matched_name' => OA_STR, 'score' => OA_STR,
        'status' => ['type' => 'string', 'enum' => ['open', 'pending_confirmation', 'cleared', 'confirmed_match']],
        'proposed_decision' => OA_NSTR, 'proposed_reason' => OA_NSTR, 'evidence_ref' => OA_NSTR, 'proposed_by' => OA_NUUID, 'proposed_at' => OA_NTS,
        'confirmed_by' => OA_NUUID, 'confirmed_at' => OA_NTS, 'confirmation_note' => OA_NSTR, 'created_at' => OA_TS,
    ], 'required' => ['id', 'party_id', 'category', 'list_name', 'list_version', 'entry_id', 'matched_name', 'score', 'status', 'created_at']],
    'KycStatus' => ['type' => 'object', 'properties' => [
        'application_id' => OA_UUID, 'status' => OA_STR, 'outcome' => ['type' => 'string', 'enum' => ['clear', 'pending', 'blocked']],
        'risk' => ['type' => 'string', 'enum' => ['standard', 'enhanced']], 'gate_version' => OA_STR,
        'conditions' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => ['code' => OA_STR, 'met' => OA_BOOL, 'detail' => OA_STR], 'required' => ['code', 'met', 'detail']]],
        'screening_runs' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
            'id' => OA_UUID, 'party_id' => OA_UUID, 'subject_name' => OA_STR, 'trigger' => OA_STR, 'list_version' => OA_STR, 'provider_reference' => OA_STR, 'hit_count' => OA_INT, 'screened_at' => OA_STR,
        ], 'required' => ['id', 'party_id', 'subject_name', 'trigger', 'list_version', 'provider_reference', 'hit_count', 'screened_at']]],
        'alerts' => ['type' => 'array', 'items' => oa_ref('ScreeningAlert')],
    ], 'required' => ['application_id', 'status', 'outcome', 'risk', 'gate_version', 'conditions', 'screening_runs', 'alerts']],
];

return ['tags' => ['Compliance'], 'paths' => $paths, 'schemas' => $schemas];
