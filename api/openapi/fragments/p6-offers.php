<?php

declare(strict_types=1);

/* P1 Offer slice: issue (key facts, CBA schedule, PDF), re-issue, delivery, ordered wet signing, acceptance with executed agreement, decline/negotiate (FR-OFR-001..010, FR-CMP-025). */

require_once __DIR__.'/_helpers.php';

$person = ['type' => ['object', 'null'], 'properties' => ['id' => OA_STR, 'name' => OA_NSTR], 'required' => ['id', 'name']];
$p = [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')];
$offer = oa_data(oa_ref('Offer'));

$paths = [
    '/api/v1/applications/{id}/offers' => [
        'get' => oa_op('Offers on an application, newest first', 'listApplicationOffers', 'Offers', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
            '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('Offer')])],
        ]),
        'post' => oa_op('Issue the offer: terms from the standing approval, schedule from the core banking system, key facts, PDF from the bound template', 'issueOffer', 'Offers', 'offer:prepare', $p, [
            '201' => ['description' => 'Issued; the application moves to offer_issued', 'content' => $offer],
        ], null, ['x-idempotency-key' => 'required']),
    ],
    '/api/v1/offers/{id}' => ['get' => oa_op('An offer with key facts, schedule, signatories, deliveries and agreement', 'getOffer', 'Offers', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => $offer],
    ])],
    '/api/v1/offers/{id}/actions/reissue' => ['post' => oa_op('Re-issue an unsigned offer (fresh terms, schedule and validity) within the template limit', 'reissueOffer', 'Offers', 'offer:prepare', $p, [
        '201' => ['description' => 'The new offer; the old one is superseded', 'content' => $offer],
    ], ['type' => 'object', 'properties' => ['reason' => OA_STR], 'required' => ['reason']], ['x-idempotency-key' => 'required'])],
    '/api/v1/offers/{id}/deliveries' => ['post' => oa_op('Email the offer to a party (queued) or record a printed branch copy', 'deliverOffer', 'Offers', 'offer:prepare', $p, [
        '201' => ['description' => 'Delivery recorded', 'content' => $offer],
    ], ['type' => 'object', 'properties' => ['channel' => ['type' => 'string', 'enum' => ['email', 'print']], 'party_id' => OA_NUUID], 'required' => ['channel']], ['x-idempotency-key' => 'required'])],
    '/api/v1/offers/{id}/signatories/{signatoryId}/actions/sign' => ['post' => oa_op('Record a wet signature; signatories sign in the template order', 'signOffer', 'Offers', 'offer:prepare', [oa_param('Id'), oa_path_param('signatoryId', OA_UUID), oa_param('IdempotencyKey'), oa_param('CorrelationId')], [
        '200' => ['description' => 'Recorded', 'content' => $offer],
    ], ['type' => 'object', 'properties' => ['signed_at' => OA_TS, 'witness_name' => OA_NSTR, 'place' => OA_NSTR], 'required' => ['signed_at']], ['x-idempotency-key' => 'required'])],
    '/api/v1/offers/{id}/actions/accept' => ['post' => [
        'summary' => 'Accept a fully signed offer by uploading the executed agreement (stored immutably with its hash)',
        'operationId' => 'acceptOffer', 'tags' => ['Offers'], 'x-permission' => 'offer:prepare', 'x-idempotency-key' => 'required',
        'parameters' => $p,
        'requestBody' => ['required' => true, 'content' => ['multipart/form-data' => ['schema' => ['type' => 'object', 'properties' => ['file' => ['type' => 'string', 'format' => 'binary']], 'required' => ['file']]]]],
        'responses' => ['200' => ['description' => 'Accepted; the application moves to accepted', 'content' => $offer], 'default' => ['$ref' => '#/components/responses/Problem']],
        'security' => [['sessionCookie' => []], ['bearerToken' => []]],
    ]],
    '/api/v1/offers/{id}/actions/decline' => ['post' => oa_op('The customer rejects or wants to negotiate: the application returns to assessment for revised terms', 'declineOffer', 'Offers', 'offer:prepare', $p, [
        '200' => ['description' => 'Recorded', 'content' => $offer],
    ], ['type' => 'object', 'properties' => ['outcome' => ['type' => 'string', 'enum' => ['reject', 'negotiate']], 'reason_code' => OA_STR, 'note' => OA_STR], 'required' => ['outcome', 'reason_code', 'note']], ['x-idempotency-key' => 'required'])],
];

