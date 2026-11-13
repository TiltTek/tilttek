<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Tests\Unit\Fixture;

use Closure;
use Tilttek\GameEngine\Contract\LogStore;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\LoggedMove;
use Tilttek\GameEngine\LogHeader;
use Tilttek\GameEngine\Store\InMemoryLogStore;

/**
 * A log store in memory that counts what it is asked for, and runs a change of the log while moves are read — as
 * another process would between two windows.
 */
final class CountingLogStore implements LogStore
{
    public int $loads = 0;

    public int $headers = 0;

    public int $read = 0;

    /** @var list<array{int, int}> */
    public array $windows = [];

    /** @var ?Closure(): void runs before the first move a read gives — not within itself —, then is let go unless it is kept */
    public ?Closure $whileReading = null;

    public bool $keep = false;

    /** @var ?Closure(): void runs once before the next whole log is loaded */
    public ?Closure $beforeLoading = null;

    public function __construct(
        public readonly InMemoryLogStore $inner = new InMemoryLogStore(),
    ) {
    }

    public function create(GameLog $log): void
    {
        $this->inner->create($log);
    }

    public function load(string $gameId): ?GameLog
    {
        ++$this->loads;
        $change = $this->beforeLoading;
        $this->beforeLoading = null;

        if ($change instanceof Closure) {
            $change();
        }

        return $this->inner->load($gameId);
    }

    public function header(string $gameId): ?LogHeader
    {
        ++$this->headers;

        return $this->inner->header($gameId);
    }

    public function moves(string $gameId, int $from, int $to): iterable
    {
        $this->windows[] = [$from, $to];
        $moves = $this->inner->moves($gameId, $from, $to);
        $change = $this->whileReading;

        if ($change instanceof Closure) {
            $this->whileReading = null;
            $change();
            $this->whileReading = $this->keep ? $change : null;
        }

        foreach ($moves as $move) {
            ++$this->read;

            yield $move;
        }
    }

    public function append(LogHeader $read, LoggedMove $move): void
    {
        $this->inner->append($read, $move);
    }

    public function removeLast(LogHeader $read): void
    {
        $this->inner->removeLast($read);
    }
}
