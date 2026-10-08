<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Testing\TestResponse;
use Opis\JsonSchema\Errors\ErrorFormatter;
use Opis\JsonSchema\Validator;
use PHPUnit\Framework\Assert;
use Symfony\Component\Yaml\Yaml;

/**
 * Contract test: every API response produced by a feature test must be
 * documented in api/openapi/openapi.yaml (operation + status + media type)
 * and its body must validate against the documented schema.
 *
 * league/openapi-psr7-validator (named in TRD §10.1) depends on
 * cebe/php-openapi, which only understands OpenAPI 3.0; our contract is
 * OpenAPI 3.1, whose schemas *are* JSON Schema 2020-12. We therefore
 * validate with opis/json-schema (2020-12) against the spec itself, resolving
 * $refs inside the document.
 */
final class OpenApiValidator
{
    private const BASE = 'https://contract.fundly.local/openapi.json';

    private static ?self $instance = null;

    /** @var array<string, mixed> */
    private array $spec;

    private Validator $validator;

    /** @var list<array{method: string, path: string, status: string}> */
    public static array $seen = [];

    private function __construct(string $file)
    {
        /** @var array<string, mixed> $spec */
        $spec = Yaml::parseFile($file, Yaml::PARSE_CONSTANT);
        $this->spec = $spec;
        $this->validator = new Validator;
        $this->validator->setMaxErrors(5);
        $this->validator->parser()->setDefaultDraftVersion('2020-12');
        $doc = json_decode((string) json_encode($spec, JSON_THROW_ON_ERROR | JSON_PRESERVE_ZERO_FRACTION));
        $this->validator->resolver()?->registerRaw($doc, self::BASE);
    }

    public static function instance(): self
    {
        return self::$instance ??= new self(self::specPath());
    }

    public static function specPath(): string
    {
        return dirname(__DIR__, 3).'/api/openapi/openapi.yaml';
    }

    /** @return array<string, mixed> */
    public function spec(): array
    {
        return $this->spec;
    }

    public function assertResponseMatches(string $method, string $path, TestResponse $response): void
    {
        if (! str_starts_with($path, '/api/v1') && ! in_array($path, ['/health', '/ready'], true)) {
            return;
        }
        $method = strtolower($method);
        $template = $this->matchPath($path, $method);
        Assert::assertNotNull($template, "OpenAPI: no documented operation for {$method} {$path}");

        $status = (string) $response->getStatusCode();
        $responses = $this->spec['paths'][$template][$method]['responses'] ?? [];
        $key = isset($responses[$status]) ? $status : (isset($responses['default']) ? 'default' : null);
        Assert::assertNotNull($key, "OpenAPI: {$method} {$template} does not document status {$status}. Body: ".substr((string) $response->getContent(), 0, 500));
        self::$seen[] = ['method' => $method, 'path' => $template, 'status' => $status];

        $content = $this->resolveResponse($responses[$key])['content'] ?? null;
        if ($status === '204' || $content === null) {
            Assert::assertSame('', (string) $response->getContent(), "OpenAPI: {$method} {$template} {$status} documents no body.");

            return;
        }
        $type = strtolower(trim(explode(';', (string) $response->headers->get('Content-Type'))[0]));
        Assert::assertArrayHasKey($type, $content, "OpenAPI: {$method} {$template} {$status} does not document media type {$type}.");

        $pointer = '#/paths/'.self::escape($template).'/'.$method.'/responses/'.$key;
        if (isset($responses[$key]['$ref'])) {
            $pointer = (string) $responses[$key]['$ref'];
        }
        $schemaRef = (object) ['$ref' => self::BASE.$pointer.'/content/'.self::escape($type).'/schema'];
        $data = json_decode((string) $response->getContent());
        $result = $this->validator->validate($data, $schemaRef);
        if (! $result->isValid()) {
            $errors = (new ErrorFormatter)->format($result->error() ?? throw new \LogicException, false);
            Assert::fail("OpenAPI: {$method} {$path} {$status} body does not match schema:\n".json_encode($errors, JSON_PRETTY_PRINT)."\nBody: ".substr((string) $response->getContent(), 0, 1500));
        }
        Assert::assertTrue(true);
    }

    /** @return list<array{method: string, path: string}> */
    public function operations(): array
    {
        $out = [];
        foreach ($this->spec['paths'] as $path => $item) {
            foreach (['get', 'post', 'put', 'patch', 'delete'] as $m) {
                if (isset($item[$m])) {
                    $out[] = ['method' => $m, 'path' => (string) $path];
                }
            }
        }

        return $out;
    }

    private function matchPath(string $path, string $method): ?string
    {
        foreach ($this->spec['paths'] as $template => $item) {
            if (! isset($item[$method])) {
                continue;
            }
            $regex = '#^'.preg_replace('#\\\{[^/]+\\\}#', '[^/]+', preg_quote((string) $template, '#')).'$#';
            if (preg_match($regex, $path) === 1) {
                return (string) $template;
            }
        }

        return null;
    }

    /**
     * @param  array<string, mixed>  $response
     * @return array<string, mixed>
     */
    private function resolveResponse(array $response): array
    {
        if (isset($response['$ref']) && is_string($response['$ref'])) {
            $node = $this->spec;
            foreach (explode('/', ltrim(substr($response['$ref'], 1), '/')) as $part) {
                $node = $node[str_replace(['~1', '~0'], ['/', '~'], $part)];
            }

            return $node;
        }

        return $response;
    }

    /** JSON Pointer escaping, then URI-encoding so the pointer is a valid URI fragment. */
    private static function escape(string $segment): string
    {
        return rawurlencode(str_replace(['~', '/'], ['~0', '~1'], $segment));
    }
}
