<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * The truth of a table: the game and the version of its rules, the seed of its chance, its seats and options, and the
 * moves made, in order. The state is rebuilt from it by replaying the moves; it is what a store keeps.
 */
final readonly class GameLog
{
    /** The most moves a log holds. */
    public const int MAX_MOVES = 100_000;

    private const string OF = 'a log';

    /**
     * @param list<string>                   $seats   in the order of the table
     * @param array<string, bool|int|string> $options the options chosen for the table
     * @param list<LoggedMove>               $moves   numbered from 1, in order
     *
     * @throws InvalidGame
     */
    public function __construct(
        public string $gameId,
        public string $game,
        public string $version,
        public string $seed,
        public array $seats,
        public array $options = [],
        public array $moves = [],
    ) {
        Name::identifier($gameId, 'a table');
        Name::check($game, 'a game');
        Randomness::check($seed);

        if (\count($moves) > self::MAX_MOVES) {
            throw InvalidGame::because(\sprintf('The log of the table "%s" holds more than %d moves.', $gameId, self::MAX_MOVES));
        }

        foreach ($moves as $index => $move) {
            if ($move->sequence !== $index + 1) {
                throw InvalidGame::because(\sprintf('The move %d of the log of the table "%s" is numbered %d.', $index + 1, $gameId, $move->sequence));
            }
        }
    }

    /**
     * The commitment to the seed: its SHA-256 digest, which the players may be shown when the table opens, while the
     * seed itself stays secret; once the game is over, the log shows the seed, and anyone replays it to check that every
     * draw came from the seed committed to.
     */
    public function commitment(): string
    {
        return Randomness::commitment($this->seed);
    }

    /**
     * The number of moves made.
     */
    public function sequence(): int
    {
        return \count($this->moves);
    }

    /**
     * The move at a place of the log, from 1.
     *
     * @throws InvalidGame when the log holds no such move
     */
    public function move(int $sequence): LoggedMove
    {
        return $this->moves[$sequence - 1] ?? throw InvalidGame::because(\sprintf('The log of the table "%s" has no move %d.', $this->gameId, $sequence));
    }

    public function last(): ?LoggedMove
    {
        return array_last($this->moves);
    }

    /**
     * What the log holds besides its moves, at a revision: its number of moves when the store keeps no other.
     *
     * @throws InvalidGame when the revision is lower than the number of moves
     */
    public function header(?int $revision = null): LogHeader
    {
        return new LogHeader($this->gameId, $this->game, $this->version, $this->seed, $this->seats, $this->options, $this->sequence(), $revision ?? $this->sequence());
    }

    /**
     * This log with one more move.
     */
    public function with(LoggedMove $move): self
    {
        return new self($this->gameId, $this->game, $this->version, $this->seed, $this->seats, $this->options, [...$this->moves, $move]);
    }

    /**
     * This log without its last move.
     */
    public function withoutLast(): self
    {
        return new self($this->gameId, $this->game, $this->version, $this->seed, $this->seats, $this->options, \array_slice($this->moves, 0, -1));
    }

    /**
     * The log as plain values, which JSON writes and `fromArray()` reads back.
     *
     * @return array{game_id: string, game: string, version: string, seed: string, seats: list<string>, options: array<string, bool|int|string>, moves: list<array{type: string, seat: ?string, payload: array<string, mixed>, drew: bool}>}
     */
    public function toArray(): array
    {
        return [
            'game_id' => $this->gameId,
            'game' => $this->game,
            'version' => $this->version,
            'seed' => $this->seed,
            'seats' => $this->seats,
            'options' => $this->options,
            'moves' => array_map(static fn (LoggedMove $logged): array => [
                'type' => $logged->move->type,
                'seat' => $logged->move->seat,
                'payload' => $logged->move->payload,
                'drew' => $logged->drew,
            ], $this->moves),
        ];
    }

    /**
     * A log read back from the plain values `toArray()` wrote.
     *
     * @param array<mixed> $data
     *
     * @throws InvalidGame
     */
    public static function fromArray(array $data): self
    {
        $moves = [];

        foreach (Fields::list($data, 'moves', self::OF) as $index => $move) {
            if (!\is_array($move)) {
                throw InvalidGame::because(\sprintf('The move %d of a log is not a mapping.', $index + 1));
            }

            $seat = $move['seat'] ?? null;
            $moves[] = new LoggedMove($index + 1, new Move(Fields::text($move, 'type', self::OF), null === $seat ? null : Fields::text($move, 'seat', self::OF), Fields::map($move, 'payload', self::OF)), true === ($move['drew'] ?? false));
        }

        $options = [];

        foreach (Fields::map($data, 'options', self::OF) as $name => $value) {
            $options[$name] = \is_bool($value) || \is_int($value) || \is_string($value) ? $value : throw InvalidGame::because(\sprintf('The option "%s" of a log is not a boolean, a whole number or a text.', $name));
        }

        return new self(
            Fields::text($data, 'game_id', self::OF),
            Fields::text($data, 'game', self::OF),
            Fields::text($data, 'version', self::OF),
            Fields::text($data, 'seed', self::OF),
            Fields::texts($data, 'seats', self::OF),
            $options,
            $moves,
        );
    }
}
