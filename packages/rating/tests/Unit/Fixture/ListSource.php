<?php

declare(strict_types=1);

namespace Tilttek\Rating\Tests\Unit\Fixture;

use Override;
use Tilttek\Rating\Contract\PlaySource;
use Tilttek\Rating\GameResult;

/**
 * A source of results of tests: the results it was given, for any game asked.
 */
final class ListSource implements PlaySource
{
    /**
     * @param list<GameResult> $results
     */
    public function __construct(public array $results = [])
    {
    }

    #[Override]
    public function results(string $game): iterable
    {
        yield from $this->results;
    }
}
