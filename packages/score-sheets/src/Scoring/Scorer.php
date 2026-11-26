<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Scoring;

use Tilttek\ScoreSheets\Arithmetic;
use Tilttek\ScoreSheets\Direction;
use Tilttek\ScoreSheets\Entries;
use Tilttek\ScoreSheets\Exception\InvalidEntry;
use Tilttek\ScoreSheets\ParticipantId;
use Tilttek\ScoreSheets\SheetDefinition;
use Tilttek\ScoreSheets\TeamCombine;

/**
 * Computes a scoreboard from a sheet and its entries: each category in the declared order, entered or computed, the
 * total of the categories that count, the ranks — total first, then the tie-breakers in order, then a shared rank —,
 * and the score of each team. It keeps no state: call it at every entry.
 */
final readonly class Scorer
{
    /**
     * @throws InvalidEntry when a value is too large to compute
     */
    public function score(SheetDefinition $sheet, Entries $entries): Scoreboard
    {
        // Participants by their place on the sheet: an identifier of digits would turn into an integer key.
        $table = array_fill_keys(array_keys($entries->participants), []);
        $steps = $table;

        foreach ($sheet->categories() as $category) {
            $computed = $category->operation?->apply($category->key, $table) ?? array_map(static fn (ParticipantId $participant): Step => new Step($category->key, 'entered', [], $entries->value($participant, $category->key) ?? 0), $entries->participants);

            foreach ($computed as $index => $step) {
                $table[$index][$category->key] = $step->value;
                $steps[$index][] = $step;
            }
        }

        $totals = [];

        foreach ($table as $index => $values) {
            $totals[$index] = 0;

            foreach ($sheet->categories() as $category) {
                if ($category->counted) {
                    $totals[$index] = Arithmetic::add($totals[$index], $values[$category->key], 'total');
                }
            }
        }

        $compare = static function (int $left, int $right) use ($sheet, $table, $totals): int {
            $by = $totals[$right] <=> $totals[$left];

            foreach ($sheet->tiebreakers as $tiebreaker) {
                if (0 !== $by) {
                    return $by;
                }

                $by = Direction::Most === $tiebreaker->prefer
                    ? $table[$right][$tiebreaker->category] <=> $table[$left][$tiebreaker->category]
                    : $table[$left][$tiebreaker->category] <=> $table[$right][$tiebreaker->category];
            }

            return $by;
        };
        $scores = [];

        foreach ($this->ranked(array_keys($totals), $compare) as $index => $rank) {
            $scores[] = new ParticipantScore($entries->participants[$index], $table[$index], $totals[$index], $rank, $steps[$index]);
        }

        return new Scoreboard($sheet->game, $scores, $this->teams($sheet, $entries, $totals), $entries->isComplete($sheet));
    }

    /**
     * @param array<int, int> $totals by place on the sheet
     *
     * @return list<TeamScore>
     */
    private function teams(SheetDefinition $sheet, Entries $entries, array $totals): array
    {
        $places = array_flip(array_map(static fn (ParticipantId $participant): string => $participant->value, $entries->participants));
        $members = [];
        $byTeam = [];

        foreach ($entries->teams as $index => $team) {
            $members[$index] = array_map(static fn (ParticipantId $member): array => ['participant' => $member->value, 'total' => $totals[$places[$member->value]]], $team->members);
            $memberTotals = array_column($members[$index], 'total');
            $byTeam[$index] = TeamCombine::Best === $sheet->teams ? max($memberTotals) : array_reduce($memberTotals, static fn (int $sum, int $total): int => Arithmetic::add($sum, $total, 'team'), 0);
        }

        $scores = [];

        foreach ($this->ranked(array_keys($byTeam), static fn (int $left, int $right): int => $byTeam[$right] <=> $byTeam[$left]) as $index => $rank) {
            $scores[] = new TeamScore($entries->teams[$index]->id, $members[$index], $sheet->teams, $byTeam[$index], $rank);
        }

        return $scores;
    }

    /**
     * Sorts the keys, then ranks them from 1: equal neighbours share the rank, and the ranks they fill are skipped.
     *
     * @param list<int>               $keys
     * @param callable(int, int): int $compare
     *
     * @return array<int, int> the rank of each key, in rank order
     */
    private function ranked(array $keys, callable $compare): array
    {
        usort($keys, $compare);
        $ranks = [];
        $rank = 0;

        foreach ($keys as $index => $key) {
            if (0 === $index || 0 !== $compare($keys[$index - 1], $key)) {
                $rank = $index + 1;
            }

            $ranks[$key] = $rank;
        }

        return $ranks;
    }
}
