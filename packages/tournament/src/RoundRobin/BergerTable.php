<?php

declare(strict_types=1);

namespace Tilttek\Tournament\RoundRobin;

use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Pairing;
use Tilttek\Tournament\PairingReason;
use Tilttek\Tournament\ReasonKind;

/**
 * The rounds of a pool where everyone meets everyone, by the Berger tables, built by the circle method: the last number
 * stays in place and the others turn by one each round. Each pair meets once a leg, every round has the same number of
 * tables, and each participant plays first in half of their matches, give or take one. With an odd number of
 * participants, one rests each round, each in turn, and the rest shows in their schedule. A second leg plays the same
 * rounds again with the sides swapped.
 */
final class BergerTable
{
    /** The most participants of a pool. */
    public const int MAX_PARTICIPANTS = 64;

    private function __construct()
    {
    }

    /**
     * The tables of each round, the first round first.
     *
     * @param list<string> $participants in seed order: the first gets the number 1
     * @param int          $legs         1, or 2 for return matches
     *
     * @return list<list<Pairing>>
     *
     * @throws InvalidTournament when the pool has fewer than 2 or more than 64 participants, or the legs are not 1 or 2
     */
    public static function rounds(array $participants, int $legs = 1, ?string $pool = null): array
    {
        $count = \count($participants);

        if ($count < 2 || $count > self::MAX_PARTICIPANTS) {
            throw InvalidTournament::because(\sprintf('A pool has 2 to %d participants.', self::MAX_PARTICIPANTS));
        }

        if (1 !== $legs && 2 !== $legs) {
            throw InvalidTournament::because('A pool is played in 1 or 2 legs.');
        }

        $numbers = $count + ($count % 2);
        $turning = $numbers - 1;
        $rounds = [];

        for ($round = 0; $round < $turning; ++$round) {
            $pairs = [0 === $round % 2 ? [$round, $turning] : [$turning, $round]];

            for ($step = 1; $step < $numbers / 2; ++$step) {
                $up = ($round + $step) % $turning;
                $down = ($round - $step + $turning) % $turning;
                $pairs[] = 1 === $step % 2 ? [$down, $up] : [$up, $down];
            }

            $rounds[] = $pairs;
        }

        $tables = [];

        foreach ($rounds as $pairs) {
            $tables[] = self::tables($participants, $pairs, false, $pool);
        }

        if (2 === $legs) {
            foreach ($rounds as $pairs) {
                $tables[] = self::tables($participants, $pairs, true, $pool);
            }
        }

        return $tables;
    }

    /**
     * @param list<string>          $participants
     * @param list<array{int, int}> $pairs
     *
     * @return list<Pairing>
     */
    private static function tables(array $participants, array $pairs, bool $return, ?string $pool): array
    {
        $tables = [];
        $rest = null;

        foreach ($pairs as [$one, $two]) {
            [$first, $second] = $return ? [$participants[$two] ?? null, $participants[$one] ?? null] : [$participants[$one] ?? null, $participants[$two] ?? null];

            if (null === $first) {
                $rest = $second;

                continue;
            }

            if (null === $second) {
                $rest = $first;

                continue;
            }

            $kind = $return ? ReasonKind::ReturnMatch : ReasonKind::Scheduled;
            $tables[] = new Pairing(\count($tables) + 1, $first, $second, [new PairingReason($kind, $first, $second), new PairingReason($kind, $second, $first)], $pool);
        }

        if (null !== $rest) {
            $tables[] = new Pairing(\count($tables) + 1, $rest, null, [new PairingReason(ReasonKind::Rest, $rest)], $pool, true);
        }

        return $tables;
    }
}
