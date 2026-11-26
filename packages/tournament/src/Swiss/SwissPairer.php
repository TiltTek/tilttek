<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Swiss;

use Tilttek\Tournament\Exception\InvalidTournament;
use Tilttek\Tournament\Pairing;
use Tilttek\Tournament\PairingReason;
use Tilttek\Tournament\ReasonKind;
use Tilttek\Tournament\Record;
use Tilttek\Tournament\Records;

/**
 * Pairs a round of a Swiss stage by score groups, after the rules of the FIDE Swiss system taken as a written reference,
 * not as a certified implementation of them.
 *
 * Participants are ranked by points, then by their initial order. Each possible pair has a cost, and the pairing of the
 * round is the set of pairs of the lowest total cost; the costs weigh, from the heaviest to the lightest:
 *
 * 1. two participants who already played each other over the board, or a second bye;
 * 2. the bye given to a participant with more points;
 * 3. the difference of points between the two of a pair, squared, so that one large gap weighs more than two small;
 * 4. two participants due to play the same side, more so when both must;
 * 5. inside a score group, a pair other than the higher half against the lower half, in order; between two groups, a
 *    pair far apart in the ranking; for the bye, a participant ranked higher.
 *
 * Up to `$exactLimit` participants, the bye counted as one, the lowest total cost is found exactly; above, a bounded
 * search finds a low one. Both give the same pairing each time for the same records and order: the initial order comes
 * from the published seed when no seeding sets it.
 *
 * The pairer keeps nothing between calls.
 */
