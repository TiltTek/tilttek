<?php

declare(strict_types=1);

namespace Tilttek\Bingo;

use Tilttek\Activity\Activity;
use Tilttek\Activity\VerbPattern;

/**
 * What ticks a square in a game ticked by activity: a gesture of the member recorded in the activity journal, by
 * verb — exact, `talk.*` or `*` — and, when given, on one object only.
 */
final readonly class Trigger
{
    public VerbPattern $verb;

    public function __construct(
        string $verb,
        public ?string $object = null,
    ) {
        $this->verb = VerbPattern::fromString($verb);
    }

    public function matches(Activity $activity): bool
    {
        if (!$this->verb->matches($activity->verb)) {
            return false;
        }

        return null === $this->object || $this->object === $activity->object?->toString();
    }
}
