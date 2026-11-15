<?php

declare(strict_types=1);

namespace Tilttek\LeaderboardsBundle\Infrastructure\Lead;

use Closure;
use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Board;
use Tilttek\Leaderboards\Boards;
use Tilttek\Leaderboards\Circle;
use Tilttek\Leaderboards\Contribution;
use Tilttek\Leaderboards\Measure;
use Tilttek\Leaderboards\Period\Period;
use Tilttek\Leaderboards\Ranking;
use Tilttek\Leaderboards\Score;
use Tilttek\Leaderboards\Store\AppearanceStore;
use Tilttek\Leaderboards\Store\ScoreStore;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\Summit;
use Tilttek\LeaderboardsBundle\Infrastructure\Event\TopReached;
use Tilttek\LeaderboardsBundle\Infrastructure\Lead\Contract\LeadStore;

/**
 * The store of scores that watches the lead, when the first places are celebrated as scores change: a score kept in
 * the period under way that puts its member first — or first with others, where ties are shared —, among at least two
 * members, when they were not first before, is a lead taken. The lead is claimed in the `LeadStore`, and the first
 * claim of a member on a board in a period dispatches a `TopReached` of `Summit::Lead`, carrying how the member chose
 * to appear. Counting the same activities again, as a rebuild does, claims nothing new; a period over is never watched.
 * A reported score goes through `keep()`: when the store it watches does not keep it, no lead is taken.
 *
 * Each score kept reads the member's score held and the first scores of the board before and after it: three indexed
 * reads more, each of one or two rows. Everything else is the store's own.
 */
final readonly class LeadWatch implements ScoreStore
{
    public function __construct(
        private ScoreStore $scores,
        private Boards $boards,
        private LeadStore $leads,
        private EventDispatcherInterface $dispatcher,
        private ClockInterface $clock,
        private ?AppearanceStore $appearances = null,
    ) {
    }

    #[Override]
    public function save(Score $score): void
    {
        $this->watch($score, function (Score $score): bool {
            $this->scores->save($score);

            return true;
        });
    }

    /**
     * Keeps a reported score through the store it watches; a lead is taken only when the store kept it.
     */
    #[Override]
    public function keep(Score $score, Measure $measure, Ranking $ranking): bool
    {
        return $this->watch($score, fn (Score $score): bool => $this->scores->keep($score, $measure, $ranking));
    }

    /**
     * Writes the score, then claims the lead it takes, if the write kept it.
     *
     * @param Closure(Score): bool $write the write, which says whether it kept the score
     */
    private function watch(Score $score, Closure $write): bool
    {
        $board = $this->board($score);

        if (!$board instanceof Board || !$this->underWay($board, $score->period)) {
            return $write($score);
        }

        $held = $this->scores->score($score->board, $score->period, $score->member);
        $led = $held instanceof Score && $this->first($held, $this->scores->top($score->board, $score->period, 1, $board->ranking), $board->ranking);

        if (!$write($score)) {
            return false;
        }

        $top = $this->scores->top($score->board, $score->period, 2, $board->ranking);

        if (!$led && \count($top) >= 2 && $this->first($score, $top, $board->ranking) && $this->leads->claim(new Lead($score->board, $score->period, $score->member, $score->reachedAt))) {
            $this->dispatcher->dispatch(new TopReached($score->board, $score->period, $score->member, Summit::Lead, $score->reachedAt, $this->appearance($score->member)));
        }

        return true;
    }

    #[Override]
    public function add(Contribution $contribution): bool
    {
        return $this->scores->add($contribution);
    }

    #[Override]
    public function remove(string $activity): array
    {
        return $this->scores->remove($activity);
    }

    #[Override]
    public function contributions(string $board, string $period, ResourceReference $member): array
    {
        return $this->scores->contributions($board, $period, $member);
    }

    #[Override]
    public function forget(?DateTimeImmutable $since): array
    {
        return $this->scores->forget($since);
    }

    #[Override]
    public function expire(string $board, DateTimeImmutable $before): array
    {
        return $this->scores->expire($board, $before);
    }

    #[Override]
    public function delete(string $board, string $period, ResourceReference $member): void
    {
        $this->scores->delete($board, $period, $member);
    }

    #[Override]
    public function score(string $board, string $period, ResourceReference $member): ?Score
    {
        return $this->scores->score($board, $period, $member);
    }

    #[Override]
    public function top(string $board, string $period, int $limit, Ranking $ranking, ?Circle $among = null): array
    {
        return $this->scores->top($board, $period, $limit, $ranking, $among);
    }

    #[Override]
    public function ahead(Score $score, Ranking $ranking, ?Circle $among = null): int
    {
        return $this->scores->ahead($score, $ranking, $among);
    }

    #[Override]
    public function participants(string $board, string $period, ?Circle $among = null): int
    {
        return $this->scores->participants($board, $period, $among);
    }

    #[Override]
    public function of(ResourceReference $member): array
    {
        return $this->scores->of($member);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return $this->scores->eraseMember($member);
    }

    /**
     * The board the score is kept on — its key holds the object's digest on a board ranked by object —, if declared.
     */
    private function board(Score $score): ?Board
    {
        $name = explode(Board::OBJECT_SEPARATOR, $score->board)[0];

        return $this->boards->has($name) ? $this->boards->get($name) : null;
    }

    /**
     * Whether nobody ranks before the score: the first of the board is its member, or does not rank before it — an
     * equal score, where ranks are shared.
     *
     * @param list<Score> $top the first scores of the board, in rank order
     */
    private function first(Score $score, array $top, Ranking $ranking): bool
    {
        $leader = $top[0] ?? null;

        return !$leader instanceof Score || $leader->member->equals($score->member) || !$ranking->ranksBefore($leader, $score);
    }

    private function underWay(Board $board, string $period): bool
    {
        $current = $board->schedule->at($this->clock->now());

        return $current instanceof Period && $current->code === $period;
    }

    private function appearance(ResourceReference $member): Appearance
    {
        return $this->appearances?->of([$member])[$member->toString()] ?? Appearance::hidden();
    }
}
