<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Event;

/**
 * A preparation is ready for the table.
 */
final readonly class PreparationReady implements PreparationEvent
{
    public function __construct(public string $preparation)
    {
    }
}
