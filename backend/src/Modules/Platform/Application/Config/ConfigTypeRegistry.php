<?php

declare(strict_types=1);

namespace Fundly\Modules\Platform\Application\Config;

use Closure;
use Fundly\Shared\Exceptions\ValidationFailed;
use Illuminate\Contracts\Validation\Factory;

/**
 * Code-defined configuration artefact types and their content validators
 * (FR-CFG-002 groundwork). Unknown types are rejected.
 */
final class ConfigTypeRegistry
{
    /** @var array<string, array{description: string, rules: array<string, mixed>|Closure(array<string, mixed>): void}> */
    private array $types = [];

    public function __construct(private readonly Factory $validator)
    {
        $this->register('security.session_policy', 'Session controls: idle/absolute timeouts and concurrent-session cap (FR-SEC-015).', [
            'idle_minutes' => ['required', 'integer', 'min:5', 'max:120'],
            'absolute_minutes' => ['required', 'integer', 'min:30', 'max:1440'],
            'max_concurrent' => ['required', 'integer', 'min:1', 'max:10'],
        ]);
        $this->register('platform.reference_list', 'A tenant reference list (code → label).', [
            'items' => ['required', 'array', 'min:1'],
            'items.*.code' => ['required', 'string', 'max:64', 'distinct'],
            'items.*.label' => ['required', 'string', 'max:200'],
        ]);
    }

    /** @param array<string, mixed>|Closure(array<string, mixed>): void $rules */
    public function register(string $type, string $description, array|Closure $rules): void
    {
        $this->types[$type] = ['description' => $description, 'rules' => $rules];
    }

    public function has(string $type): bool
    {
        return isset($this->types[$type]);
    }

    /** @param array<string, mixed> $content */
    public function validate(string $type, array $content): void
    {
        $def = $this->types[$type] ?? throw ValidationFailed::with(['type' => "Unknown configuration type {$type}."]);
        if ($def['rules'] instanceof Closure) {
            ($def['rules'])($content);

            return;
        }
        $v = $this->validator->make($content, $def['rules']);
        if ($v->fails()) {
            $errors = [];
            foreach ($v->errors()->toArray() as $field => $messages) {
                $errors['content.'.$field] = array_values(array_map('strval', (array) $messages));
            }
            throw new ValidationFailed($errors);
        }
    }

    /** @return array<string, string> */
    public function catalogue(): array
    {
        return array_map(static fn (array $d): string => $d['description'], $this->types);
    }
}
