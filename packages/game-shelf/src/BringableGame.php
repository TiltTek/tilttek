<?php

declare(strict_types=1);

namespace Tilttek\GameShelf;

/**
 * A game of the catalogue a member owns and can bring to a game night, once per member however many copies they hold.
 */
final readonly class BringableGame
{
    public function __construct(public string $game, public string $owner)
    {
    }
}
