<?php

declare(strict_types=1);

namespace Tilttek\Collections\Daily;

/**
 * Where the calendar of the item of the day is kept once generated or chosen, one pick per day.
 */
interface DailyStore
{
    /**
     * Keeps a pick, in place of the one of its day.
     */
    public function save(DailyPick $pick): void;

    public function find(string $day): ?DailyPick;

    /**
     * The picks from one day to another, both included, by day.
     *
     * @return list<DailyPick>
     */
    public function between(string $from, string $until): array;

    /**
     * Removes the pick of a day; returns whether there was one.
     */
    public function remove(string $day): bool;
}
