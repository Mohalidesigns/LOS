<?php

declare(strict_types=1);

namespace Fundly\Shared\Rules\Evaluators\V1;

final readonly class Token
{
    public const NUMBER = 'number';

    public const STRING = 'string';

    public const NAME = 'name';

    public const OP = 'op';

    public const PUNCT = 'punct';

    public const END = 'end';

    public function __construct(public string $type, public string $value, public int $pos) {}

    public function is(string $type, ?string $value = null): bool
    {
        return $this->type === $type && ($value === null || $this->value === $value);
    }
}
