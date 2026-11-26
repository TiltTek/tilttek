<?php

declare(strict_types=1);

namespace Tilttek\Tournament;

use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\RoundRobin\Pools;

/**
 * A stage of a tournament: its format, its rounds for a Swiss stage, its pools and legs for a round robin, and how many
 * of its participants go on to the next stage — in all for a Swiss stage, in each pool for a round robin.
 */
final readonly class Stage
{
    /** The most rounds of a Swiss stage. */
    public const int MAX_ROUNDS = 64;

    /**
     * @throws InvalidTournament when the identifier, the rounds, the pools, the legs or the qualifiers are out of range
     */
    public function __construct(
        public string $id,
        public StageFormat $format,
        public int $rounds = 0,
        public int $pools = 1,
        public int $legs = 1,
        public int $qualifiers = 0,
    ) {
        Identifier::check($id, 'stage');

        if (StageFormat::Swiss === $format && ($rounds < 1 || $rounds > self::MAX_ROUNDS)) {
            throw InvalidTournament::because(\sprintf('A Swiss stage has 1 to %d rounds.', self::MAX_ROUNDS));
        }

        if ($pools < 1 || $pools > Pools::MAX_POOLS || (1 !== $pools && StageFormat::RoundRobin !== $format)) {
            throw InvalidTournament::because(\sprintf('A round robin has 1 to %d pools; the other stages have one.', Pools::MAX_POOLS));
        }

        if (1 !== $legs && (2 !== $legs || StageFormat::RoundRobin !== $format)) {
            throw InvalidTournament::because('A round robin is played in 1 or 2 legs; the other stages in one.');
        }

        if ($qualifiers < 0 || (0 !== $qualifiers && StageFormat::SingleElimination === $format)) {
            throw InvalidTournament::because('The qualifiers of a stage are 0 or more, and an elimination has none: it ends the tournament.');
        }
    }

    /**
     * @param int $qualifiers the participants who go on to the next stage, the first of the standings
     */
    public static function swiss(string $id, int $rounds, int $qualifiers = 0): self
    {
        return new self($id, StageFormat::Swiss, $rounds, qualifiers: $qualifiers);
    }

    /**
     * @param int $qualifiers the participants of each pool who go on to the next stage
     */
    public static function roundRobin(string $id, int $pools = 1, int $legs = 1, int $qualifiers = 0): self
    {
        return new self($id, StageFormat::RoundRobin, pools: $pools, legs: $legs, qualifiers: $qualifiers);
    }

    public static function singleElimination(string $id): self
    {
        return new self($id, StageFormat::SingleElimination);
    }

    /**
     * How many participants go on: the qualifiers of each pool times the pools.
     */
    public function advancing(): int
    {
        return $this->qualifiers * $this->pools;
    }
}
