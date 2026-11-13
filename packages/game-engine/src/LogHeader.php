<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Tilttek\GameEngine\Exception\InvalidGame;

/**
 * What a log holds besides its moves: the game and the version of its rules, the seed, the seats and options, the number
 * of moves made and the revision of the log — read in one go, without a move, to tell whether a cached state is still
 * the one the log leads to.
 *
 * The revision counts every change of the log, each move appended and each move taken back, and never goes down: two
 * reads of one revision read the same moves. Its difference with the number of moves, the lineage, changes only when a
 * move is taken back, so that two headers of one lineage share every move up to the shorter of the two.
 */
final readonly class LogHeader
{
    /**
     * @param list<string>                   $seats    in the order of the table
     * @param array<string, bool|int|string> $options  the options chosen for the table
     * @param int                            $sequence the number of moves made
     * @param int                            $revision the changes of the log, never fewer than its moves
     *
     * @throws InvalidGame
     */
    public function __construct(
        public string $gameId,
        public string $game,
        public string $version,
        public string $seed,
        public array $seats,
        public array $options,
        public int $sequence,
        public int $revision,
    ) {
        Name::identifier($gameId, 'a table');
        Name::check($game, 'a game');
        Randomness::check($seed);

        if ($sequence < 0 || $sequence > GameLog::MAX_MOVES) {
            throw InvalidGame::because(\sprintf('The log of the table "%s" holds %d moves, not 0 to %d.', $gameId, $sequence, GameLog::MAX_MOVES));
        }

        if ($revision < $sequence) {
            throw InvalidGame::because(\sprintf('The log of the table "%s" is at the revision %d, fewer than its %d moves.', $gameId, $revision, $sequence));
        }
    }

    /**
     * The commitment to the seed, `GameLog::commitment()`, read without the moves.
     */
    public function commitment(): string
    {
        return Randomness::commitment($this->seed);
    }

    /**
     * The changes of the log that were not moves appended: twice the moves taken back. It stays the same as long as no
     * move is taken back.
     */
    public function lineage(): int
    {
        return $this->revision - $this->sequence;
    }

    /**
     * The header once one more move is appended.
     */
    public function appended(): self
    {
        return new self($this->gameId, $this->game, $this->version, $this->seed, $this->seats, $this->options, $this->sequence + 1, $this->revision + 1);
    }

    /**
     * The header once the last move is taken back.
     *
     * @throws InvalidGame when the log holds no move
     */
    public function withoutLast(): self
    {
        return new self($this->gameId, $this->game, $this->version, $this->seed, $this->seats, $this->options, $this->sequence - 1, $this->revision + 1);
    }
}
