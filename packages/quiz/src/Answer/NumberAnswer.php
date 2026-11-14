<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Answer;

use Tilttek\Quiz\Contract\Answer;

/**
 * The whole number a player gives to a numeric question: a year, a count, a distance in the unit the question names.
 */
final readonly class NumberAnswer implements Answer
{
    public function __construct(
        public int $number,
    ) {
    }
}
