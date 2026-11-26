<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Event;

/**
 * A preparation was deleted.
 */
final readonly class PreparationDeleted implements PreparationEvent
{
    public function __construct(public string $preparation)
    {
    }
}
