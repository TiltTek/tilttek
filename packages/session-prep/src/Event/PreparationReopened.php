<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Event;

/**
 * A ready or closed preparation is being written again.
 */
final readonly class PreparationReopened implements PreparationEvent
{
    public function __construct(public string $preparation)
    {
    }
}
