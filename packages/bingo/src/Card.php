<?php

declare(strict_types=1);

namespace Tilttek\Bingo;

use DateTimeImmutable;
use Tilttek\Bingo\Exception\InvalidBingo;

/**
 * A card: its serial in the game, the player who holds it — none for a card printed in a batch —, its layout — the square of each cell, row by row, null for the free
 * centre — drawn from the seed of the server, and, in a game ticked by activity, the cells ticked and the gesture that
 * ticked each. A cell stays ticked while one of its gestures is not cancelled. Only the server ticks a card; once it
 * won, it stays won.
 *
 * Each change gives the card its next version, which the store checks.
 */
final class Card
{
    /** The most gestures a cell remembers: beyond them, a cell already ticked keeps its first ones. */
    public const int MOST_KEYS = 16;

    /** @var array<int, list<string>> the idempotency keys of the gestures that ticked each cell, by cell */
    private array $ticks = [];

    private ?string $pattern = null;

    private ?DateTimeImmutable $wonAt = null;

    private int $version = 1;

    /**
     * @param list<string|null> $layout
     */
    public function __construct(
        public readonly string $game,
        public readonly int $serial,
        public readonly ?string $player,
        public readonly array $layout,
    ) {
        if (null !== $player) {
            Identifier::check($player, 'player');
        }

        if ($serial < 1) {
            throw InvalidBingo::because(\sprintf('The serial of a card starts at 1; %d is not one.', $serial));
        }
    }

    /**
     * The cells whose square it is.
     *
     * @return list<int>
     */
    public function cellsOf(string $square): array
    {
        return array_keys($this->layout, $square, true);
    }

    /**
     * Ticks the cells of the square, for the gesture of this idempotency key.
     *
     * @return list<int> the cells newly ticked
     */
    public function tick(string $square, string $key): array
    {
        $cells = $this->mark($square, $key);

        if (null !== $cells) {
            ++$this->version;
        }

        return $cells ?? [];
    }

    /**
     * Ticks the cells of several squares for the gesture of this idempotency key, in one change: the card takes one
     * version, however many squares the gesture ticks.
     *
     * @param list<string> $squares
     *
     * @return list<string> the squares whose cells were newly ticked
     */
    public function tickAll(array $squares, string $key): array
    {
        $ticked = [];
        $changed = false;

        foreach ($squares as $square) {
            $cells = $this->mark($square, $key);

            if (null === $cells) {
                continue;
            }

            $changed = true;

            if ([] !== $cells) {
                $ticked[] = $square;
            }
        }

        if ($changed) {
            ++$this->version;
        }

        return $ticked;
    }

    /**
     * Unticks the cells the gesture of this idempotency key ticked, once it is cancelled; a card that won keeps them.
     *
     * @return list<int> the cells unticked
     */
    public function untick(string $key): array
    {
        if ($this->hasWon()) {
            return [];
        }

        $unticked = [];
        $changed = false;

        foreach ($this->ticks as $cell => $keys) {
            if (!\in_array($key, $keys, true)) {
                continue;
            }

            $left = array_values(array_diff($keys, [$key]));
            $changed = true;

            if ([] === $left) {
                unset($this->ticks[$cell]);
                $unticked[] = $cell;
            } else {
                $this->ticks[$cell] = $left;
            }
        }

        if ($changed) {
            ++$this->version;
        }

        return $unticked;
    }

    /**
     * The cells ticked by gestures.
     *
     * @return array<int, list<string>> the idempotency keys of the gestures of each, by cell
     */
    public function ticks(): array
    {
        return $this->ticks;
    }

    /**
     * Adds the gesture's key to the cells of the square, without a new version.
     *
     * @return list<int>|null the cells newly ticked, or null when nothing changed
     */
    private function mark(string $square, string $key): ?array
    {
        $cells = $this->cellsOf($square);

        if ([] === $cells) {
            return null;
        }

        // Every cell of a square is ticked and unticked with the others: the first tells for all.
        $keys = $this->ticks[$cells[0]] ?? [];

        if (\in_array($key, $keys, true) || \count($keys) >= self::MOST_KEYS) {
            return null;
        }

        foreach ($cells as $cell) {
            $this->ticks[$cell] = [...$keys, $key];
        }

        return [] === $keys ? $cells : [];
    }

    public function win(string $pattern, DateTimeImmutable $at): void
    {
        if ($this->hasWon()) {
            return;
        }

        $this->pattern = $pattern;
        $this->wonAt = $at;
        ++$this->version;
    }

    /** Whether it was printed in a batch, held by no player the server knows. */
    public function isPrinted(): bool
    {
        return null === $this->player;
    }

    public function hasWon(): bool
    {
        return null !== $this->pattern;
    }

    /** The name of the pattern it won with, or null. */
    public function pattern(): ?string
    {
        return $this->pattern;
    }

    public function wonAt(): ?DateTimeImmutable
    {
        return $this->wonAt;
    }

    public function version(): int
    {
        return $this->version;
    }
}
