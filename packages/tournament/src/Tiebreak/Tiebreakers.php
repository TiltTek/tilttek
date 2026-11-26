<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tiebreak;

use Tilttek\Tournament\Contract\Tiebreaker;
use Tilttek\Tournament\Exception\InvalidTournament;

/**
 * The tie-breakers a tournament may declare, by their code: those of the library, and those an application adds.
 */
final readonly class Tiebreakers
{
    /** @var array<string, Tiebreaker> */
    private array $byId;

    /**
     * @throws InvalidTournament when two tie-breakers have the same code, or a code is not letters, digits and "-"
     */
    public function __construct(Tiebreaker ...$custom)
    {
        $byId = [];

        foreach ([new Buchholz(), new Buchholz(1), new Buchholz(2), new SonnebornBerger(), new HeadToHead(), new Wins(), new MissionPoints(), ...$custom] as $tiebreaker) {
            $id = $tiebreaker->id();

            if (1 !== preg_match('/^[a-z][a-z0-9-]{0,63}$/D', $id)) {
                throw InvalidTournament::because(\sprintf('The code of a tie-breaker is 1 to 64 lowercase letters, digits or "-", starting with a letter; "%s" is not.', $id));
            }

            if (isset($byId[$id])) {
                throw InvalidTournament::because(\sprintf('Two tie-breakers have the code %s.', $id));
            }

            $byId[$id] = $tiebreaker;
        }

        $this->byId = $byId;
    }

    /**
     * The tie-breakers of these codes, in the order given.
     *
     * @param list<string> $ids
     *
     * @return list<Tiebreaker>
     *
     * @throws InvalidTournament when a code is unknown
     */
    public function get(array $ids): array
    {
        $tiebreakers = [];

        foreach ($ids as $id) {
            $tiebreakers[] = $this->byId[$id] ?? throw InvalidTournament::because(\sprintf('No tie-breaker has the code %s.', $id));
        }

        return $tiebreakers;
    }

    public function has(string $id): bool
    {
        return isset($this->byId[$id]);
    }
}
