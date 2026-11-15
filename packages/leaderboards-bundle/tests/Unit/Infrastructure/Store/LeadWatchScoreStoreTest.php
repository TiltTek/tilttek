<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Store;

use PHPUnit\Framework\Attributes\CoversClass;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Store\InMemoryScoreStore;
use Tilttek\Leaderboards\Store\ScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\InMemoryLeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\LeadWatch;
use Tilttek\LeaderboardsBundle\Tests\Unit\Fixture\Clock;

/**
 * The store that watches the lead keeps the contract of the store it watches.
 */
#[CoversClass(LeadWatch::class)]
final class LeadWatchScoreStoreTest extends ScoreStoreTestCase
{
    protected function store(): ScoreStore
    {
        return new LeadWatch(new InMemoryScoreStore(), Boards::fromArray(['games' => ['measure' => 'total', 'verbs' => ['game.played']]]), new InMemoryLeadStore(), new class implements EventDispatcherInterface {
            public function dispatch(object $event): object
            {
                return $event;
            }
        }, new Clock());
    }
}