final readonly class SwissPairer
{
    /** The most participants a round pairs. */
    public const int MAX_PARTICIPANTS = 512;

    /** The cost of a rematch or a second bye: above every other cost of a round put together. */
    private const int FORBIDDEN = 10_000_000_000_000_000;

    /** The most rematches or byes a cost counts, so that no sum of costs goes past the largest integer. */
    private const int MOST_REPEATS = 3;

    /** The largest squared gap of points a cost counts. */
    private const int MOST_GAP = 1_000_000;

    private const int BYE_POINTS = 10_000_000_000;

    private const int POINTS_GAP = 100_000_000;

    private const int SIDE_MUST = 1_000_000;

    private const int SIDE_DUE = 100_000;

    private const int SPLIT = 100;

    /**
     * @param int $exactLimit the most participants, the bye counted as one, paired exactly: 2 to 20
     * @param int $passes     the passes of exchanges of the bounded search: 1 to 100
     *
     * @throws InvalidTournament when a bound is out of range
     */
    public function __construct(
        private int $exactLimit = 16,
        private int $passes = 20,
    ) {
        if ($exactLimit < 2 || $exactLimit > 20) {
            throw InvalidTournament::because('The exact pairing takes 2 to 20 participants.');
        }

        if ($passes < 1 || $passes > 100) {
            throw InvalidTournament::because('The bounded pairing makes 1 to 100 passes.');
        }
    }

    /**
     * The tables of a round, the highest ranked first and the bye last, each with its reasons.
     *
     * @param list<string> $active  the participants to pair, those who withdrew left out, in their initial order
     * @param Records      $records the records of the stage so far
     *
     * @return list<Pairing>
     *
     * @throws InvalidTournament when there are more participants than a round pairs
     */
    public function pair(array $active, Records $records): array
    {
        if (\count($active) > self::MAX_PARTICIPANTS) {
            throw InvalidTournament::because(\sprintf('A Swiss round pairs %d participants at most.', self::MAX_PARTICIPANTS));
        }

        $ranked = $this->rank($active, $records);
        $count = \count($ranked);
        $vertices = $count + ($count % 2);
        $facts = array_map(static function (array $entry) use ($records): array {
            $record = $records->of($entry['participant']);

            return ['met' => $record->met(), 'byes' => $record->byes(), 'due' => self::due($record)];
        }, $ranked);
        $costs = [];

        for ($i = 0; $i < $vertices; ++$i) {
            $row = [];

            for ($j = 0; $j < $vertices; ++$j) {
                $row[] = $i === $j ? 0 : $this->cost($ranked, $facts, min($i, $j), max($i, $j));
            }

            $costs[] = $row;
        }

        $matcher = $vertices <= $this->exactLimit ? new ExactMatcher() : new BoundedMatcher($this->passes);
        $tables = [];
        $bye = null;

        foreach ($matcher->match($costs) as [$i, $j]) {
            if ($j === $count) {
                $bye = $ranked[$i]['participant'];

                continue;
            }

            $tables[] = $this->table($ranked[$i], $ranked[$j], \count($tables) + 1, $ranked, $records);
        }

        if (null !== $bye) {
            $kind = $records->of($bye)->byes() > 0 ? ReasonKind::ByeRepeated : ReasonKind::Bye;
            $tables[] = new Pairing(\count($tables) + 1, $bye, null, [new PairingReason($kind, $bye)]);
        }

        return $tables;
    }

    /**
     * The participants by points, then by their initial order, each with its place in its score group.
     *
     * @param list<string> $active
     *
     * @return list<array{participant: string, points: int, index: int, group: int, position: int}>
     */
    private function rank(array $active, Records $records): array
    {
        $order = array_flip($active);
        usort($active, static fn (string $a, string $b): int => [$records->points($b), $order[$a]] <=> [$records->points($a), $order[$b]]);
        $sizes = [];

        foreach ($active as $participant) {
            $points = $records->points($participant);
            $sizes[$points] = ($sizes[$points] ?? 0) + 1;
        }

        $ranked = [];
        $seen = [];

        foreach ($active as $index => $participant) {
            $points = $records->points($participant);
            $position = $seen[$points] ?? 0;
            $seen[$points] = $position + 1;
            $ranked[] = ['participant' => $participant, 'points' => $points, 'index' => $index, 'group' => $sizes[$points], 'position' => $position];
        }

        return $ranked;
    }

    /**
     * The cost of pairing two participants, or a participant with the bye when `$j` is past the last one.
     *
     * @param list<array{participant: string, points: int, index: int, group: int, position: int}> $ranked
     * @param list<array{met: array<string, list<int>>, byes: int, due: array{int, bool}}>         $facts  by rank
     */
    private function cost(array $ranked, array $facts, int $i, int $j): int
    {
        $a = $ranked[$i];
        $count = \count($ranked);

        if ($j === $count) {
            $last = $count - 1;

            return min($facts[$i]['byes'], self::MOST_REPEATS) * self::FORBIDDEN + $a['points'] * self::BYE_POINTS + ($last - $i);
        }

        $b = $ranked[$j];
        $cost = min(\count($facts[$i]['met'][$b['participant']] ?? []), self::MOST_REPEATS) * self::FORBIDDEN;
        $gap = $a['points'] - $b['points'];
        $cost += min($gap * $gap, self::MOST_GAP) * self::POINTS_GAP;
        [$wantA, $mustA] = $facts[$i]['due'];
        [$wantB, $mustB] = $facts[$j]['due'];

        if (0 !== $wantA && $wantA === $wantB) {
            $cost += $mustA && $mustB ? self::SIDE_MUST : self::SIDE_DUE;
        }

        if (0 === $gap) {
            return $cost + abs(abs($a['position'] - $b['position']) - intdiv($a['group'], 2)) * self::SPLIT;
        }

        return $cost + ($j - $i);
    }

    /**
     * The side a participant is due to play — 1 for first, -1 for second, 0 for either — and whether they must: their
     * sides are two apart, or their last two were the same.
     *
     * @return array{int, bool}
     */
    private static function due(Record $record): array
    {
        $sides = $record->sides();
        $balance = $record->sideBalance();
        $last = $sides[\count($sides) - 1] ?? null;
        $before = $sides[\count($sides) - 2] ?? null;
        $repeated = null !== $before && $before === $last;
        $must = abs($balance) >= 2 || $repeated;

        if ($balance > 0) {
            return [-1, $must];
        }

        if ($balance < 0) {
            return [1, $must];
        }

        if (null === $last) {
            return [0, $repeated];
        }

        return [$last ? -1 : 1, $repeated];
    }

    /**
     * A table of two participants, the higher ranked given first, with the side each plays and the reasons.
     *
     * @param array{participant: string, points: int, index: int, group: int, position: int}       $a
     * @param array{participant: string, points: int, index: int, group: int, position: int}       $b
     * @param list<array{participant: string, points: int, index: int, group: int, position: int}> $ranked
     */
    private function table(array $a, array $b, int $table, array $ranked, Records $records): Pairing
    {
        $one = $a['participant'];
        $two = $b['participant'];
        $scores = array_column($ranked, 'points', 'participant');
        [$first, $kind] = $this->sides($records->of($one), $records->of($two), $table);
        $second = $first === $one ? $two : $one;
        $reasons = [new PairingReason($kind, $first, $second)];
        $met = $records->of($one)->met()[$two] ?? [];

        if ([] !== $met) {
            $last = $met[\count($met) - 1];
            $reasons[] = new PairingReason(ReasonKind::Rematch, $one, $two, $last);
            $reasons[] = new PairingReason(ReasonKind::Rematch, $two, $one, $last);
        }

        if ($a['points'] !== $b['points']) {
            $reasons[] = new PairingReason(ReasonKind::Floated, $one, $two);
            $reasons[] = new PairingReason(ReasonKind::Floated, $two, $one);
        }

        foreach ([[$one, $a['points'], $two], [$two, $b['points'], $one]] as [$participant, $points, $partner]) {
            foreach ($records->of($participant)->met() as $opponent => $rounds) {
                if ($opponent !== $partner && ($scores[$opponent] ?? null) === $points) {
                    $reasons[] = new PairingReason(ReasonKind::RematchAvoided, $participant, $opponent, $rounds[\count($rounds) - 1]);
                }
            }
        }

        return new Pairing($table, $first, $second, $reasons);
    }

    /**
     * Who of the two plays first, and why: after a meeting, the one who played second then; else the one more due to
     * play first; else the one who played second in the latest round where their sides differ; else the higher ranked
     * gets their due side, or the first side on odd tables.
     *
     * @return array{string, ReasonKind}
     */
    private function sides(Record $a, Record $b, int $table): array
    {
        $last = $a->lastSideAgainst($b->participant);

        if (null !== $last) {
            return [$last ? $b->participant : $a->participant, ReasonKind::FirstPlayerAlternatedRematch];
        }

        [$wantA, $mustA] = self::due($a);
        [$wantB, $mustB] = self::due($b);
        $weightA = $wantA * ($mustA ? 2 : 1);
        $weightB = $wantB * ($mustB ? 2 : 1);

        if ($weightA > $weightB) {
            return [$a->participant, $this->kind($wantA)];
        }

        if ($weightB > $weightA) {
            return [$b->participant, $this->kind($wantB)];
        }

        $sidesA = $a->sides();
        $sidesB = $b->sides();

        for ($i = \count($sidesA) - 1, $j = \count($sidesB) - 1; $i >= 0 && $j >= 0; --$i, --$j) {
            if ($sidesA[$i] !== $sidesB[$j]) {
                return $sidesA[$i] ? [$b->participant, ReasonKind::FirstPlayerAlternated] : [$a->participant, ReasonKind::FirstPlayerAlternated];
            }
        }

        if ($wantA > 0) {
            return [$a->participant, ReasonKind::FirstPlayerAlternated];
        }

        if ($wantA < 0) {
            return [$b->participant, ReasonKind::FirstPlayerRepeated];
        }

        return [1 === $table % 2 ? $a->participant : $b->participant, ReasonKind::FirstPlayerByRank];
    }

    private function kind(int $want): ReasonKind
    {
        return $want < 0 ? ReasonKind::FirstPlayerRepeated : ReasonKind::FirstPlayerAlternated;
    }
}
