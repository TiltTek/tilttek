<?php

declare(strict_types=1);

namespace Tilttek\Factions;

use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Internal\Identifier;

/**
 * A character's place within a faction: the rank they hold, by its key.
 */
final readonly class Membership
{
    /**
     * @param string $character the character's identifier
     * @param string $rank      the key of a rank of the faction
     *
     * @throws InvalidRoster when the identifier or the key is out of its shape
     */
    public function __construct(public string $character, public string $rank)
    {
        Identifier::check($character, 'a character');
        Identifier::key($rank, 'a rank');
    }
}
