<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

use Tilttek\Tournament\Exception\InvalidTournament;

/**
 * The points a match gives, in whole numbers: a win, a draw, a loss, a bye. A forfeit win counts as a win and a
 * forfeit loss as a loss, unless set apart. Chess counts 1, ½ and 0: here 2, 1 and 0, the same order in whole numbers.
 */
final readonly class Scoring
{
    /** The most points a match gives. */
    public const int MAX_POINTS = 1000;

    /**
     * @throws InvalidTournament when a number is out of range, or when a draw gives more than a win or less than a loss
     */
    public function __construct(
        public int $win = 2,
        public int $draw = 1,
        public int $loss = 0,
        public ?int $bye = null,
        public ?int $forfeitWin = null,
        public ?int $forfeitLoss = null,
    ) {
        foreach ([$win, $draw, $loss, $bye, $forfeitWin, $forfeitLoss] as $points) {
            if (null !== $points && ($points < 0 || $points > self::MAX_POINTS)) {
                throw InvalidTournament::because(\sprintf('The points of a match are 0 to %d.', self::MAX_POINTS));
            }
        }

        if ($draw > $win || $loss > $draw) {
            throw InvalidTournament::because('A win gives at least the points of a draw, and a draw at least those of a loss.');
        }
    }

    /**
     * Three points a win, one a draw, none a loss: the scoring of many card and miniatures games.
     */
    public static function threeForAWin(): self
    {
        return new self(3, 1, 0);
    }

    /**
     * The points of a bye: a win unless set apart.
     */
    public function byePoints(): int
    {
        return $this->bye ?? $this->win;
    }

    /**
     * The points each side scores for a result: the first participant's, then the second's.
     *
     * @return array{int, int}
     */
    public function points(Outcome $outcome): array
    {
        return match ($outcome) {
            Outcome::FirstWins => [$this->win, $this->loss],
            Outcome::SecondWins => [$this->loss, $this->win],
            Outcome::Draw => [$this->draw, $this->draw],
            Outcome::FirstForfeits => [$this->forfeitLoss ?? $this->loss, $this->forfeitWin ?? $this->win],
            Outcome::SecondForfeits => [$this->forfeitWin ?? $this->win, $this->forfeitLoss ?? $this->loss],
            Outcome::BothForfeit => [$this->forfeitLoss ?? $this->loss, $this->forfeitLoss ?? $this->loss],
        };
    }
}
