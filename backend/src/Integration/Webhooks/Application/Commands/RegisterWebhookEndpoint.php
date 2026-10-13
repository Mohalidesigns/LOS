<?php

declare(strict_types=1);

namespace Fundly\Integration\Webhooks\Application\Commands;

use Fundly\Shared\Bus\Command;
use Fundly\Shared\Bus\HandledBy;
use Fundly\Shared\Bus\ValidatesInput;
use Fundly\Shared\Security\ResourceRef;

/** Register a subscriber endpoint; the signing secret is returned once, here, and stored encrypted. */
#[HandledBy(RegisterWebhookEndpointHandler::class)]
final readonly class RegisterWebhookEndpoint implements Command, ValidatesInput
{
    /** @param list<mixed> $events */
    public function __construct(public ?string $name, public ?string $url, public array $events) {}

    public function action(): string
    {
        return 'webhook.endpoint_registered';
    }

    public function permission(): string
    {
        return 'webhook:manage';
    }

    public function resource(): ?ResourceRef
    {
        return null;
    }

    public function data(): array
    {
        return ['name' => $this->name, 'url' => $this->url, 'events' => $this->events];
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'url' => ['required', 'url:https,http', 'max:500'],
            'events' => ['required', 'array', 'min:1'],
            'events.*' => ['string', 'regex:/^(\*|[a-z_]+\.[a-z_]+)$/'],
        ];
    }
}
