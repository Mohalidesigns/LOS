<?php

declare(strict_types=1);

namespace Fundly\Shared\Audit;

/** Supplies the current actor (implemented by the Access module). */
interface ActorProvider
{
    public function current(): Actor;
}
