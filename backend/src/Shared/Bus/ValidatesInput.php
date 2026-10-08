<?php

declare(strict_types=1);

namespace Fundly\Shared\Bus;

/** Commands with declarative input rules, checked in the validate stage. */
interface ValidatesInput
{
    /** @return array<string, mixed> */
    public function data(): array;

    /** @return array<string, mixed> Laravel validation rules */
    public function rules(): array;
}
