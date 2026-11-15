<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Tests\Unit\Fixture;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\Measure;
use Tilttek\Leaderboards\Ranking;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Store\InMemoryScoreStore;
use Tilttek\Leaderboards\Store\ScoreStore;

/**
 * Scores in memory where another process reports a score between the read and the write of the next report: the
 * racer's score is written first, and the report is then compared with it.
 */
final class RacingScoreStore implements ScoreStore
{
    public function __construct(
        public readonly InMemoryScoreStore $store,
        private ?Score $racer,
    ) {
    }

    #[Override]
    public function add(Contribution $contribution): bool
    {
        return $this->store->add($contribution);
    }

    #[Override]
    public function remove(string $activity): array
    {
        return $this->store->remove($activity);
    }

    #[Override]
    public function contributions(string $board, string $period, ResourceReference $member): array
    {
        return $this->store->contributions($board, $period, $member);
    }

    #[Override]
    public function forget(?DateTimeImmutable $since): array
    {
        return $this->store->forget($since);
    }

    #[Override]
    public function expire(string $board, DateTimeImmutable $before): array
    {
        return $this->store->expire($board, $before);
    }

    #[Override]
    public function score(string $board, string $period, ResourceReference $member): ?Score
    {
        return $this->store->score($board, $period, $member);
    }

    #[Override]
    public function top(string $board, string $period, int $limit, Ranking $ranking, ?Circle $among = null): array
    {
        return $this->store->top($board, $period, $limit, $ranking, $among);
    }

    #[Override]
    public function ahead(Score $score, Ranking $ranking, ?Circle $among = null): int
    {
        return $this->store->ahead($score, $ranking, $among);
    }

    #[Override]
    public function participants(string $board, string $period, ?Circle $among = null): int
    {
        return $this->store->participants($board, $period, $among);
    }

    #[Override]
    public function of(ResourceReference $member): array
    {
        return $this->store->of($member);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return $this->store->eraseMember($member);
    }

    #[Override]
    public function delete(string $board, string $period, ResourceReference $member): void
    {
        $this->store->delete($board, $period, $member);
    }

    #[Override]
    public function save(Score $score): void
    {
        $this->store->save($score);
    }

    #[Override]
    public function keep(Score $score, Measure $measure, Ranking $ranking): bool
    {
        if ($this->racer instanceof Score) {
            $this->store->save($this->racer);
            $this->racer = null;
        }

        return $this->store->keep($score, $measure, $ranking);
    }
}
