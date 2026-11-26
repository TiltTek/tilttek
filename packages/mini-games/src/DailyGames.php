<?php

declare(strict_types=1);

namespace Tilttek\MiniGames;

use Tilttek\GameEngine\Exception\ConcurrentMove;
use Tilttek\GameEngine\Exception\GameInProgress;
use Tilttek\GameEngine\Exception\IllegalMove;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\GameEngine\Exception\UnknownTable;
use Tilttek\GameEngine\GameLog;
use Tilttek\GameEngine\GameState;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Tables;
use Tilttek\MiniGames\Contract\ScoreSink;
use Tilttek\MiniGames\Exception\InvalidMiniGame;
use Tilttek\MiniGames\Exception\SeedNotRevealable;
use Tilttek\MiniGames\Store\NullScoreSink;

/**
 * The daily games: each player plays a game of the day at a table of their own, opened with the seed of that game and
 * that day, so that everyone gets the same game. The score of a finished game goes to the score sink, once. It keeps
 * no state between two calls; the `Tables` it is given keeps the states it read, until its `reset()`.
 */
final readonly class DailyGames
{
    /**
     * @param Tables $tables the engine's tables, which know the same games as `$games`
     */
    public function __construct(
        private Tables $tables,
        private MiniGames $games,
        private DailySeed $seed,
        private ScoreSink $scores = new NullScoreSink(),
    ) {
    }

    /**
     * The identifier of a player's table for a game of a day, `daily:higher-or-lower:2026-11-24:u-101`.
     */
    public static function tableId(string $game, Day $day, string $player): string
    {
        return 'daily:'.$game.':'.$day->value.':'.$player;
    }

    /**
     * The player's table for the game of the day, opened on the first call, the same table on the next ones.
     *
     * @param string $player the player's opaque identifier: an ASCII letter, then ASCII letters, digits, `:`, `.`,
     *                       `_` or `-`
     *
     * @throws InvalidMiniGame when no mini-game has that name
     * @throws InvalidGame     when the player's identifier does not make a table identifier
     */
    public function start(string $game, Day $day, string $player): GameState
    {
        $this->games->get($game);
        $table = self::tableId($game, $day, $player);

        try {
            $this->tables->header($table);
        } catch (UnknownTable) {
            return $this->tables->open($game, $table, [$player], seed: $this->seed->for($game, $day));
        }

        return $this->tables->state($table);
    }

    /**
     * A move of the player at their table of the day; when it ends the game, its score goes to the score sink.
     *
     * @param string               $type     a move type of the game, `higher-or-lower.call`
     * @param array<string, mixed> $payload
     * @param ?int                 $expected the moves the player saw; a move made since is refused with `ConcurrentMove`
     *
     * @throws InvalidMiniGame
     * @throws IllegalMove
     * @throws ConcurrentMove
     * @throws InvalidGame
     * @throws UnknownTable    when the player has not started that game of that day
     */
    public function play(string $game, Day $day, string $player, string $type, array $payload = [], ?int $expected = null): GameState
    {
        $miniGame = $this->games->get($game);
        $state = $this->tables->play(self::tableId($game, $day, $player), new Move($type, $player, $payload), $expected);

        if ($state->outcome instanceof Outcome) {
            $this->scores->record(new DailyScore(
                $game,
                $day,
                $player,
                \in_array($player, $state->outcome->winners, true),
                $state->outcome->statistics->ofSeat($player, $miniGame->scoring()->statistic),
                $miniGame->scoring()->order,
            ));
        }

        return $state;
    }

    /**
     * What the player sees of their table, shaped by their preferences.
     *
     * @param array<string, mixed> $preferences
     *
     * @return array<string, mixed>
     *
     * @throws IllegalMove
     * @throws InvalidGame
     * @throws ConcurrentMove
     * @throws UnknownTable
     */
    public function view(string $game, Day $day, string $player, array $preferences = []): array
    {
        return $this->tables->view(self::tableId($game, $day, $player), $player, $preferences);
    }

    /**
     * The player's result to share, at any point of the game.
     *
     * @throws InvalidMiniGame
     * @throws InvalidGame
     * @throws ConcurrentMove
     * @throws UnknownTable
     */
    public function share(string $game, Day $day, string $player): ShareGrid
    {
        return $this->games->get($game)->share($this->tables->state(self::tableId($game, $day, $player)));
    }

    /**
     * How the game ended for the player, or null while it goes on.
     *
     * @param ?int $best the best score the player had before this game, or null for a first game
     *
     * @throws InvalidMiniGame
     * @throws InvalidGame
     * @throws ConcurrentMove
     * @throws UnknownTable
     */
    public function ending(string $game, Day $day, string $player, ?int $best = null): ?Ending
    {
        $outcome = $this->tables->state(self::tableId($game, $day, $player))->outcome;

        return $outcome instanceof Outcome ? Ending::of($outcome, $player, $this->games->get($game)->scoring(), $best) : null;
    }

    /**
     * The finished log of the player's table, seed included, to verify every draw — once the day is over only, since
     * every player of the day shares its seed.
     *
     * @param Day $today the current day, in the timezone the days are counted in
     *
     * @throws SeedNotRevealable while the day of the game is not over
     * @throws GameInProgress
     * @throws ConcurrentMove
     * @throws InvalidGame
     * @throws UnknownTable
     */
    public function finishedLog(string $game, Day $day, string $player, Day $today): GameLog
    {
        if (!$day->isBefore($today)) {
            throw SeedNotRevealable::until($game, $day->value);
        }

        return $this->tables->finishedLog(self::tableId($game, $day, $player));
    }
}
