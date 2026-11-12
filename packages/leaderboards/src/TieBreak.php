<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards;

use Tilttek\Activity\Activity;
use Tilttek\Leaderboards\Exception\InvalidLeaderboard;

/**
 * A measure that orders members with the same score, before the first to reach it or a shared rank: what it gathers
 * from the member's gestures — their number, the sum, the highest or the lowest of their quantities, or of an integer
 * of their context —, or the value the application reports with the score; and which way it orders them.
 *
 * *The best score, the fewest attempts first*: `new TieBreak(Aggregate::Count, Direction::Ascending)`. *The highest
 * total, the shortest time first*: `new TieBreak(Aggregate::Lowest, Direction::Ascending, 'seconds')`.
 */
final readonly class TieBreak
{
    public function __construct(
        public Aggregate $aggregate,
        public Direction $direction = Direction::Descending,
        public ?string $context = null,
    ) {
        if (null !== $context) {
            Names::check($context, 'context');

            if (Aggregate::Count === $aggregate || Aggregate::Reported === $aggregate) {
                throw InvalidLeaderboard::because(\sprintf('A tie-break that reads the context value "%s" adds it up, keeps the highest or the lowest; it does not count nor take a reported value.', $context));
            }
        }
    }

    /**
     * Whether the activity holds what the tie-break reads: an integer of 0 to 10^15 under its context name, when it
     * reads one.
     */
    public function reads(Activity $activity): bool
    {
        if (null === $this->context) {
            return true;
        }

        $value = $activity->context->get($this->context);

        return \is_int($value) && $value >= 0 && $value <= Score::MAX;
    }

    /**
     * What one activity brings to the tie-break: one gesture, its quantity, or its context value.
     */
    public function amountFor(Activity $activity): int
    {
        if (Aggregate::Count === $this->aggregate) {
            return 1;
        }

        $value = null === $this->context ? $activity->quantity : $activity->context->get($this->context);

        return \is_int($value) ? $value : 0;
    }
}
