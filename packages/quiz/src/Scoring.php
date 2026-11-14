<?php

declare(strict_types=1);

namespace Tilttek\Quiz;

use Tilttek\Quiz\Exception\InvalidQuiz;

/**
 * How an answer turns into points. The points are those of the question, times the grade of the answer, rounded down.
 * In speed mode, a fully right answer to a timed question also earns a bonus: `bonus` percent of the question's
 * points for an instant answer, falling in a straight line to nothing at the end of the time.
 */
final readonly class Scoring
{
    /** The largest bonus, in percent of the points: a quick answer never earns more than twice a slow one. */
    public const int MAX_BONUS = 100;

    public function __construct(
        public ScoringMode $mode = ScoringMode::Accuracy,
        public int $bonus = 50,
    ) {
        if ($bonus < 0 || $bonus > self::MAX_BONUS) {
            throw InvalidQuiz::because(\sprintf('The speed bonus is 0 to %d percent of the points; %d is not allowed.', self::MAX_BONUS, $bonus));
        }
    }

    public static function accuracy(): self
    {
        return new self(ScoringMode::Accuracy);
    }

    public static function speed(int $bonus = 50): self
    {
        return new self(ScoringMode::Speed, $bonus);
    }

    /**
     * The points the grade earns, before any bonus.
     */
    public function points(int $points, Grade $grade): int
    {
        return intdiv($points * $grade->permille, Grade::FULL);
    }

    /**
     * The speed bonus of an answer, nothing in accuracy mode, for an answer not fully right, for an untimed question or
     * once the time is up.
     *
     * @param int|null $limit the time limit of the question, in seconds
     */
    public function bonus(int $points, Grade $grade, int $elapsedMilliseconds, ?int $limit): int
    {
        if (ScoringMode::Speed !== $this->mode || !$grade->isRight() || null === $limit) {
            return 0;
        }

        $window = $limit * 1000;
        $left = max(0, $window - max(0, $elapsedMilliseconds));

        return intdiv(intdiv($points * $this->bonus, 100) * $left, $window);
    }

    /**
     * The most a question can earn: its points, and its whole bonus when there can be one.
     */
    public function most(int $points, ?int $limit): int
    {
        if (ScoringMode::Speed !== $this->mode || null === $limit) {
            return $points;
        }

        return $points + intdiv($points * $this->bonus, 100);
    }
}
