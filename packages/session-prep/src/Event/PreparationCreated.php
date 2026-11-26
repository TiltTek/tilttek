<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Event;

/**
 * A preparation was created.
 */
final readonly class PreparationCreated implements PreparationEvent
{
    public function __construct(public string $preparation)
    {
    }
}
