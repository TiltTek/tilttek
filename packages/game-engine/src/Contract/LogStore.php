<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Contract;

use Tilttek\GameEngine\Exception\ConcurrentMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\LoggedMove;
use Tilttek\GameEngine\LogHeader;

/**
 * Where the logs of the tables are kept: the truth of every table. Its header is read without the moves, and the moves
 * window by window, so that reading a table costs what changed since its cached state. Appending and taking back
 * compare the number of moves and the revision the caller read, so that two changes made at once never both land.
 */
interface LogStore
{
    /**
     * Keeps the log of a new table, at the revision of its number of moves.
     *
     * @throws InvalidGame when a table of that identifier exists
     */
    public function create(GameLog $log): void;

    /**
     * The whole log, every move included: what a finished table shows and what a verification replays.
     */
    public function load(string $gameId): ?GameLog;

    /**
     * What the log holds besides its moves, with its number of moves and its revision.
     */
    public function header(string $gameId): ?LogHeader;

    /**
     * The moves numbered from `$from` to `$to`, both included, in order, read window by window as they are asked for.
     * A log changed while they are read may give fewer; the caller reads its header again to tell.
     *
     * @return iterable<LoggedMove>
     */
    public function moves(string $gameId, int $from, int $to): iterable;

    /**
     * Appends a move to the log the header was read from.
     *
     * @throws ConcurrentMove when the log holds another number of moves or is at another revision
     * @throws InvalidGame    when there is no such table, or the move does not follow its moves
     */
    public function append(LogHeader $read, LoggedMove $move): void;

    /**
     * Removes the last move of the log the header was read from.
     *
     * @throws ConcurrentMove when the log holds another number of moves or is at another revision
     * @throws InvalidGame    when there is no such table, or no move to take back
     */
    public function removeLast(LogHeader $read): void;
}
