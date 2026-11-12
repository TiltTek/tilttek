<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use DateTimeImmutable;
use DateTimeZone;
use Tilttek\Activity\Activity;

/**
 * What a board ranks on. Four measures read the journal of activity: the number of gestures (`count`), the sum of
 * their quantities (`total`) — points earned, pages read —, the best quantity of a single gesture (`best`) — a high
 * score —, and the number of distinct days with a gesture, in the board's timezone (`days`) — the regularity of a
 * member. Two take a value the application reports, read from a balance it keeps elsewhere — a level, a points
 * balance, the longest streak —: the last value reported (`latest`), or the highest (`highest`).
 */
enum Measure: string
{
    case Count = 'count';
    case Total = 'total';
    case Best = 'best';
    case Days = 'days';
    case Latest = 'latest';
    case Highest = 'highest';

    /**
     * Whether the measure is kept from the journal of activity, rather than reported by the application.
     */
    public function readsJournal(): bool
    {
        return match ($this) {
            self::Count, self::Total, self::Best, self::Days => true,
            self::Latest, self::Highest => false,
        };
    }

    /**
     * What the activity brings: one gesture — or one day —, or its quantity.
     */
    public function amountFor(Activity $activity): int
    {
        return self::Count === $this || self::Days === $this ? 1 : $activity->quantity;
    }

    /**
     * The value and the moment it was reached once the contribution is added to the score: the best score keeps the
     * highest amount — and the earliest moment it was reached —; the days count a later day once, reached at the first
     * gesture of the latest day; the others add it up, reached at the latest gesture that counted. Null for a gesture
     * on an earlier day than the score's, whose days are counted again from every contribution.
     *
     * @return array{int, DateTimeImmutable}|null
     */
    public function add(?Score $score, Contribution $contribution, DateTimeZone $timezone): ?array
    {
        if (!$score instanceof Score) {
            return [$contribution->amount, $contribution->occurredAt];
        }

        return match ($this) {
            self::Best => $contribution->amount > $score->value || ($contribution->amount === $score->value && $contribution->occurredAt < $score->reachedAt)
                ? [$contribution->amount, $contribution->occurredAt]
                : [$score->value, $score->reachedAt],
            self::Days => match (self::day($contribution->occurredAt, $timezone) <=> self::day($score->reachedAt, $timezone)) {
                1 => [min(Score::MAX, $score->value + 1), $contribution->occurredAt],
                0 => [$score->value, min($score->reachedAt, $contribution->occurredAt)],
                default => null,
            },
            default => [min(Score::MAX, $score->value + $contribution->amount), max($score->reachedAt, $contribution->occurredAt)],
        };
    }

    /**
     * The value and the moment it was reached, counted again from every contribution, taken in the order they
     * occurred.
     *
     * @param non-empty-list<Contribution> $contributions
     *
     * @return array{int, DateTimeImmutable}
     */
    public function recount(array $contributions, DateTimeZone $timezone): array
    {
        usort($contributions, static fn (Contribution $a, Contribution $b): int => $a->occurredAt <=> $b->occurredAt);
        $value = $contributions[0]->amount;
        $reachedAt = $contributions[0]->occurredAt;

        foreach (\array_slice($contributions, 1) as $contribution) {
            // In the order they occurred, no contribution falls on an earlier day than the score's.
            [$value, $reachedAt] = $this->add(new Score($contribution->board, $contribution->period, $contribution->member, $value, $reachedAt), $contribution, $timezone) ?? [$value, $reachedAt];
        }

        return [$value, $reachedAt];
    }

    /**
     * The day of the moment in the timezone, as a code that sorts in time.
     */
    private static function day(DateTimeImmutable $moment, DateTimeZone $timezone): string
    {
        return $moment->setTimezone($timezone)->format('Y-m-d');
    }
}
