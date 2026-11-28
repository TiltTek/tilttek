<?php

declare(strict_types=1);

namespace Tilttek\Factions;

/**
 * Where a subject stands with a faction, on the scale of the roster: the roster checks that the value lies within it.
 */
final readonly class Standing
{
    public function __construct(public Subject $subject, public int $value)
    {
    }
}
