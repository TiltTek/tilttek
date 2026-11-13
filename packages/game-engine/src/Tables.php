<?php

declare(strict_types=1);

namespace Tilttek\GameEngine;

use Closure;
use Tilttek\GameEngine\Contract\LogStore;
use Tilttek\GameEngine\Contract\StateCache;
use Tilttek\GameEngine\Contract\TablePublisher;
use Tilttek\GameEngine\Exception\ConcurrentMove;
use Tilttek\GameEngine\Exception\GameInProgress;
use Tilttek\GameEngine\Exception\GameOver;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\Exception\UnknownTable;
use Tilttek\GameEngine\Store\NullStateCache;
use Tilttek\GameEngine\Store\NullTablePublisher;

/**
 * The tables of an application: opened, played, taken back and read through the engine, their logs in a store, their
 * states in a cache brought up to date from the log whenever it lags, and every change told to a publisher.
 *
 * A read costs the header of the log and, when the cached state is not that of the log, the moves made since it, read
 * window by window: a cached state of an earlier revision of the same lineage is brought forward by the moves made
 * since; any other is rebuilt from the setup. The states it read are kept for as long as the header of their log says
 * nothing changed, so that a request reads the moves of a table at most once; `reset()`, called between two requests
 * in a worker, frees them. Every change compares the number of moves and the revision it read with the store's.
 */
final class Tables
{
    /** The states it keeps at most, the one read least recently let go first. */
    public const int REMEMBERED = 32;

    /** The reads of a log that changes while it is read, before giving up. */
    public const int READS = 3;

    /** @var array<string, Snapshot> */
    private array $read = [];

    public function __construct(
        private readonly Games $games,
        private readonly LogStore $logs,
        private readonly StateCache $cache = new NullStateCache(),
        private readonly TablePublisher $publisher = new NullTablePublisher(),
        private readonly Engine $engine = new Engine(),
    ) {
    }

    /**
     * Opens a table of a game.
     *
     * @param list<string>         $seats
     * @param array<string, mixed> $options
     *
     * @throws InvalidGame
     * @throws UnknownTable when no game has that name
     */
    public function open(string $game, string $gameId, array $seats, array $options = [], ?string $seed = null): GameState
    {
        $table = $this->engine->start($this->games->get($game), $gameId, $seats, $options, $seed);
        $this->logs->create($table->log);

        return $this->changed($table->log->header(), $table->state);
    }

    /**
     * Makes a move at a table.
     *
     * @param ?int $expected the moves the caller saw at the table; a move made since is refused with `ConcurrentMove`
     *
     * @throws IllegalMove
     * @throws ConcurrentMove
     * @throws InvalidGame
     * @throws UnknownTable
     */
    public function play(string $gameId, Move $move, ?int $expected = null): GameState
    {
        [$header, $state] = $this->current($gameId);

        if (null !== $expected && $expected !== $header->sequence) {
            throw ConcurrentMove::at($gameId, $expected, $header->sequence);
        }

        $step = $this->engine->step($this->games->get($header->game), $header, $state, $move);
        $this->logs->append($header, $step->move);

        return $this->changed($header->appended(), $step->state);
    }

    /**
     * Takes the last move of a table back, its state rebuilt from the setup.
     *
     * @throws GameOver       when the game is over: its seed may have been shown, which tells the draws to come
     * @throws IllegalMove
     * @throws ConcurrentMove
     * @throws InvalidGame
     * @throws UnknownTable
     */
    public function undo(string $gameId, ?string $seat = null): GameState
    {
        [$header, $state] = $this->current($gameId);
        $game = $this->games->get($header->game);
        $this->engine->checkUndo($game, $state, $this->last($header), $seat);
        [, $previous] = $this->settle($header, fn (LogHeader $read): GameState => $this->engine->advance($game, $read, $this->engine->setup($game, $read), $this->logs->moves($gameId, 1, $read->sequence - 1)), 1, 1);
        $this->logs->removeLast($header);

        return $this->changed($header->withoutLast(), $previous);
    }

    /**
     * The current state of a table: the cached one when it reflects the log, else brought up to date from it.
     *
     * @throws ConcurrentMove when the log changed during each of its reads
     * @throws InvalidGame    when the log cannot be replayed
     * @throws UnknownTable
     */
    public function state(string $gameId): GameState
    {
        return $this->current($gameId)[1];
    }

    /**
     * What a seat sees of a table, shaped by the preferences of its player; what its audience sees when the seat is null.
     *
     * @param array<string, mixed> $preferences the values the player chose; the game's defaults for the others
     *
     * @return array<string, mixed>
     *
     * @throws IllegalMove    when the seat is not at the table
     * @throws InvalidGame    when a preference is not one the game offers, or its value one it allows
     * @throws ConcurrentMove when the log changed during each of its reads
     * @throws UnknownTable
     */
    public function view(string $gameId, ?string $seat = null, array $preferences = []): array
    {
        [$header, $state] = $this->current($gameId);

        return $this->engine->view($this->games->get($header->game), $state, $seat, $preferences);
    }

    /**
     * The whole log of a table, every move included.
     *
     * @throws UnknownTable
     */
    public function log(string $gameId): GameLog
    {
        return $this->logs->load($gameId) ?? throw UnknownTable::table($gameId);
    }