$fee = ['type' => 'object', 'properties' => ['code' => OA_STR, 'name' => OA_STR, 'type' => OA_STR, 'amount' => OA_STR, 'costed' => OA_BOOL], 'required' => ['code', 'name', 'type', 'amount', 'costed']];
$schemas = [
    'OfferKeyFacts' => ['type' => 'object', 'properties' => [
        'method' => OA_STR, 'principal' => OA_STR, 'total_interest' => OA_STR, 'fees' => ['type' => 'array', 'items' => $fee], 'total_fees' => OA_STR,
        'total_cost_of_credit' => OA_STR, 'total_repayable' => OA_STR, 'net_disbursed' => OA_STR, 'apr_percent' => OA_STR,
        'effective_annual_rate_percent' => OA_STR, 'instalment_count' => OA_INT, 'cooling_off_days' => OA_INT,
    ], 'required' => ['method', 'principal', 'total_interest', 'fees', 'total_fees', 'total_cost_of_credit', 'total_repayable', 'net_disbursed', 'apr_percent', 'effective_annual_rate_percent', 'instalment_count', 'cooling_off_days']],
    'OfferInstalment' => ['type' => 'object', 'properties' => [
        'number' => OA_INT, 'due_date' => OA_STR, 'principal' => OA_STR, 'interest' => OA_STR, 'total' => OA_STR, 'closing_balance' => OA_STR,
    ], 'required' => ['number', 'due_date', 'principal', 'interest', 'total', 'closing_balance']],
    'OfferSignatory' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'position' => OA_INT, 'role' => ['type' => 'string', 'enum' => ['primary', 'joint', 'guarantor', 'co_signer', 'bank']],
        'party_id' => OA_NUUID, 'name' => OA_STR, 'signed' => OA_BOOL, 'method' => OA_NSTR, 'signed_at' => OA_NTS, 'witness_name' => OA_NSTR, 'place' => OA_NSTR,
        'recorded_by' => $person, 'is_next' => OA_BOOL,
    ], 'required' => ['id', 'position', 'role', 'party_id', 'name', 'signed', 'method', 'signed_at', 'witness_name', 'place', 'recorded_by', 'is_next']],
    'OfferDelivery' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'channel' => ['type' => 'string', 'enum' => ['email', 'print']], 'party_id' => OA_NUUID, 'recipient' => OA_NSTR,
        'status' => ['type' => 'string', 'enum' => ['queued', 'sent', 'failed', 'recorded']], 'failure' => OA_NSTR, 'requested_by' => $person, 'requested_at' => OA_TS, 'completed_at' => OA_NTS,
    ], 'required' => ['id', 'channel', 'party_id', 'recipient', 'status', 'failure', 'requested_by', 'requested_at', 'completed_at']],
    'Offer' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'application_id' => OA_UUID, 'sequence' => OA_INT,
        'status' => ['type' => 'string', 'enum' => ['issued', 'accepted', 'rejected', 'negotiating', 'expired', 'superseded']], 'is_expired' => OA_BOOL,
        'template' => ['type' => 'object', 'properties' => ['key' => OA_STR, 'version_id' => OA_UUID, 'version_no' => OA_INT], 'required' => ['key', 'version_id', 'version_no']],
        'execution_method' => ['type' => 'string', 'enum' => ['wet_sign', 'e_sign']], 'approval_request_id' => OA_UUID,
        'terms' => ['type' => 'object', 'properties' => [
            'amount' => oa_ref('Money'), 'tenor_months' => OA_INT, 'rate_percent' => OA_STR, 'repayment_frequency' => OA_STR, 'instalment' => oa_ref('Money'),
            'source' => ['type' => 'string', 'enum' => ['approval', 'counter_offer']],
        ], 'required' => ['amount', 'tenor_months', 'rate_percent', 'repayment_frequency', 'instalment', 'source']],
        'key_facts' => oa_ref('OfferKeyFacts'), 'schedule' => ['type' => 'array', 'items' => oa_ref('OfferInstalment')], 'schedule_source' => OA_STR,
        'document' => ['type' => 'object', 'properties' => ['document_id' => OA_UUID, 'version_id' => OA_UUID, 'sha256' => OA_STR], 'required' => ['document_id', 'version_id', 'sha256']],
        'issued_by' => $person, 'issued_at' => OA_TS, 'valid_until' => OA_TS, 'reissue_of' => OA_NUUID, 'reissue_count' => OA_INT,
        'decision' => ['type' => ['object', 'null'], 'properties' => ['reason_code' => OA_NSTR, 'note' => OA_NSTR, 'by' => $person, 'at' => OA_TS], 'required' => ['reason_code', 'note', 'by', 'at']],
        'signatories' => ['type' => 'array', 'items' => oa_ref('OfferSignatory')],
        'deliveries' => ['type' => 'array', 'items' => oa_ref('OfferDelivery')],
        'agreement' => ['type' => ['object', 'null'], 'properties' => [
            'id' => OA_UUID, 'document_id' => OA_UUID, 'version_id' => OA_UUID, 'sha256' => OA_STR, 'offer_document_sha256' => OA_STR, 'execution_method' => OA_STR, 'executed_at' => OA_TS,
        ], 'required' => ['id', 'document_id', 'version_id', 'sha256', 'offer_document_sha256', 'execution_method', 'executed_at']],
    ], 'required' => ['id', 'application_id', 'sequence', 'status', 'is_expired', 'template', 'execution_method', 'approval_request_id', 'terms', 'key_facts', 'schedule', 'schedule_source', 'document', 'issued_by', 'issued_at', 'valid_until', 'reissue_of', 'reissue_count', 'decision', 'signatories', 'deliveries', 'agreement']],
];

return ['tags' => ['Offers'], 'paths' => $paths, 'schemas' => $schemas];
