<?php

declare(strict_types=1);

/* P1 Documents slice: upload with malware gate, immutable versions, checklist, waivers (FR-DOC-001..009). */

require_once __DIR__.'/_helpers.php';

$checklistStatus = ['type' => 'string', 'enum' => ['not_received', 'received', 'under_review', 'verified', 'rejected', 'waived', 'expired']];

$paths = [
    '/api/v1/applications/{id}/documents' => [
        'get' => oa_op('Documents on an application', 'listApplicationDocuments', 'Documents', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
            '200' => ['description' => 'OK', 'content' => oa_data(['type' => 'array', 'items' => oa_ref('Document')])],
        ]),
        'post' => [
            'summary' => 'Upload a document (multipart). Hashed before storage, scanned before anyone can read it; infected files are quarantined.',
            'operationId' => 'uploadDocument', 'tags' => ['Documents'], 'x-permission' => 'document:upload', 'x-idempotency-key' => 'required',
            'parameters' => [oa_param('Id'), oa_param('IdempotencyKey'), oa_param('CorrelationId')],
            'requestBody' => ['required' => true, 'content' => ['multipart/form-data' => ['schema' => ['type' => 'object', 'properties' => [
                'file' => ['type' => 'string', 'format' => 'binary'],
                'document_type' => OA_STR,
                'checklist_item_id' => OA_NUUID,
                'party_id' => OA_NUUID,
                'title' => OA_NSTR,
            ], 'required' => ['file', 'document_type']]]]],
            'responses' => [
                '201' => ['description' => 'Stored (scan_status clean) or quarantined (scan_status infected)', 'content' => oa_data(oa_ref('Document'))],
                'default' => ['$ref' => '#/components/responses/Problem'],
            ],
            'security' => [['sessionCookie' => []], ['bearerToken' => []]],
        ],
    ],
    '/api/v1/documents/{id}' => ['get' => oa_op('Get a document with all versions', 'getDocument', 'Documents', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(oa_ref('Document'))],
    ])],
    '/api/v1/documents/{id}/versions/{versionId}/content' => ['get' => [
        'summary' => 'Download a clean version (read access is audited). Quarantined versions are never served (423).',
        'operationId' => 'getDocumentContent', 'tags' => ['Documents'], 'x-permission' => 'application:view',
        'parameters' => [oa_param('Id'), oa_path_param('versionId', OA_UUID), oa_param('CorrelationId')],
        'responses' => [
            '200' => ['description' => 'The file', 'content' => ['application/octet-stream' => ['schema' => ['type' => 'string', 'format' => 'binary']]]],
            'default' => ['$ref' => '#/components/responses/Problem'],
        ],
        'security' => [['sessionCookie' => []], ['bearerToken' => []]],
    ]],
    '/api/v1/applications/{id}/checklist' => ['get' => oa_op('Document checklist derived from the pinned product', 'getChecklist', 'Documents', 'application:view', [oa_param('Id'), oa_param('CorrelationId')], [
        '200' => ['description' => 'OK', 'content' => oa_data(oa_ref('Checklist'))],
    ])],
    '/api/v1/checklist-items/{id}/actions/{action}' => ['post' => oa_op('Verify, reject or waive a checklist item. Waive raises a maker-checker request (202).', 'actOnChecklistItem', 'Documents', 'document:verify', [
        oa_param('Id'), oa_path_param('action', ['type' => 'string', 'enum' => ['verify', 'reject', 'waive']]), oa_param('IdempotencyKey'), oa_param('CorrelationId'),
    ], [
        '200' => ['description' => 'Updated (verify, reject)', 'content' => oa_data(oa_ref('ChecklistItem'))],
        '202' => ['description' => 'Waiver requested; awaits the configured authority', 'content' => oa_data(oa_ref('ChangeRequest'))],
    ], ['type' => 'object', 'properties' => [
        'reason' => OA_NSTR, 'note' => OA_NSTR, 'valid_until' => ['type' => ['string', 'null'], 'format' => 'date'],
    ]], ['x-idempotency-key' => 'required', 'description' => 'verify/reject need document:verify; waive needs document:verify to request and the item\'s waiver authority to approve. reject and waive need a reason.'])],
];

$version = ['type' => 'object', 'properties' => [
    'id' => OA_UUID, 'version_no' => OA_INT, 'filename' => OA_STR, 'mime_type' => OA_STR, 'size_bytes' => OA_INT,
    'sha256' => ['type' => 'string', 'pattern' => '^[0-9a-f]{64}$'],
    'scan_status' => ['type' => 'string', 'enum' => ['clean', 'infected']], 'scan_signature' => OA_NSTR, 'scanner' => OA_STR,
    'uploaded_by' => OA_STR, 'uploaded_at' => OA_TS,
    'duplicates' => ['type' => 'array', 'items' => ['type' => 'object', 'properties' => [
        'document_id' => OA_UUID, 'application_id' => OA_UUID, 'application_reference' => OA_STR, 'same_application' => OA_BOOL,
    ], 'required' => ['document_id', 'application_id', 'application_reference', 'same_application']]],
], 'required' => ['id', 'version_no', 'filename', 'mime_type', 'size_bytes', 'sha256', 'scan_status', 'scan_signature', 'uploaded_by', 'uploaded_at', 'duplicates']];

$schemas = [
    'Document' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'application_id' => OA_UUID, 'checklist_item_id' => OA_NUUID, 'party_id' => OA_NUUID, 'document_type' => OA_STR, 'title' => OA_STR,
        'latest_version' => ['oneOf' => [$version, ['type' => 'null']]],
        'versions' => ['type' => 'array', 'items' => $version],
        'created_at' => OA_TS,
    ], 'required' => ['id', 'application_id', 'checklist_item_id', 'party_id', 'document_type', 'title', 'latest_version', 'versions', 'created_at']],
    'ChecklistItem' => ['type' => 'object', 'properties' => [
        'id' => OA_UUID, 'application_id' => OA_UUID, 'code' => OA_STR, 'name' => OA_STR, 'mandatory' => OA_BOOL, 'status' => $checklistStatus,
        'document_id' => OA_NUUID, 'latest_version_id' => OA_NUUID, 'valid_until' => ['type' => ['string', 'null'], 'format' => 'date'],
        'rejection_reason' => OA_NSTR, 'waiver_reason' => OA_NSTR, 'waiver_change_request_id' => OA_NUUID, 'waiver_authority' => OA_STR,
        'verified_by' => OA_NSTR, 'verified_at' => OA_NTS, 'updated_at' => OA_TS,
    ], 'required' => ['id', 'application_id', 'code', 'name', 'mandatory', 'status', 'document_id', 'valid_until', 'waiver_authority', 'updated_at']],
    'Checklist' => ['type' => 'object', 'properties' => [
        'items' => ['type' => 'array', 'items' => oa_ref('ChecklistItem')],
        'summary' => ['type' => 'object', 'properties' => [
            'mandatory_total' => OA_INT, 'mandatory_satisfied' => OA_INT, 'outstanding' => ['type' => 'array', 'items' => OA_STR], 'complete' => OA_BOOL,
        ], 'required' => ['mandatory_total', 'mandatory_satisfied', 'outstanding', 'complete']],
    ], 'required' => ['items', 'summary']],
];

return ['tags' => ['Documents'], 'paths' => $paths, 'schemas' => $schemas];
