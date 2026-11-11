<?php

declare(strict_types=1);

namespace Tilttek\Progression\Streak;

use Tilttek\Progression\Exception\InvalidProgression;

/**
 * The days — or weeks — a streak forgives: `allowed` missed in any `window` in a row. *One day missed a week*: `new
 * Grace(1, 7)`. A forgiven day keeps the streak alive without adding to it.
 */
final readonly class Grace
{
    public const int MAX_WINDOW = 366;

    public function __construct(
        public int $allowed,
        public int $window,
    ) {
        if ($allowed < 1 || $window <= $allowed || $window > self::MAX_WINDOW) {
            throw InvalidProgression::because(\sprintf('A grace forgives at least 1 missed unit in a window longer than that, of at most %d; %d in %d is not one.', self::MAX_WINDOW, $allowed, $window));
        }
    }
}
