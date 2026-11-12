<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Store;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Leaderboards\Board;
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\Ranking;
use Tilttek\Leaderboards\Score;

/**
 * The scores in memory, forgotten with the process: for tests, scripts and examples.
 */
final class InMemoryScoreStore implements ScoreStore
{
    /**
     * @var array<string, Contribution> by board and activity
     */
    private array $contributions = [];

    /**
     * @var array<string, Score> by board, period and member
     */
    private array $scores = [];

    #[Override]
    public function add(Contribution $contribution): bool
    {
        $key = json_encode([$contribution->board, $contribution->activity], \JSON_THROW_ON_ERROR);

        if (isset($this->contributions[$key])) {
            return false;
        }

        $this->contributions[$key] = $contribution;

        return true;
    }

    #[Override]
    public function remove(string $activity): array
    {
        $removed = array_filter($this->contributions, static fn (Contribution $contribution): bool => $contribution->activity === $activity);
        $this->contributions = array_diff_key($this->contributions, $removed);

        return array_values($removed);
    }

    #[Override]
    public function contributions(string $board, string $period, ResourceReference $member): array
    {
        $contributions = array_filter(
            $this->contributions,
            static fn (Contribution $contribution): bool => $contribution->board === $board && $contribution->period === $period && $contribution->member->equals($member),
        );
        usort($contributions, static fn (Contribution $a, Contribution $b): int => $a->occurredAt <=> $b->occurredAt);

        return $contributions;
    }

    #[Override]
    public function forget(?DateTimeImmutable $since): array
    {
        $forgotten = array_filter($this->contributions, static fn (Contribution $contribution): bool => !$since instanceof DateTimeImmutable || $contribution->occurredAt >= $since);
        $this->contributions = array_diff_key($this->contributions, $forgotten);

        return array_values($forgotten);
    }

    #[Override]
    public function expire(string $board, DateTimeImmutable $before): array
    {
        $expired = array_filter($this->contributions, static fn (Contribution $contribution): bool => self::boardOf($contribution->board) === $board && $contribution->occurredAt < $before);
        $this->contributions = array_diff_key($this->contributions, $expired);

        return array_values($expired);
    }

    #[Override]
    public function save(Score $score): void
    {
        $this->scores[$this->key($score->board, $score->period, $score->member)] = $score;
    }

    #[Override]
    public function delete(string $board, string $period, ResourceReference $member): void
    {
        unset($this->scores[$this->key($board, $period, $member)]);
    }

    #[Override]
    public function score(string $board, string $period, ResourceReference $member): ?Score
    {
        return $this->scores[$this->key($board, $period, $member)] ?? null;
    }

    #[Override]
    public function top(string $board, string $period, int $limit, Ranking $ranking, ?Circle $among = null): array
    {
        return \array_slice($ranking->sort($this->on($board, $period, $among)), 0, $limit);
    }

    #[Override]
    public function ahead(Score $score, Ranking $ranking, ?Circle $among = null): int
    {
        $byArrival = $ranking->byArrival();
        $key = $byArrival ? $ranking->key($score) : $ranking->valuesKey($score);
        $ahead = 0;

        foreach ($this->on($score->board, $score->period, $among) as $other) {
            // A better score ranks ahead whatever its key; an equal one is told by its key.
            if ($other->value > $score->value || ($other->value === $score->value && ($byArrival ? $ranking->key($other) : $ranking->valuesKey($other)) < $key)) {
                ++$ahead;
            }
        }

        return $ahead;
    }

    #[Override]
    public function participants(string $board, string $period, ?Circle $among = null): int
    {
        return \count($this->on($board, $period, $among));
    }

    #[Override]
    public function of(ResourceReference $member): array
    {
        $scores = array_filter($this->scores, static fn (Score $score): bool => $score->member->equals($member));
        ksort($scores, \SORT_STRING);

        return array_values($scores);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $count = \count($this->contributions) + \count($this->scores);
        $this->contributions = array_filter($this->contributions, static fn (Contribution $contribution): bool => !$contribution->member->equals($member));
        $this->scores = array_filter($this->scores, static fn (Score $score): bool => !$score->member->equals($member));

        return $count - \count($this->contributions) - \count($this->scores);
    }

    /**
     * Empties the store: a long-running process, such as a worker that serves one request after another, calls it
     * between two of them, so that each starts as a new process would.
     */
    public function reset(): void
    {
        $this->contributions = [];
        $this->scores = [];
    }

    /**
     * The board's name in a key its scores are kept under, without the digest of an object.
     */
    private static function boardOf(string $key): string
    {
        return explode(Board::OBJECT_SEPARATOR, $key)[0];
    }

    private function key(string $board, string $period, ResourceReference $member): string
    {
        return json_encode([$board, $period, $member->toString()], \JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, Score>
     */
    private function on(string $board, string $period, ?Circle $among): array
    {
        return array_filter(
            $this->scores,
            static fn (Score $score): bool => $score->board === $board && $score->period === $period && (!$among instanceof Circle || $among->has($score->member)),
        );
    }
}
