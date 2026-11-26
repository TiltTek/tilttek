<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Event;

/**
 * A clue of a secret was found, or lost again when `$found` is false.
 */
final readonly class ClueMarked implements PreparationEvent
{
    public function __construct(public string $preparation, public string $secret, public string $clue, public bool $found)
    {
    }
}
