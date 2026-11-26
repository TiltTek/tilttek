<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Standings;

use Tilttek\Tournament\Contract\Tiebreaker;
use Tilttek\Tournament\Records;
use Tilttek\Tournament\SeededDraw;

/**
 * Ranks participants by their points, then by the tie-breakers in the order declared: each tie-breaker splits the group
 * still tied, and the next one is computed on each smaller group left. A tie left at the end is broken by the draw of
 * the published seed, never by a chance that cannot be replayed.
 */
final readonly class StandingsCalculator
{
    /**
     * @param list<Tiebreaker> $tiebreakers in the order declared
     */
    public function __construct(
        private array $tiebreakers,
        private string $seed,
    ) {
    }

    /**
     * @param list<string>        $participants every participant to rank
     * @param array<string, true> $withdrawn    the participants who withdrew, by participant
     */
    public function compute(array $participants, Records $records, array $withdrawn = []): Standings
    {
        $byPoints = [];

        foreach ($participants as $participant) {
            $byPoints[$records->points($participant)][] = $participant;
        }

        krsort($byPoints);
        $ranked = [];

        foreach ($byPoints as $points => $group) {
            foreach ($this->split($group, $records, 0, []) as [$participant, $tiebreaks, $drawn]) {
                $ranked[] = new Standing($participant, \count($ranked) + 1, $points, $tiebreaks, $drawn, isset($withdrawn[$participant]));
            }
        }

        return new Standings($ranked);
    }

    /**
     * The group in its final order, each participant with the scores of the tie-breakers computed for it.
     *
     * @param list<string>                      $group
     * @param array<string, array<string, int>> $known the scores computed so far, by participant
     *
     * @return list<array{string, array<string, int>, bool}>
     */
    private function split(array $group, Records $records, int $level, array $known): array
    {
        if (1 === \count($group)) {
            return [[$group[0], $known[$group[0]] ?? [], false]];
        }

        $tiebreaker = $this->tiebreakers[$level] ?? null;

        if (null === $tiebreaker) {
            return array_map(static fn (string $participant): array => [$participant, $known[$participant] ?? [], true], SeededDraw::order($this->seed, $group));
        }

        $scores = $tiebreaker->scores($group, $records);
        $byScore = [];

        foreach ($group as $participant) {
            $score = $scores[$participant] ?? 0;
            $known[$participant][$tiebreaker->id()] = $score;
            $byScore[$score][] = $participant;
        }

        krsort($byScore);
        $ordered = [];

        foreach ($byScore as $tied) {
            array_push($ordered, ...$this->split($tied, $records, $level + 1, $known));
        }

        return $ordered;
    }
}
