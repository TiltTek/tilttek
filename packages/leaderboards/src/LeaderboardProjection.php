<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use DateTimeImmutable;
use Override;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Filter;
use Tilttek\Activity\Projection\Projection;
use Tilttek\Leaderboards\League\Leagues;
use Tilttek\Leaderboards\Period\Period;
use Tilttek\Leaderboards\Store\ScoreStore;

/**
 * The projection that keeps the boards read from the journal up to date: each recorded activity a board reads brings
 * its contribution to the member's score in the period it fell in — the score is kept, not counted again at each
 * display —; a cancellation takes the contributions back and counts the scores they made again.
 *
 * When a board changes, `Projector::rebuild()` forgets the contributions of the period and replays its activity under
 * the new boards. The boards whose measure the application reports are not touched.
 *
 * With the leagues, a member who scores on a board with a league enters a division of its week. With a clock, a gesture
 * older than a sliding window is left out of it; `slide()` takes out of each window the gestures that have grown too
 * old since.
 */
final readonly class LeaderboardProjection implements Projection
{
    public const string NAME = 'leaderboards';

    public function __construct(
        private Boards $boards,
        private ScoreStore $scores,
        private string $name = self::NAME,
        private ?Leagues $leagues = null,
        private ?ClockInterface $clock = null,
    ) {
    }

    #[Override]
    public function name(): string
    {
        return $this->name;
    }

    #[Override]
    public function filter(): Filter
    {
        return Filter::all()->verbs(...$this->boards->patterns());
    }

    #[Override]
    public function apply(Entry $entry): void
    {
        $activity = $entry->activity;

        if ($entry->isCancellation()) {
            $this->recount($this->scores->remove($activity->key->value));

            return;
        }

        $now = $this->clock?->now();

        foreach ($this->boards->all() as $board) {
            $period = $board->matches($activity) ? $board->schedule->at($activity->occurredAt) : null;
            $start = $now instanceof DateTimeImmutable ? $board->windowStart($now) : null;

            if (!$period instanceof Period || ($start instanceof DateTimeImmutable && $activity->occurredAt < $start)) {
                continue;
            }

            $contribution = $board->contribution($activity, $period);

            if ($this->scores->add($contribution)) {
                $this->scores->save(
                    $board->add($this->scores->score($contribution->board, $period->code, $activity->member), $contribution)
                    ?? $board->recount([$contribution, ...array_filter(
                        $this->scores->contributions($contribution->board, $period->code, $activity->member),
                        static fn (Contribution $held): bool => $held->activity !== $contribution->activity,
                    )]),
                );
                $this->leagues?->enter($board->name, $activity->member, $activity->occurredAt);
            }
        }
    }

    #[Override]
    public function reset(?DateTimeImmutable $since): void
    {
        $this->recount($this->scores->forget($since));
    }

    /**
     * Takes out of each board ranked over a sliding window the gestures older than its first day at that moment, counts
     * again the scores they had made, and returns how many contributions left. A scheduler calls it soon after midnight
     * in the boards' timezones.
     */
    public function slide(DateTimeImmutable $now): int
    {
        $expired = 0;

        foreach ($this->boards->all() as $board) {
            $start = $board->windowStart($now);

            if ($start instanceof DateTimeImmutable) {
                $removed = $this->scores->expire($board->name, $start);
                $expired += \count($removed);
                $this->recount($removed);
            }
        }

        return $expired;
    }

    /**
     * Counts again the scores the contributions taken back had made, each once; a score left without a contribution,
     * or on a board no longer declared, is deleted.
     *
     * @param list<Contribution> $removed
     */
    private function recount(array $removed): void
    {
        $scores = [];

        foreach ($removed as $contribution) {
            $scores[json_encode([$contribution->board, $contribution->period, $contribution->member->toString()], \JSON_THROW_ON_ERROR)] = $contribution;
        }

        foreach ($scores as $contribution) {
            $this->recountOne($contribution->board, $contribution->period, $contribution->member);
        }
    }

    private function recountOne(string $key, string $period, ResourceReference $member): void
    {
        $contributions = $this->scores->contributions($key, $period, $member);
        $board = explode(Board::OBJECT_SEPARATOR, $key)[0];

        if ([] === $contributions || !$this->boards->has($board)) {
            $this->scores->delete($key, $period, $member);

            return;
        }

        $this->scores->save($this->boards->get($board)->recount($contributions));
    }
}
