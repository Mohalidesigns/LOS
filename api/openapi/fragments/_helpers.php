<?php

declare(strict_types=1);

/* Shared builders for OpenAPI fragments (loaded with require_once). */

function oa_ref(string $name): array
{
    return ['$ref' => '#/components/schemas/'.$name];
}

function oa_param(string $name): array
{
    return ['$ref' => '#/components/parameters/'.$name];
}

function oa_path_param(string $name, array $schema): array
{
    return ['name' => $name, 'in' => 'path', 'required' => true, 'schema' => $schema];
}

function oa_filter(string $name, string $desc): array
{
    return ['name' => "filter[{$name}]", 'in' => 'query', 'required' => false, 'schema' => ['type' => 'string'], 'description' => $desc];
}

/** application/json body `{data: <schema>}` */
function oa_data(array $schema): array
{
    return ['application/json' => ['schema' => ['type' => 'object', 'properties' => ['data' => $schema], 'required' => ['data']]]];
}

/** cursor-paginated list of a schema */
function oa_list(string $item): array
{
    return ['application/json' => ['schema' => ['type' => 'object', 'properties' => [
        'data' => ['type' => 'array', 'items' => oa_ref($item)],
        'meta' => oa_ref('PageMeta'),
    ], 'required' => ['data', 'meta']]]];
}

function oa_etag(): array
{
    return ['ETag' => ['$ref' => '#/components/headers/ETag']];
}

function oa_op(string $summary, string $id, string $tag, string $perm, array $params, array $responses, ?array $body = null, array $extra = []): array
{
    $o = ['summary' => $summary, 'operationId' => $id, 'tags' => [$tag], 'x-permission' => $perm, 'parameters' => $params];
    if ($body !== null) {
        $o['requestBody'] = ['required' => true, 'content' => ['application/json' => ['schema' => $body]]];
    }
    $o['responses'] = $responses + ['default' => ['$ref' => '#/components/responses/Problem']];
    $o['security'] = [['sessionCookie' => []], ['bearerToken' => []]];

    return $o + $extra;
}

const OA_STR = ['type' => 'string'];
const OA_NSTR = ['type' => ['string', 'null']];
const OA_UUID = ['type' => 'string', 'format' => 'uuid'];
const OA_NUUID = ['type' => ['string', 'null'], 'format' => 'uuid'];
const OA_TS = ['type' => 'string', 'format' => 'date-time'];
const OA_NTS = ['type' => ['string', 'null'], 'format' => 'date-time'];
const OA_INT = ['type' => 'integer'];
const OA_BOOL = ['type' => 'boolean'];
