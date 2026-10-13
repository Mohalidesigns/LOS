<?php

declare(strict_types=1);

/* P1 Handover + outbound webhooks (FR-HND-001/003/004, FR-NTF-001 webhook channel, TRD §10.1). */

require_once __DIR__.'/_helpers.php';

$p = [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')];

$paths = [
    '/api/v1/applications/{id}/handover' => ['get' => oa_op('Handover package, retention and webhook deliveries for an application', 'getApplicationHandover', 'Handover', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'object', 'properties' => [
            'application_id' => OA_UUID,
            'package' => ['oneOf' => [oa_ref('HandoverPackage'), ['type' => 'null']]],
            'retention' => ['type' => ['object', 'null'], 'properties' => [
                'category' => OA_STR, 'trigger' => OA_STR, 'starts_on' => ['type' => 'string', 'format' => 'date'], 'retain_until' => ['type' => 'string', 'format' => 'date'], 'legal_hold' => OA_BOOL, 'basis' => OA_STR,
            ], 'required' => ['category', 'trigger', 'starts_on', 'retain_until', 'legal_hold', 'basis']],
            'deliveries' => ['type' => 'array', 'items' => oa_ref('WebhookDelivery')],
        ], 'required' => ['application_id', 'package', 'retention', 'deliveries']])],
    ])],
    '/api/v1/handover-packages/{id}' => ['get' => oa_op('A handover package (exact content that was hashed); servicing systems fetch it after facility.created', 'getHandoverPackage', 'Handover', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(oa_ref('HandoverPackage'))],
    ])],
    '/api/v1/handover-packages/{id}/actions/acknowledge' => ['post' => oa_op('Servicing acknowledges the handover', 'acknowledgeHandover', 'Handover', 'handover:acknowledge', $p, [
        '200' => ['description' => 'Acknowledged', 'content' => oa_data(oa_ref('HandoverPackage'))],
    ], ['type' => 'object', 'properties' => ['note' => OA_STR], 'required' => ['note']], ['x-idempotency-key' => 'required'])],
    '/api/v1/webhook-endpoints' => [
        'get' => oa_op('Webhook subscriber endpoints', 'listWebhookEndpoints', 'Webhooks', 'integration:read', [oa_param('CorrelationId')], [
            '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('WebhookEndpoint')])],
        ]),
        'post' => oa_op('Register an endpoint. The signing secret is in this response only; deliveries carry Fundly-Signature: t=<unix>,v1=<HMAC-SHA256(secret, "<t>.<body>")>.', 'registerWebhookEndpoint', 'Webhooks', 'webhook:manage', [oa_param('IdempotencyKey'), oa_param('CorrelationId')], [
            '201' => ['description' => 'Registered', 'content' => oa_data(['allOf' => [oa_ref('WebhookEndpoint'), ['type' => 'object', 'properties' => ['secret' => OA_STR], 'required' => ['secret']]]])],
        ], ['type' => 'object', 'properties' => ['name' => OA_STR, 'url' => OA_STR, 'events' => ['type' => 'array', 'items' => OA_STR]], 'required' => ['name', 'url', 'events']], ['x-idempotency-key' => 'required']),
    ],
    '/api/v1/webhook-endpoints/{id}/actions/disable' => ['post' => oa_op('Disable an endpoint', 'disableWebhookEndpoint', 'Webhooks', 'webhook:manage', $p, [
        '200' => ['description' => 'Disabled', 'content' => oa_data(oa_ref('WebhookEndpoint'))],
    ], null, ['x-idempotency-key' => 'required'])],
    '/api/v1/webhook-deliveries' => ['get' => oa_op('Webhook delivery log, newest first', 'listWebhookDeliveries', 'Webhooks', 'integration:read', [
        oa_filter('subject_type', 'e.g. application'), oa_filter('subject_id', 'subject id'), oa_param('CorrelationId'),
    ], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('WebhookDelivery')])],
    ])],
    '/api/v1/webhook-deliveries/{id}/actions/redeliver' => ['post' => oa_op('Send a delivery again (same event id; receivers de-duplicate on Fundly-Event-Id)', 'redeliverWebhook', 'Webhooks', 'webhook:manage', $p, [
        '200' => ['description' => 'Queued', 'content' => oa_data(oa_ref('WebhookDelivery'))],
    ], null, ['x-idempotency-key' => 'required'])],
];

$schemas = [
    'HandoverPackage' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'application_id' => OA_UUID, 'disbursement_id' => OA_UUID, 'sha256' => OA_STR, 'servicing_org_unit_id' => OA_UUID,
        'webhook_event_id' => OA_NUUID, 'created_at' => OA_TS, 'content' => ['type' => 'object'],
        'acknowledgement' => ['type' => ['object', 'null'], 'properties' => [
            'by' => ['type' => 'object', 'properties' => ['id' => OA_STR, 'name' => OA_NSTR], 'required' => ['id', 'name']], 'at' => OA_TS, 'note' => OA_STR,
        ], 'required' => ['by', 'at', 'note']],
    ], 'required' => ['id', 'application_id', 'disbursement_id', 'sha256', 'servicing_org_unit_id', 'webhook_event_id', 'created_at', 'content', 'acknowledgement']],
    'WebhookEndpoint' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'name' => OA_STR, 'url' => OA_STR, 'events' => ['type' => 'array', 'items' => OA_STR], 'status' => ['type' => 'string', 'enum' => ['active', 'disabled']], 'created_by' => OA_STR, 'created_at' => OA_TS,
    ], 'required' => ['id', 'name', 'url', 'events', 'status', 'created_by', 'created_at']],
    'WebhookDelivery' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'event_id' => OA_UUID, 'event_type' => OA_NSTR,
        'subject' => ['type' => ['object', 'null'], 'properties' => ['type' => OA_STR, 'id' => OA_STR], 'required' => ['type', 'id']],
        'endpoint' => ['type' => 'object', 'properties' => ['id' => OA_UUID, 'name' => OA_NSTR], 'required' => ['id', 'name']],
        'status' => ['type' => 'string', 'enum' => ['pending', 'delivered', 'failing', 'skipped']], 'attempts' => OA_INT,
        'last_http_status' => ['type' => ['integer', 'null']], 'last_error' => OA_NSTR, 'last_attempt_at' => OA_NTS, 'delivered_at' => OA_NTS, 'created_at' => OA_TS,
    ], 'required' => ['id', 'event_id', 'event_type', 'subject', 'endpoint', 'status', 'attempts', 'last_http_status', 'last_error', 'last_attempt_at', 'delivered_at', 'created_at']],
];

return ['tags' => ['Handover', 'Webhooks'], 'paths' => $paths, 'schemas' => $schemas];
