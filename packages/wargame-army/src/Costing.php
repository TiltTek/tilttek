<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy;

use Tilttek\WargameArmy\Constraint\Selector;

/**
 * The points of an army list against its catalogue: each unit, each detachment and the whole list. A unit or an
 * option the catalogue does not hold costs nothing; the validator reports it.
 */
final readonly class Costing
{
    /**
     * @param list<list<int>>          $units       the points of each unit, by detachment
     * @param list<int>                $detachments the points of each detachment
     * @param list<array<string, int>> $counts      the number of each unit the catalogue holds, by detachment
     * @param array<string, int>       $spent       the points of each unit the catalogue holds, in the army
     */
    private function __construct(
        public array $units,
        public array $detachments,
        public int $total,
        private array $counts,
        private array $spent,
    ) {
    }

    public static function of(ArmyList $list, Catalogue $catalogue): self
    {
        $units = [];
        $detachments = [];
        $counts = [];
        $spent = [];

        foreach ($list->detachments as $detachment) {
            $costs = [];
            $count = [];

            foreach ($detachment->units as $selection) {
                $entry = $catalogue->unit($selection->unit);

                if (!$entry instanceof UnitEntry) {
                    $costs[] = 0;

                    continue;
                }

                $cost = self::unitCost($entry, $selection);
                $costs[] = $cost;
                $count[$entry->code] = ($count[$entry->code] ?? 0) + 1;
                $spent[$entry->code] = ($spent[$entry->code] ?? 0) + $cost;
            }

            $units[] = $costs;
            $detachments[] = array_sum($costs);
            $counts[] = $count;
        }

        return new self($units, $detachments, array_sum($detachments), $counts, $spent);
    }

    /**
     * The points of a unit: its own for its fewest models, those of each model beyond them, and those of its options.
     */
    public static function unitCost(UnitEntry $entry, UnitSelection $selection): int
    {
        $cost = $entry->points + max(0, $selection->models - $entry->minModels) * $entry->pointsPerModel;

        foreach ($selection->options as $code => $count) {
            $cost += $entry->option((string) $code)?->cost($count, $selection->models) ?? 0;
        }

        return $cost;
    }

    /**
     * The points of the unit at that position of that detachment, both from 0; 0 when there is none.
     */
    public function unit(int $detachment, int $position): int
    {
        return $this->units[$detachment][$position] ?? 0;
    }

    /**
     * The points of the detachment at that position, from 0; 0 when there is none.
     */
    public function detachment(int $detachment): int
    {
        return $this->detachments[$detachment] ?? 0;
    }

    /**
     * The number of units the selector takes, in the army or in the detachment at that position, from 0.
     */
    public function count(Selector $selector, Catalogue $catalogue, ?int $detachment = null): int
    {
        $counts = null === $detachment ? $this->counts : [$this->counts[$detachment] ?? []];
        $count = 0;

        foreach ($counts as $units) {
            $count += $this->sum($units, $selector, $catalogue);
        }

        return $count;
    }

    /**
     * The points the units the selector takes cost in the army.
     */
    public function points(Selector $selector, Catalogue $catalogue): int
    {
        return $this->sum($this->spent, $selector, $catalogue);
    }

    /**
     * @param array<string, int> $byUnit
     */
    private function sum(array $byUnit, Selector $selector, Catalogue $catalogue): int
    {
        if (null !== $selector->unit) {
            return $byUnit[$selector->unit] ?? 0;
        }

        $sum = 0;

        foreach ($byUnit as $code => $value) {
            $unit = $catalogue->unit(Identifiers::key($code));

            if ($unit instanceof UnitEntry && $selector->matches($unit)) {
                $sum += $value;
            }
        }

        return $sum;
    }
}
