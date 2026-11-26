<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Event;

/**
 * The game master changed the parts of a preparation.
 */
final readonly class PreparationChanged implements PreparationEvent
{
    public function __construct(public string $preparation)
    {
    }
}
