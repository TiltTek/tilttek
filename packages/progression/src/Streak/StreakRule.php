<?php

declare(strict_types=1);

namespace Tilttek\Progression\Streak;

use Tilttek\Activity\Activity;
use Tilttek\Activity\Context;
use Tilttek\Activity\Verbs;
use Tilttek\Progression\Exception\InvalidProgression;
use Tilttek\Progression\Names;

/**
 * A streak: the days — or weeks — in a row a member makes one of the gestures it reads, with the facts the activity's
 * context must hold, and the grace it gives, if any.
 *
 * *Seven days of play in a row, one day forgiven a week*: `new StreakRule('daily-play', ['game.played'],
 * StreakUnit::Day, new Grace(1, 7))`.
 */
final readonly class StreakRule
{
    public Verbs $verbs;

    public Context $conditions;

    /**
     * @param list<string>                   $verbs
     * @param array<string, bool|int|string> $context
     */
    public function __construct(
        public string $name,
        array $verbs,
        public StreakUnit $unit = StreakUnit::Day,
        public ?Grace $grace = null,
        array $context = [],
    ) {
        Names::check($name, 'streak');

        if ([] === $verbs) {
            throw InvalidProgression::because(\sprintf('The streak "%s" reads at least one verb.', $name));
        }

        $this->verbs = Verbs::of($verbs);
        $this->conditions = new Context($context);
    }

    public function matches(Activity $activity): bool
    {
        return $this->verbs->allows($activity->verb)
            && array_all($this->conditions->values, static fn (bool|int|string $value, string $name): bool => $activity->context->get($name) === $value);
    }
}
