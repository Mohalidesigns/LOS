<?php

declare(strict_types=1);

namespace Fundly\Shared\Rules;

use Fundly\Shared\Rules\Evaluators\V1\Evaluator as V1;

/**
 * Every released evaluator stays loadable so a decision replays with the
 * engine that made it (TRD §7.2, FR-CRD-014).
 */
final class EvaluatorRegistry
{
    public const CURRENT = V1::VERSION;

    /** @return list<string> */
    public static function versions(): array
    {
        return [V1::VERSION];
    }

    public static function supports(string $version): bool
    {
        return in_array($version, self::versions(), true);
    }

    /** @param array<string, mixed> $facts */
    public static function for(string $version, array $facts): V1
    {
        return match ($version) {
            V1::VERSION => new V1($facts),
            default => throw new RuleError("Evaluator {$version} is not available."),
        };
    }

    public static function check(string $version, string $expression): void
    {
        self::for($version, []);
        V1::check($expression);
    }

    /** @return list<string> */
    public static function references(string $version, string $expression): array
    {
        self::for($version, []);

        return V1::references($expression);
    }
}
