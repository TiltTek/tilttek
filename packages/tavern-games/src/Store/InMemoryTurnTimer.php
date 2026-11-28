<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Store;

use Tilttek\TavernGames\Contract\TurnTimer;

/**
 * The turns running, in memory: for tests.
 */
final class InMemoryTurnTimer implements TurnTimer
{
    /** @var array<string, array{seat: string, sequence: int}> */
    private array $running = [];

    public function started(string $tableId, string $seat, int $sequence): void
    {
        $this->running[$tableId] = ['seat' => $seat, 'sequence' => $sequence];
    }

    public function stopped(string $tableId): void
    {
        unset($this->running[$tableId]);
    }

    /**
     * The turn running at a table, or null.
     *
     * @return ?array{seat: string, sequence: int}
     */
    public function running(string $tableId): ?array
    {
        return $this->running[$tableId] ?? null;
    }
}
