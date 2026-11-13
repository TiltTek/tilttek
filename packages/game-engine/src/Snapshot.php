<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * A state as a cache keeps it: the state, the revision of the log it was built from and the commitment to the seed of
 * its table. A cached state is used only when its revision and commitment are those of the log, so that a state built
 * from moves since taken back, or from an earlier table of the same identifier, is never served.
 */
final readonly class Snapshot
{
    /**
     * @param int    $revision   the revision of the log the state reflects, `LogHeader::$revision`
     * @param string $commitment the commitment to the seed of the table, `LogHeader::commitment()`
     *
     * @throws InvalidGame
     */
    public function __construct(
        public GameState $state,
        public int $revision,
        public string $commitment,
    ) {
        if ($revision < $state->sequence) {
            throw InvalidGame::because(\sprintf('The state of the table "%s" is at the revision %d, fewer than its %d moves.', $state->gameId, $revision, $state->sequence));
        }

        if (1 !== preg_match('/^[0-9a-f]{64}$/D', $commitment)) {
            throw InvalidGame::because('The commitment of a cached state is 64 lowercase hexadecimal digits.');
        }
    }

    /**
     * The snapshot of a state and the header of the log it reflects.
     *
     * @throws InvalidGame when the state is not of that table, or not after every move of the log
     */
    public static function of(GameState $state, LogHeader $header): self
    {
        if ($state->gameId !== $header->gameId || $state->sequence !== $header->sequence) {
            throw InvalidGame::because(\sprintf('The state of the table "%s" after %d moves is not the one of the log of the table "%s" after %d moves.', $state->gameId, $state->sequence, $header->gameId, $header->sequence));
        }

        return new self($state, $header->revision, $header->commitment());
    }

    /**
     * Whether the state is the one the log leads to: same table, game, seed, revision and moves.
     */
    public function reflects(LogHeader $header): bool
    {
        return $this->revision === $header->revision && $this->state->sequence === $header->sequence && $this->sameTable($header);
    }

    /**
     * Whether the state is one the log went through, so that the moves made since bring it up to date: same table, game
     * and seed, no move taken back since, and fewer moves.
     */
    public function precedes(LogHeader $header): bool
    {
        return $this->state->sequence < $header->sequence && $this->revision - $this->state->sequence === $header->lineage() && $this->sameTable($header);
    }

    /**
     * Whether this snapshot is of a later revision of the same table than another, which a cache then keeps.
     */
    public function supersedes(self $other): bool
    {
        return $this->state->gameId === $other->state->gameId && $this->commitment === $other->commitment && $this->revision > $other->revision;
    }

    /**
     * The snapshot as plain values, which a shared cache keeps and `fromArray()` reads back.
     *
     * @return array{revision: int, commitment: string, state: array<string, mixed>}
     */
    public function toArray(): array
    {
        return ['revision' => $this->revision, 'commitment' => $this->commitment, 'state' => $this->state->toArray()];
    }

    /**
     * A snapshot read back from the plain values `toArray()` wrote, each field checked.
     *
     * @param array<mixed> $data
     *
     * @throws InvalidGame
     */
    public static function fromArray(array $data): self
    {
        $of = 'a cached state';

        return new self(GameState::fromArray(Fields::map($data, 'state', $of)), Fields::count($data, 'revision', $of), Fields::text($data, 'commitment', $of));
    }

    private function sameTable(LogHeader $header): bool
    {
        return $this->state->gameId === $header->gameId && $this->state->game === $header->game && hash_equals($header->commitment(), $this->commitment);
    }
}
