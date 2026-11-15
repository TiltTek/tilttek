<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Feedback;

use Tilttek\TableSafety\Exception\InvalidAnswer;
use Tilttek\TableSafety\RespondentThreshold;

/**
 * The stars and wishes of one session, gathered without saying who gave which: every star and every wish in
 * alphabetical order rather than in the order they were given, and how many participants' answers it holds.
 *
 * A summary holds answers only in whole groups of `$threshold` participants, taken in the order they were given: under
 * the threshold it holds none and says only that there are not enough answers; past it, the answers of a group that
 * is not full wait for the group to fill. One more answer therefore never changes the summary by the answers of one
 * person alone.
 */
final readonly class FeedbackSummary
{
    /** How many participants' answers a summary waits for, unless the application chooses otherwise. */
    public const int DEFAULT_THRESHOLD = RespondentThreshold::DEFAULT;

    /**
     * @param list<string> $stars
     * @param list<string> $wishes
     * @param int          $respondents how many participants' answers the summary holds: 0, or a multiple of the threshold
     * @param positive-int $threshold
     */
    private function __construct(
        public array $stars,
        public array $wishes,
        public int $respondents,
        public int $threshold,
    ) {
    }

    /**
     * @param list<StarsAndWishes> $feedback each participant's, in the order they were given
     *
     * @throws InvalidAnswer when the threshold is under 1
     */
    public static function of(array $feedback, int $threshold = self::DEFAULT_THRESHOLD): self
    {
        self::assertThreshold($threshold);

        $answered = array_filter($feedback, static fn (StarsAndWishes $one): bool => !$one->isEmpty());
        $respondents = RespondentThreshold::counted(\count($answered), $threshold);
        $stars = [];
        $wishes = [];

        foreach (\array_slice($answered, 0, $respondents) as $one) {
            array_push($stars, ...$one->stars);
            array_push($wishes, ...$one->wishes);
        }

        sort($stars, \SORT_STRING | \SORT_FLAG_CASE);
        sort($wishes, \SORT_STRING | \SORT_FLAG_CASE);

        return new self($stars, $wishes, $respondents, $threshold);
    }

    /**
     * The same check as `RespondentThreshold::assert()`.
     *
     * @phpstan-assert positive-int $threshold
     *
     * @throws InvalidAnswer when the threshold is under 1
     */
    public static function assertThreshold(int $threshold): void
    {
        RespondentThreshold::assert($threshold);
    }

    /**
     * Whether the summary holds any answer; false means "not enough answers yet".
     */
    public function hasEnoughAnswers(): bool
    {
        return $this->respondents > 0;
    }
}
