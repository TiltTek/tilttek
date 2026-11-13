<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

/**
 * The statistics of a game that ended, as its outcome reports them: whole numbers for the table, and for each seat. The
 * engine checks them against the statistics the game declares.
 */
final readonly class Statistics
{
    /**
     * @param array<string, int>                $table the statistics of the table, by name
     * @param array<string, array<string, int>> $seats the statistics of each seat, by seat, then by name
     */
    public function __construct(
        public array $table = [],
        public array $seats = [],
    ) {
    }

    /**
     * A statistic of the table, or null when the game did not report it.
     */
    public function ofTable(string $name): ?int
    {
        return $this->table[$name] ?? null;
    }

    /**
     * A statistic of one seat, or null when the game did not report it.
     */
    public function ofSeat(string $seat, string $name): ?int
    {
        return $this->seats[$seat][$name] ?? null;
    }

    public function isEmpty(): bool
    {
        return [] === $this->table && [] === $this->seats;
    }
}
