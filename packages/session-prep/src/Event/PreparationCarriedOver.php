<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Event;

/**
 * The secrets not revealed and the scenes not played of another preparation were carried into this one.
 */
final readonly class PreparationCarriedOver implements PreparationEvent
{
    public function __construct(public string $preparation, public string $from)
    {
    }
}
