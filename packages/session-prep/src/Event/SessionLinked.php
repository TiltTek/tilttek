<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Event;

/**
 * A preparation was linked to a session, or to none when `$session` is null.
 */
final readonly class SessionLinked implements PreparationEvent
{
    public function __construct(public string $preparation, public ?string $session)
    {
    }
}
