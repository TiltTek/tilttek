<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Feedback;

/**
 * The stars and wishes of one session, gathered without saying who gave which: every star and every wish in
 * alphabetical order rather than in the order they were given, and how many participants answered.
 */
final readonly class FeedbackSummary
{
    /**
     * @param list<string> $stars
     * @param list<string> $wishes
     */
    private function __construct(
        public array $stars,
        public array $wishes,
        public int $respondents,
    ) {
    }

    /**
     * @param list<StarsAndWishes> $feedback each participant's
     */
    public static function of(array $feedback): self
    {
        $stars = [];
        $wishes = [];
        $respondents = 0;

        foreach ($feedback as $one) {
            if ($one->isEmpty()) {
                continue;
            }

            ++$respondents;
            array_push($stars, ...$one->stars);
            array_push($wishes, ...$one->wishes);
        }

        sort($stars, \SORT_STRING | \SORT_FLAG_CASE);
        sort($wishes, \SORT_STRING | \SORT_FLAG_CASE);

        return new self($stars, $wishes, $respondents);
    }
}
