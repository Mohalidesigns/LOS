<?php

declare(strict_types=1);

namespace Fundly\Modules\Audit\Application;

use Fundly\Modules\Audit\Contracts\EvidenceSources;
use Fundly\Shared\Security\Principal;
use Illuminate\Contracts\Container\Container;

final class EvidenceRegistry implements EvidenceSources
{
    /** @var array<string, array{0: class-string, 1: string}> */
    private array $sources = [];

    public function __construct(private readonly Container $container) {}

    public function register(string $section, string $service, string $method = 'forApplication'): void
    {
        if (preg_match('/^[a-z][a-z0-9_]{0,40}$/', $section) !== 1) {
            throw new \InvalidArgumentException("Invalid evidence section {$section}.");
        }
        $this->sources[$section] = [$service, $method];
    }

    /** @return array<string, mixed> section => data, in section order */
    public function collect(string $applicationId, Principal $principal): array
    {
        $out = [];
        $sections = $this->sources;
        ksort($sections);
        foreach ($sections as $section => [$service, $method]) {
            $result = $this->container->make($service)->{$method}($applicationId, $principal);
            $out[$section] = is_array($result) && array_key_exists('data', $result) ? $result['data'] : $result;
        }

        return $out;
    }
}
