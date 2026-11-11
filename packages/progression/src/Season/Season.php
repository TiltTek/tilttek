<?php

declare(strict_types=1);

namespace Tilttek\Progression\Season;

use DateTimeImmutable;
use Tilttek\Progression\Exception\InvalidProgression;

/**
 * A named period at the end of which a track starts again from zero: a month, a summer, an event. It starts at
 * `start`, included, and ends at `end`, excluded.
 */
final readonly class Season
{
    public function __construct(
        public string $code,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
    ) {
        if (1 !== preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $code)) {
            throw InvalidProgression::because(\sprintf('"%s" is not a season code: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit.', $code));
        }

        if ($end <= $start) {
            throw InvalidProgression::because(\sprintf('The season "%s" ends after it starts.', $code));
        }
    }

    public function holds(DateTimeImmutable $moment): bool
    {
        return $moment >= $this->start && $moment < $this->end;
    }
}
