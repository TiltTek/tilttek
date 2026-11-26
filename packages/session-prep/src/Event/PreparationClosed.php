<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Event;

/**
 * A preparation was closed, the session over.
 */
final readonly class PreparationClosed implements PreparationEvent
{
    public function __construct(public string $preparation)
    {
    }
}
