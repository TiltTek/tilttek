<?php

declare(strict_types=1);

namespace Tilttek\Bingo;

use Tilttek\Bingo\Exception\BingoRefused;
use Tilttek\Bingo\Exception\InvalidBingo;

/**
 * A bingo game: the pool its cards are drawn from, their grid, the ways to win and what ticks the squares. It counts
 * the cards dealt, keeps the squares called and the cards that won, in order, and is open until it is closed.
 *
 * Each change gives the game its next version, which the store checks.
 */
final class BingoGame
{
    /** The most cards a game deals. */
    public const int MOST_CARDS = 100_000;

    private bool $open = true;

    private int $dealt = 0;

    /** @var array<string, string> the squares called, by themselves, in the order called */
    private array $called = [];

    /** @var list<int> the serials of the cards that won, in order */
    private array $winners = [];

    private int $version = 1;

    public function __construct(
        public readonly string $id,
        public readonly SquarePool $pool,
        public readonly Grid $grid,
        public readonly WinPatterns $patterns,
        public readonly TickMode $mode = TickMode::Activity,
    ) {
        Identifier::check($id, 'game');

        if ($pool->count() < $grid->squares()) {
            throw InvalidBingo::because(\sprintf('A card of the game %s holds %d squares; its pool holds %d.', $id, $grid->squares(), $pool->count()));
        }
    }

    /**
     * The serial of the next card, from 1.
     */
    public function nextSerial(): int
    {
        $this->checkOpen();

        if ($this->dealt >= self::MOST_CARDS) {
            throw BingoRefused::because(\sprintf('The bingo game %s dealt %d cards, the most it deals.', $this->id, self::MOST_CARDS));
        }

        ++$this->dealt;
        ++$this->version;

        return $this->dealt;
    }

    /**
     * Reserves the serials of `$count` cards at once, for a batch to print.
     *
     * @return int the first serial reserved; the others follow it
     */
    public function nextSerials(int $count): int
    {
        $this->checkOpen();

        if ($count < 1) {
            throw InvalidBingo::because(\sprintf('A batch of serials holds at least one; %d asked.', $count));
        }

        if ($this->dealt + $count > self::MOST_CARDS) {
            throw BingoRefused::because(\sprintf('The bingo game %s dealt %d cards; %d more would pass the %d it deals.', $this->id, $this->dealt, $count, self::MOST_CARDS));
        }

        $first = $this->dealt + 1;
        $this->dealt += $count;
        ++$this->version;

        return $first;
    }

    /**
     * Calls a square for every card, in a game whose squares are called.
     *
     * @return bool whether the square was not called yet
     */
    public function call(string $square): bool
    {
        $this->checkOpen();

        if (TickMode::Called !== $this->mode) {
            throw BingoRefused::because(\sprintf('The squares of the bingo game %s are ticked by activity, never called.', $this->id));
        }

        $this->pool->get($square);

        if (isset($this->called[$square])) {
            return false;
        }

        $this->called[$square] = $square;
        ++$this->version;

        return true;
    }

    public function isCalled(string $square): bool
    {
        return isset($this->called[$square]);
    }

    /**
     * @return list<string> in the order called
     */
    public function called(): array
    {
        return array_values($this->called);
    }

    /**
     * Records that a card won; a card wins once.
     */
    public function won(int $serial): void
    {
        if (\in_array($serial, $this->winners, true)) {
            return;
        }

        $this->winners[] = $serial;
        ++$this->version;
    }

    /**
     * @return list<int> the serials of the cards that won, in order
     */
    public function winners(): array
    {
        return $this->winners;
    }

    public function close(): void
    {
        $this->checkOpen();
        $this->open = false;
        ++$this->version;
    }

    public function isOpen(): bool
    {
        return $this->open;
    }

    public function dealt(): int
    {
        return $this->dealt;
    }

    public function version(): int
    {
        return $this->version;
    }

    private function checkOpen(): void
    {
        if (!$this->open) {
            throw BingoRefused::closed($this->id);
        }
    }
}
