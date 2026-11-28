<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Choice;

use Tilttek\GameNight\Exception\InvalidNight;
use Tilttek\GameNight\Internal\Identifier;

/**
 * A game of the catalogue a member can bring to the night.
 */
final readonly class OwnedGame
{
    /**
     * @throws InvalidNight when an identifier is not one
     */
    public function __construct(public string $game, public string $owner)
    {
        Identifier::game($game);
        Identifier::check($owner, 'a member');
    }
}