    /**
     * The whole log of a table whose game is over, seed included, as its players are shown it to verify every draw: the
     * log read is the one of the finished state — same seed, same number of moves —, so that a table opened again under
     * the same identifier between the two reads never has its seed shown.
     *
     * @throws GameInProgress when the game goes on: the seed would tell the draws to come
     * @throws ConcurrentMove when the log read is not the one of the finished state
     * @throws InvalidGame    when the log cannot be replayed
     * @throws UnknownTable
     */
    public function finishedLog(string $gameId): GameLog
    {
        [$header, $state] = $this->current($gameId);

        if (!$state->isOver()) {
            throw GameInProgress::table($gameId);
        }

        $log = $this->log($gameId);

        if ($log->sequence() !== $header->sequence || !hash_equals($header->commitment(), $log->commitment())) {
            throw ConcurrentMove::unsettled($gameId, 1);
        }

        return $log;
    }

    /**
     * What the log of a table holds besides its moves: its seed, seats and options, its number of moves and revision.
     *
     * @throws UnknownTable
     */
    public function header(string $gameId): LogHeader
    {
        return $this->logs->header($gameId) ?? throw UnknownTable::table($gameId);
    }

    /**
     * The commitment to the seed of a table, which its players may be shown from the opening, read without its moves.
     *
     * @throws UnknownTable
     */
    public function commitment(string $gameId): string
    {
        return $this->header($gameId)->commitment();
    }

    /**
     * Lets go of the states it kept, as a worker does between two requests.
     */
    public function reset(): void
    {
        $this->read = [];
    }

    /**
     * The header of a table's log and its current state: the one kept or cached when it reflects the log, else the
     * kept or cached one brought forward by the moves made since, else the log replayed — read again when it changed
     * meanwhile.
     *
     * @return array{LogHeader, GameState}
     *
     * @throws ConcurrentMove
     * @throws InvalidGame
     * @throws UnknownTable
     */
    private function current(string $gameId): array
    {
        $header = $this->header($gameId);
        $kept = $this->read[$gameId] ?? null;

        if ($kept instanceof Snapshot && $kept->reflects($header)) {
            return [$header, $this->remember($kept)->state];
        }

        $cached = $this->cache->get($gameId);

        if ($cached instanceof Snapshot && $cached->reflects($header)) {
            return [$header, $this->remember($cached)->state];
        }

        $from = match (true) {
            $kept instanceof Snapshot && $kept->precedes($header) => $kept,
            $cached instanceof Snapshot && $cached->precedes($header) => $cached,
            default => null,
        };
        $game = $this->games->get($header->game);
        [$header, $state] = $this->settle($header, function (LogHeader $read) use ($game, $from): GameState {
            $start = $from instanceof Snapshot && $from->precedes($read) ? $from->state : $this->engine->setup($game, $read);

            return $this->engine->advance($game, $read, $start, $this->logs->moves($read->gameId, $start->sequence + 1, $read->sequence));
        });
        $snapshot = Snapshot::of($state, $header);
        $this->cache->put($snapshot);

        return [$header, $this->remember($snapshot)->state];
    }

    /**
     * A state built from the moves of a log, kept only when the log did not change while they were read: built again
     * from the header read anew otherwise, up to `$reads` times.
     *
     * @param Closure(LogHeader): GameState $build
     * @param int                           $before the moves before the last the state stops at: 1 for a move taken back
     *
     * @return array{LogHeader, GameState} the header the state was built from, and the state
     *
     * @throws ConcurrentMove when the log changed during each read
     * @throws InvalidGame    when the log, unchanged, cannot be replayed
     * @throws UnknownTable
     */
    private function settle(LogHeader $header, Closure $build, int $before = 0, int $reads = self::READS): array
    {
        for ($read = 1; $read <= $reads; ++$read) {
            try {
                $state = $build($header);
            } catch (InvalidGame $invalidGame) {
                $now = $this->header($header->gameId);

                if ($now->revision === $header->revision) {
                    throw $invalidGame;
                }

                $header = $now;

                continue;
            }

            $now = $this->header($header->gameId);

            if ($now->revision === $header->revision && $state->sequence === $header->sequence - $before) {
                return [$header, $state];
            }

            $header = $now;
        }

        throw ConcurrentMove::unsettled($header->gameId, $reads);
    }

    /**
     * The last move of the log the header was read from, or null when it holds none — or no longer holds it.
     */
    private function last(LogHeader $header): ?LoggedMove
    {
        foreach ($this->logs->moves($header->gameId, $header->sequence, $header->sequence) as $move) {
            return $move;
        }

        return null;
    }

    private function remember(Snapshot $snapshot): Snapshot
    {
        $gameId = $snapshot->state->gameId;
        unset($this->read[$gameId]);

        if (\count($this->read) >= self::REMEMBERED) {
            unset($this->read[array_key_first($this->read)]);
        }

        return $this->read[$gameId] = $snapshot;
    }

    private function changed(LogHeader $header, GameState $state): GameState
    {
        $snapshot = Snapshot::of($state, $header);
        $this->cache->put($snapshot);
        $this->remember($snapshot);
        $this->publisher->publish(TableUpdate::of($state));

        return $state;
    }
}
