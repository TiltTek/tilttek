<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Benchmark;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Store\ScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalLeadStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Doctrine\DbalScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\LeadWatch;
use Tilttek\LeaderboardsBundle\Tests\Unit\Fixture\Clock;
use Tilttek\LeaderboardsBundle\Tests\Unit\Infrastructure\Database;

/**
 * What watching the lead costs a score kept, on SQLite in memory among 10 000 members: the score kept by the store
 * alone, then by the store that watches the lead — the member's score and the first scores read before and after it.
 */
final class LeadWatchBench
{
    private const int MEMBERS = 10_000;

    private readonly ScoreStore $plain;

    private readonly ScoreStore $watched;

    private int $score = 0;

    public function __construct()
    {
        $this->plain = $this->filled(new DbalScoreStore(Database::connection()));
        $connection = Database::connection();
        $this->watched = new LeadWatch(
            $this->filled(new DbalScoreStore($connection)),
            Boards::fromArray(['games' => ['measure' => 'total', 'verbs' => ['game.played'], 'period' => 'month']]),
            new DbalLeadStore($connection),
            new class implements EventDispatcherInterface {
                public function dispatch(object $event): object
                {
                    return $event;
                }
            },
            new Clock('2026-11-07 12:00 UTC'),
        );
    }

    public function benchSave(): void
    {
        $this->plain->save($this->next());
    }

    public function benchSaveWatched(): void
    {
        $this->watched->save($this->next());
    }

    private function next(): Score
    {
        ++$this->score;

        return new Score('games', '2026-11', ResourceReference::fromString('member:'.($this->score % self::MEMBERS)), $this->score % 20_000, new DateTimeImmutable('2026-11-07 10:00 UTC'));
    }

    private function filled(ScoreStore $store): ScoreStore
    {
        for ($member = 0; $member < self::MEMBERS; ++$member) {
            $store->save(new Score('games', '2026-11', ResourceReference::fromString('member:'.$member), $member, new DateTimeImmutable('2026-11-07 09:00 UTC')));
        }

        return $store;
    }
}
