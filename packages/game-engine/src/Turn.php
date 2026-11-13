<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * Whose turn it is in the current phase: the round, from 1, the order of the seats, the seat acting, the seats still to
 * act in a simultaneous round, and the moves made in the current turn.
 */
final readonly class Turn
{
    /**
     * @param list<string> $order   the seats in the order they act
     * @param int          $current the index in `order` of the seat acting
     * @param list<string> $waiting the seats that have not ended their turn, in a simultaneous round
     * @param int          $moves   the moves the acting seat has made in its turn
     */
    public function __construct(
        public TurnOrder $kind,
        public int $round,
        public array $order,
        public int $current = 0,
        public array $waiting = [],
        public int $moves = 0,
    ) {
    }

    /**
     * The first turn of a phase.
     *
     * @param list<string>       $seats      in the order of the table
     * @param array<string, int> $initiative the initiative of the seats that have one
     */
    public static function start(TurnOrder $kind, array $seats, array $initiative): self
    {
        return match ($kind) {
            TurnOrder::Initiative => new self($kind, 1, self::byInitiative($seats, $initiative)),
            TurnOrder::Simultaneous => new self($kind, 1, $seats, waiting: $seats),
            TurnOrder::Free, TurnOrder::Sequential => new self($kind, 1, $seats),
        };
    }

    /**
     * The seats that may act now: every seat in a free phase, the waiting seats in a simultaneous round, the acting seat
     * otherwise.
     *
     * @return list<string>
     */
    public function toAct(): array
    {
        return match ($this->kind) {
            TurnOrder::Free => $this->order,
            TurnOrder::Simultaneous => $this->waiting,
            TurnOrder::Sequential, TurnOrder::Initiative => [$this->order[$this->current]],
        };
    }

    /**
     * Whether a seat may act now; the table itself, a null seat, always may.
     */
    public function allows(?string $seat): bool
    {
        return null === $seat || \in_array($seat, $this->toAct(), true);
    }

    /**
     * The turn after a seat ends its own; the table itself ends the acting seat's turn, or a simultaneous round.
     */
    public function end(?string $seat): self
    {
        if (TurnOrder::Simultaneous === $this->kind) {
            $waiting = null === $seat ? [] : array_values(array_diff($this->waiting, [$seat]));

            return [] === $waiting
                ? new self($this->kind, $this->round + 1, $this->order, waiting: $this->order)
                : new self($this->kind, $this->round, $this->order, waiting: $waiting);
        }

        $next = $this->current + 1;

        return $next < \count($this->order)
            ? new self($this->kind, $this->round, $this->order, $next)
            : new self($this->kind, $this->round + 1, $this->order);
    }

    /**
     * The turn after a move of the game, ended by itself when the phase ends a turn after that many moves.
     */
    public function counted(?string $seat, ?int $movesPerTurn): self
    {
        if (TurnOrder::Free === $this->kind) {
            return $this;
        }

        $turn = new self($this->kind, $this->round, $this->order, $this->current, $this->waiting, $this->moves + 1);

        return null !== $movesPerTurn && $turn->moves >= $movesPerTurn ? $this->end($seat) : $turn;
    }

    /**
     * The round ordered again by initiative, from its first seat; the round number is kept.
     *
     * @param list<string>       $seats
     * @param array<string, int> $initiative
     */
    public function reordered(array $seats, array $initiative): self
    {
        return new self($this->kind, $this->round, self::byInitiative($seats, $initiative));
    }

    /**
     * The acting seat moved after another later in the order; the seat that followed it acts.
     */
    public function delayed(string $after): ?self
    {
        $seat = $this->order[$this->current];
        $target = array_search($after, $this->order, true);

        if (!\is_int($target) || $target <= $this->current) {
            return null;
        }

        $order = $this->order;
        array_splice($order, $target + 1, 0, [$seat]);
        array_splice($order, $this->current, 1);

        return new self($this->kind, $this->round, $order, $this->current);
    }

    /**
     * The turn as plain values, which `fromArray()` reads back.
     *
     * @return array{kind: string, round: int, order: list<string>, current: int, waiting: list<string>, moves: int}
     */
    public function toArray(): array
    {
        return ['kind' => $this->kind->value, 'round' => $this->round, 'order' => $this->order, 'current' => $this->current, 'waiting' => $this->waiting, 'moves' => $this->moves];
    }

    /**
     * A turn read back from the plain values `toArray()` wrote: a known turn order, a round from 1, a seat acting among
     * the order, and waiting seats among it.
     *
     * @param array<mixed> $data
     *
     * @throws InvalidGame
     */
    public static function fromArray(array $data): self
    {
        $of = 'a turn';
        $kind = TurnOrder::tryFrom(Fields::text($data, 'kind', $of)) ?? throw InvalidGame::because("The turn order of a turn is not one of the engine's.");
        $order = Fields::texts($data, 'order', $of);
        $current = Fields::count($data, 'current', $of);
        $waiting = Fields::texts($data, 'waiting', $of);
        $round = Fields::count($data, 'round', $of);

        if ($round < 1 || ([] !== $order && $current >= \count($order)) || ([] === $order && 0 !== $current) || [] !== array_diff($waiting, $order)) {
            throw InvalidGame::because('A turn has a round from 1, a seat acting among its order, and waiting seats among it.');
        }

        return new self($kind, $round, $order, $current, $waiting, Fields::count($data, 'moves', $of));
    }

    /**
     * The seats by initiative, the highest first; equal scores in the order of the table, and the seats without a score
     * after the others, in the order of the table.
     *
     * @param list<string>       $seats
     * @param array<string, int> $initiative
     *
     * @return list<string>
     */
    private static function byInitiative(array $seats, array $initiative): array
    {
        $ranked = $seats;
        usort($ranked, static fn (string $a, string $b): int => [isset($initiative[$b]), $initiative[$b] ?? 0, array_search($a, $seats, true)]
            <=> [isset($initiative[$a]), $initiative[$a] ?? 0, array_search($b, $seats, true)]);

        return $ranked;
    }
}
