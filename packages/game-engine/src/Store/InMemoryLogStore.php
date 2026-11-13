<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Store;

use Tilttek\GameEngine\Contract\LogStore;
use Tilttek\GameEngine\Exception\ConcurrentMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\LoggedMove;
use Tilttek\GameEngine\LogHeader;

/**
 * The logs in the memory of the process, for tests and scripts.
 */
final class InMemoryLogStore implements LogStore
{
    /** @var array<string, GameLog> */
    private array $logs = [];

    /** @var array<string, int> */
    private array $revisions = [];

    public function create(GameLog $log): void
    {
        if (isset($this->logs[$log->gameId])) {
            throw InvalidGame::because(\sprintf('A table has the identifier "%s".', $log->gameId));
        }

        $this->logs[$log->gameId] = $log;
        $this->revisions[$log->gameId] = $log->sequence();
    }

    public function load(string $gameId): ?GameLog
    {
        return $this->logs[$gameId] ?? null;
    }

    public function header(string $gameId): ?LogHeader
    {
        return isset($this->logs[$gameId]) ? $this->logs[$gameId]->header($this->revisions[$gameId]) : null;
    }

    public function moves(string $gameId, int $from, int $to): iterable
    {
        $from = max(1, $from);

        return $from > $to ? [] : \array_slice($this->logs[$gameId]->moves ?? [], $from - 1, $to - $from + 1);
    }

    public function append(LogHeader $read, LoggedMove $move): void
    {
        $this->logs[$read->gameId] = $this->expect($read)->with($move);
        ++$this->revisions[$read->gameId];
    }

    public function removeLast(LogHeader $read): void
    {
        $log = $this->expect($read);

        if (0 === $log->sequence()) {
            throw InvalidGame::because(\sprintf('The table "%s" has no move to take back.', $read->gameId));
        }

        $this->logs[$read->gameId] = $log->withoutLast();
        ++$this->revisions[$read->gameId];
    }

    /**
     * Forgets every log, as a worker does between two requests when this store serves tests.
     */
    public function reset(): void
    {
        $this->logs = [];
        $this->revisions = [];
    }

    private function expect(LogHeader $read): GameLog
    {
        $log = $this->logs[$read->gameId] ?? throw InvalidGame::because(\sprintf('No table has the identifier "%s".', $read->gameId));

        if ($log->sequence() !== $read->sequence) {
            throw ConcurrentMove::at($read->gameId, $read->sequence, $log->sequence());
        }

        if ($this->revisions[$read->gameId] !== $read->revision) {
            throw ConcurrentMove::revised($read->gameId, $read->revision, $this->revisions[$read->gameId]);
        }

        return $log;
    }
}
