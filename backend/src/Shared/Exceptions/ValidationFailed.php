<?php

declare(strict_types=1);

namespace Fundly\Shared\Exceptions;

/** 422 with a field → messages map, as `errors` in the problem document. */
final class ValidationFailed extends ProblemException
{
    /** @param array<string, list<string>> $errors */
    public function __construct(private readonly array $errors, string $detail = 'The request is invalid.')
    {
        parent::__construct($detail, ['errors' => $errors]);
    }

    /** @param array<string, list<string>|string> $errors */
    public static function with(array $errors): self
    {
        $normalised = [];
        foreach ($errors as $field => $messages) {
            $normalised[$field] = is_array($messages) ? array_values($messages) : [$messages];
        }

        return new self($normalised);
    }

    /** @return array<string, list<string>> */
    public function errors(): array
    {
        return $this->errors;
    }

    public function status(): int
    {
        return 422;
    }

    public function type(): string
    {
        return 'validation-failed';
    }

    public function title(): string
    {
        return 'Validation failed';
    }
}
