<?php

declare(strict_types=1);

namespace Tilttek\Quiz;

use Tilttek\Quiz\Exception\InvalidQuiz;

/**
 * How right an answer is, in thousandths: 1000 is fully right, 0 is wrong, anything between is the partial credit of
 * a question that gives one. Whole numbers keep every score exact.
 */
final readonly class Grade
{
    public const int FULL = 1000;

    private function __construct(
        public int $permille,
    ) {
    }

    public static function of(int $permille): self
    {
        if ($permille < 0 || $permille > self::FULL) {
            throw InvalidQuiz::because(\sprintf('A grade is 0 to %d thousandths; %d is not one.', self::FULL, $permille));
        }

        return new self($permille);
    }

    public static function right(): self
    {
        return new self(self::FULL);
    }

    public static function wrong(): self
    {
        return new self(0);
    }

    /**
     * The share `$part / $whole` in thousandths, rounded down; nothing of nothing is wrong.
     */
    public static function share(int $part, int $whole): self
    {
        if ($whole < 1 || $part < 1) {
            return new self(0);
        }

        return new self(intdiv(min($part, $whole) * self::FULL, $whole));
    }

    public function isRight(): bool
    {
        return self::FULL === $this->permille;
    }

    public function isWrong(): bool
    {
        return 0 === $this->permille;
    }
}
