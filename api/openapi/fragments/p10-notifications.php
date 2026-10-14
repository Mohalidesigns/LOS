<?php

declare(strict_types=1);

/* P1 Notifications: in-app inbox and communication log (FR-NTF-001/005). Templates are config artefacts of type notification.template. */

require_once __DIR__.'/_helpers.php';

$item = ['type' => 'object', 'properties' => [
    'id' => OA_UUID, 'event' => OA_STR, 'title' => OA_STR, 'body' => OA_STR, 'link' => OA_NSTR, 'application_id' => OA_NUUID, 'created_at' => OA_TS, 'read_at' => OA_NTS,
], 'required' => ['id', 'event', 'title', 'body', 'link', 'application_id', 'created_at', 'read_at']];

$paths = [
    '/api/v1/me/notifications' => ['get' => oa_op('My in-app notifications, newest first, with the unread count', 'listMyNotifications', 'Notifications', 'application:view', [oa_filter('unread', 'true for unread only'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
            'data' => ['type' => 'array', 'items' => oa_ref('InAppNotification')],
            'meta' => ['type' => 'object', 'properties' => ['unread' => OA_INT], 'required' => ['unread']],
        ], 'required' => ['data', 'meta']]]]],
    ])],
    '/api/v1/me/notifications/actions/read-all' => ['post' => oa_op('Mark all my notifications read', 'readAllNotifications', 'Notifications', 'application:view', [oa_param('IdempotencyKey'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'object', 'properties' => ['marked' => OA_INT], 'required' => ['marked']])],
    ], null, ['x-idempotency-key' => 'required'])],
    '/api/v1/me/notifications/{id}/actions/read' => ['post' => oa_op('Mark one of my notifications read', 'readNotification', 'Notifications', 'application:view', [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(oa_ref('InAppNotification'))],
    ], null, ['x-idempotency-key' => 'required'])],
    '/api/v1/applications/{id}/communications' => ['get' => oa_op('Communication log: everything sent about the application, to whom, when, how and its status', 'listApplicationCommunications', 'Notifications', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('Communication')])],
    ])],
];

$schemas = [
    'InAppNotification' => $item,
    'Communication' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'event' => OA_STR, 'channel' => ['type' => 'string', 'enum' => ['email', 'sms', 'whatsapp', 'in_app']], 'audience' => ['type' => 'string', 'enum' => ['customer', 'staff']],
        'recipient' => ['type' => 'object', 'properties' => ['user_id' => OA_NUUID, 'party_id' => OA_NUUID, 'hint' => OA_NSTR], 'required' => ['user_id', 'party_id', 'hint']],
        'template' => ['type' => 'object', 'properties' => ['source' => ['type' => 'string', 'enum' => ['tenant', 'default']], 'version_id' => OA_NUUID, 'language' => OA_STR], 'required' => ['source', 'version_id', 'language']],
        'subject' => OA_STR, 'body' => OA_STR, 'attachments' => ['type' => 'array', 'items' => OA_STR],
        'status' => ['type' => 'string', 'enum' => ['queued', 'sent', 'failed', 'delivered', 'read']], 'attempts' => OA_INT, 'error' => OA_NSTR,
        'created_at' => OA_TS, 'sent_at' => OA_NTS, 'read_at' => OA_NTS,
    ], 'required' => ['id', 'event', 'channel', 'audience', 'recipient', 'template', 'subject', 'body', 'attachments', 'status', 'attempts', 'error', 'created_at', 'sent_at', 'read_at']],
];

return ['tags' => ['Notifications'], 'paths' => $paths, 'schemas' => $schemas];
