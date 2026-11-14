<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Question;

use Override;
use Tilttek\Quiz\Answer\NumberAnswer;
use Tilttek\Quiz\Contract\Answer;
use Tilttek\Quiz\Contract\Question;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Grade;

/**
 * A question answered by a whole number — a year, a count —, right when the number is within the tolerance of the
 * answer, both ends included.
 */
final readonly class NumericQuestion implements Question
{
    /** The widest tolerance, so that the bounds of the answer stay far from the limits of an integer. */
    public const int MAX_TOLERANCE = 1_000_000_000;

    public function __construct(
        private string $id,
        private string $prompt,
        public int $answer,
        public int $tolerance = 0,
        private int $points = 100,
        private ?int $timeLimit = null,
    ) {
        Rules::check($id, $prompt, $points, $timeLimit);

        if ($tolerance < 0 || $tolerance > self::MAX_TOLERANCE) {
            throw InvalidQuiz::because(\sprintf('The tolerance of the question %s is 0 to %d; %d is not allowed.', $id, self::MAX_TOLERANCE, $tolerance));
        }

        if (abs($answer) > \PHP_INT_MAX - self::MAX_TOLERANCE) {
            throw InvalidQuiz::because(\sprintf('The answer of the question %s is too far from zero.', $id));
        }
    }

    #[Override]
    public function id(): string
    {
        return $this->id;
    }

    #[Override]
    public function prompt(): string
    {
        return $this->prompt;
    }

    #[Override]
    public function points(): int
    {
        return $this->points;
    }

    #[Override]
    public function timeLimit(): ?int
    {
        return $this->timeLimit;
    }

    #[Override]
    public function grade(Answer $answer): Grade
    {
        $number = Rules::expect($this->id, $answer, NumberAnswer::class)->number;

        return $number >= $this->answer - $this->tolerance && $number <= $this->answer + $this->tolerance ? Grade::right() : Grade::wrong();
    }
}
