<?php

declare(strict_types=1);

namespace Tilttek\TavernGames;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * Whose turn it is at a tavern table, kept in the game's own data: the seats in the order they play, and the seat
 * playing. A tavern game decides when a turn ends — a die that shows 1, a card played — so its phase leaves the turns
 * to the game rather than to the engine.
 */
final readonly class Turns
{
    /**
     * @param non-empty-list<string> $order
     */
    private function __construct(
        public array $order,
        public int $current,
    ) {
    }

    /**
     * The first turn: the first seat of the table plays.
     *
     * @param list<string> $seats
     *
     * @throws InvalidGame when there is no seat
     */
    public static function start(array $seats): self
    {
        if ([] === $seats) {
            throw InvalidGame::because('The turns of a tavern game need a seat.');
        }

        return new self($seats, 0);
    }

    /**
     * The turns read back from what `toData()` wrote.
     *
     * @throws InvalidGame when the data is not seats and the place of the one playing
     */
    public static function fromData(mixed $data): self
    {
        $order = \is_array($data) ? $data['order'] ?? null : null;
        $current = \is_array($data) ? $data['current'] ?? null : null;

        if (!\is_array($order) || [] === $order || !\is_int($current) || $current < 0 || $current >= \count($order)) {
            throw InvalidGame::because('The turns of a tavern game are seats and the place of the one playing.');
        }

        $seats = [];

        foreach ($order as $seat) {
            $seats[] = \is_string($seat) ? $seat : throw InvalidGame::because('A seat of the turns of a tavern game is a text.');
        }

        return new self($seats, $current);
    }

    /**
     * @return array{order: non-empty-list<string>, current: int}
     */
    public function toData(): array
    {
        return ['order' => $this->order, 'current' => $this->current];
    }

    /**
     * The seat playing.
     */
    public function seat(): string
    {
        return $this->order[$this->current];
    }

    public function isTurnOf(?string $seat): bool
    {
        return $seat === $this->seat();
    }

    /**
     * The turn of the next seat, the first one after the last.
     */
    public function next(): self
    {
        return new self($this->order, ($this->current + 1) % \count($this->order));
    }

    /**
     * The place of a seat in the order, from 0.
     *
     * @throws InvalidGame when the seat is not at the table
     */
    public function placeOf(string $seat): int
    {
        $place = array_search($seat, $this->order, true);

        return \is_int($place) ? $place : throw InvalidGame::because(\sprintf('The seat "%s" is not at the table.', $seat));
    }
}
