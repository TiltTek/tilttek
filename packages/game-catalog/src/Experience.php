<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use DateTimeImmutable;
use Tilttek\GameCatalog\Exception\InvalidEntry;

/**
 * What the author of a review had played of the game when writing it: the games played and the games run, frozen at
 * that moment, so that the review keeps the experience it was written from.
 */
final readonly class Experience
{
    /**
     * @throws InvalidEntry when a number is negative
     */
    public function __construct(public int $played, public int $ran, public DateTimeImmutable $at)
    {
        if ($played < 0 || $ran < 0) {
            throw InvalidEntry::because('The games played and run are 0 or more.');
        }
    }
}
