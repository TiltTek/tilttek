<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * The state of a table after the moves of its log: a cache the engine rebuilds by replaying the log, never the truth.
 * It holds the phase, the turn, the initiative of the seats, the game's own data and, once the game is over, its
 * outcome.
 */
final readonly class GameState
{
    /**
     * @param int                  $sequence   the moves of the log it reflects
     * @param list<string>         $seats      in the order of the table
     * @param array<string, int>   $initiative the initiative of the seats that have one
     * @param array<string, mixed> $data       the game's own data, which only the game reads and writes
     */
    public function __construct(
        public string $gameId,
        public string $game,
        public int $sequence,
        public array $seats,
        public TableOptions $options,
        public string $phase,
        public Turn $turn,
        public array $initiative = [],
        public array $data = [],
        public ?Outcome $outcome = null,
    ) {
    }

    /**
     * The state as plain values — the game's data as the game wrote it —, which a shared cache keeps and `fromArray()`
     * reads back.
     *
     * @return array{game_id: string, game: string, sequence: int, seats: list<string>, options: array<string, bool|int|string>, phase: string, turn: array{kind: string, round: int, order: list<string>, current: int, waiting: list<string>, moves: int}, initiative: array<string, int>, data: array<string, mixed>, outcome: ?array{winners: list<string>, reason: string, statistics: array{table: array<string, int>, seats: array<string, array<string, int>>}}}
     */
    public function toArray(): array
    {
        return [
            'game_id' => $this->gameId,
            'game' => $this->game,
            'sequence' => $this->sequence,
            'seats' => $this->seats,
            'options' => $this->options->values,
            'phase' => $this->phase,
            'turn' => $this->turn->toArray(),
            'initiative' => $this->initiative,
            'data' => $this->data,
            'outcome' => $this->outcome?->toArray(),
        ];
    }

    /**
     * A state read back from the plain values `toArray()` wrote, each field checked for its type: a cache that gives
     * back anything else is refused with `InvalidGame`, and the log is replayed instead.
     *
     * @param array<mixed> $data
     *
     * @throws InvalidGame
     */
    public static function fromArray(array $data): self
    {
        $of = 'a state';
        $turn = Fields::map($data, 'turn', $of);
        $outcome = $data['outcome'] ?? null;

        if (null !== $outcome && !\is_array($outcome)) {
            throw InvalidGame::because('The field "outcome" of a state is not a mapping.');
        }

        return new self(
            Name::identifier(Fields::text($data, 'game_id', $of), 'a table'),
            Name::check(Fields::text($data, 'game', $of), 'a game'),
            Fields::count($data, 'sequence', $of),
            Fields::texts($data, 'seats', $of),
            TableOptions::read(Fields::map($data, 'options', $of)),
            Fields::text($data, 'phase', $of),
            Turn::fromArray($turn),
            Fields::numbers($data, 'initiative', $of),
            Fields::map($data, 'data', $of),
            null === $outcome ? null : Outcome::fromArray($outcome),
        );
    }

    public function isOver(): bool
    {
        return $this->outcome instanceof Outcome;
    }

    /**
     * @param array<string, mixed> $data
     */
    public function withData(array $data): self
    {
        return new self($this->gameId, $this->game, $this->sequence, $this->seats, $this->options, $this->phase, $this->turn, $this->initiative, $data, $this->outcome);
    }

    /**
     * @param array<string, int> $initiative
     */
    public function with(int $sequence, string $phase, Turn $turn, array $initiative, ?Outcome $outcome = null): self
    {
        return new self($this->gameId, $this->game, $sequence, $this->seats, $this->options, $phase, $turn, $initiative, $this->data, $outcome);
    }
}
