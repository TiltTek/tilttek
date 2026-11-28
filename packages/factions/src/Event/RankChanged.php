<?php

declare(strict_types=1);

namespace Tilttek\Factions\Event;

/**
 * The rank a character holds within a faction changed.
 */
final readonly class RankChanged implements RosterEvent
{
    public function __construct(public string $campaign, public string $faction, public string $character)
    {
    }
}
