<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Tests\Unit\Fixture;

use Override;
use Tilttek\GameNight\Choice\OwnedGame;
use Tilttek\GameNight\Contract\ShelfReader;

/**
 * Shelves that give the games they were given, whoever is asked for, and write down what they were asked.
 */
final class ListedShelves implements ShelfReader
{
    /** @var list<array{list<string>, int}> */
    public array $asked = [];

    /**
     * @param list<OwnedGame> $games
     */
    public function __construct(private readonly array $games = [])
    {
    }

    #[Override]
    public function games(array $members, int $limit): iterable
    {
        $this->asked[] = [$members, $limit];

        yield from $this->games;
    }
}
