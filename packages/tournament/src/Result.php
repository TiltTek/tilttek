<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

use Tilttek\Tournament\Exception\InvalidResult;

/**
 * The result of a match: its outcome and, for the games that score them, the mission points of each side beside the
 * win, as miniatures games do.
 */
final readonly class Result
{
    /** The most mission points a side scores in a match. */
    public const int MAX_MISSION_POINTS = 1_000_000;

    /**
     * @throws InvalidResult when only one side has mission points, or when they are out of range
     */
    public function __construct(
        public Outcome $outcome,
        public ?int $firstMission = null,
        public ?int $secondMission = null,
    ) {
        if ((null === $firstMission) !== (null === $secondMission)) {
            throw InvalidResult::because('Both sides of a match have mission points, or neither has.');
        }

        foreach ([$firstMission, $secondMission] as $points) {
            if (null !== $points && ($points < 0 || $points > self::MAX_MISSION_POINTS)) {
                throw InvalidResult::because(\sprintf('The mission points of a side are 0 to %d.', self::MAX_MISSION_POINTS));
            }
        }
    }

    public static function firstWins(?int $firstMission = null, ?int $secondMission = null): self
    {
        return new self(Outcome::FirstWins, $firstMission, $secondMission);
    }

    public static function secondWins(?int $firstMission = null, ?int $secondMission = null): self
    {
        return new self(Outcome::SecondWins, $firstMission, $secondMission);
    }

    public static function draw(?int $firstMission = null, ?int $secondMission = null): self
    {
        return new self(Outcome::Draw, $firstMission, $secondMission);
    }

    /**
     * Whether the two results say the same: the same outcome and the same mission points.
     */
    public function equals(self $other): bool
    {
        return $this->outcome === $other->outcome
            && $this->firstMission === $other->firstMission
            && $this->secondMission === $other->secondMission;
    }
}
