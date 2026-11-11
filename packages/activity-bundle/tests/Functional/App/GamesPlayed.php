<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Functional\App;

use DateTimeImmutable;
use Override;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Projection\Projection;

/**
 * The games each member played, cancelled games taken back: the projection of the test application.
 */
final class GamesPlayed implements Projection
{
    /**
     * @var array<string, int>
     */
    public array $games = [];

    #[Override]
    public function name(): string
    {
        return 'games_played';
    }

    #[Override]
    public function filter(): Filter
    {
        return Filter::all()->verbs('game.played');
    }

    #[Override]
    public function apply(Entry $entry): void
    {
        $member = $entry->activity->member->toString();
        $this->games[$member] = ($this->games[$member] ?? 0) + ($entry->isCancellation() ? -1 : 1);
    }

    #[Override]
    public function reset(?DateTimeImmutable $since): void
    {
        $this->games = [];
    }
}
