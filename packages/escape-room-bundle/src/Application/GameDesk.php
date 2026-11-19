<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\EscapeRoom\EscapeRooms;
use Tilttek\EscapeRoom\Exception\GameRefused;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\EscapeRoom\Game;
use Tilttek\EscapeRoom\GameOutcome;
use Tilttek\EscapeRoom\Leaderboard;
use Tilttek\EscapeRoom\RoomGuess;
use Tilttek\EscapeRoom\RoomHint;
use Tilttek\EscapeRoom\Standing;
use Tilttek\EscapeRoom\Timer;
use Tilttek\EscapeRoomBundle\Application\Contract\GameStore;
use Tilttek\EscapeRoomBundle\Application\Contract\ResultStore;
use Tilttek\EscapeRoomBundle\Application\Contract\RoomCatalog;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenPublisher;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenStore;
use Tilttek\EscapeRoomBundle\Application\Exception\DeskRefused;
use Tilttek\PuzzleBuilder\Exception\InvalidAnswer;
use Tilttek\PuzzleBuilder\Exception\PuzzleRefused;
use Tilttek\PuzzleBuilder\PuzzleEngine;
use Tilttek\PuzzleBuilder\View\ScenarioView;

/**
 * The games of the application, by identifier: each move reads the game from the store, plays it through
 * `EscapeRooms`, writes it back with the moment its countdown reaches zero, records the result of a team that escaped,
 * keeps what the hint screen shows, and tells the screens that the game moved.
 *
 * The service keeps nothing between two calls.
 */
final readonly class GameDesk
{
    /** The moves the screens are told of. */
    public const array MOVES = ['start', 'hint', 'skip', 'pause', 'resume', 'message', 'clear', 'consent', 'finish'];

    /** The most games `expireDue()` ends in one call. */
    public const int MAX_BATCH = 1_000;

    private const string IDENTIFIER = '/^[A-Za-z0-9][A-Za-z0-9_-]{0,63}$/D';

    public function __construct(
        private EscapeRooms $rooms,
        private RoomCatalog $catalog,
        private GameStore $games,
        private ResultStore $results,
        private ScreenStore $screens,
        private PuzzleEngine $puzzles,
        private ClockInterface $clock,
        private ?ScreenPublisher $publisher = null,
    ) {
    }

    /**
     * Starts the countdown of a team in a room, under an identifier the application chooses.
     *
     * @throws DeskRefused when the identifier is malformed or taken, or the room unknown
     * @throws InvalidRoom when the team is named by an email address or erased, or the session is empty
     */
    public function start(string $id, string $room, ResourceReference $team, ?string $session = null): StoredGame
    {
        $this->identifier($id);

        if (null !== $this->games->find($id)) {
            throw DeskRefused::exists($id);
        }

        $found = $this->catalog->room($room) ?? throw DeskRefused::unknownRoom($room);
        $game = new StoredGame($id, $found, $this->rooms->start($found, $team, $session));
        $this->write($game);
        $this->publisher?->moved($id, 'start');

        return $game;
    }

    /**
     * @throws DeskRefused when there is no such game, or its room is no longer in the catalog
     * @throws InvalidRoom when the stored values do not read back as a game of its room
     */
    public function game(string $id): StoredGame
    {
        $values = $this->games->find($id) ?? throw DeskRefused::unknownGame($id);
        $roomId = $values['room'] ?? null;
        $room = (\is_string($roomId) ? $this->catalog->room($roomId) : null) ?? throw DeskRefused::unknownRoom(\is_string($roomId) ? $roomId : '');

        return new StoredGame($id, $room, Game::fromArray($room, $values));
    }

    public function timer(StoredGame $game): Timer
    {
        return $this->rooms->timer($game->room, $game->game);
    }

    /**
     * The puzzles of the game as the host sees them: their status, hints revealed and left, guesses and skips.
     */
    public function puzzles(StoredGame $game): ScenarioView
    {
        return $this->puzzles->view($game->room->scenario, $game->game->progress);
    }

    /**
     * What the hint screen of the game shows, or null before the host showed anything.
     */
    public function screen(string $id): ?Screen
    {
        return $this->screens->screen($id);
    }

    /**
     * @throws DeskRefused   when there is no such game
     * @throws GameRefused   when the game is over, paused or out of time
     * @throws PuzzleRefused when the puzzle is unknown, locked or solved
     * @throws InvalidAnswer when the guess is not one the check reads
     */
    public function guess(string $id, string $puzzle, string $answer): RoomGuess
    {
        $game = $this->game($id);
        $guess = $this->rooms->guess($game->room, $game->game, $puzzle, $answer);
        $this->moved($game, $guess->game, null);

        return $guess;
    }

    /**
     * @throws DeskRefused   when there is no such game
     * @throws GameRefused   when the game is over, paused or out of time
     * @throws PuzzleRefused when the puzzle is unknown, locked or solved, or has no hint left
     */
    public function requestHint(string $id, string $puzzle): RoomHint
    {
        $game = $this->game($id);
        $hint = $this->rooms->requestHint($game->room, $game->game, $puzzle);
        $this->moved($game, $hint->game, null);

        return $hint;
    }

    /**
     * Pushes the next hint of a puzzle to the hint screen, which shows it until the host shows something else.
     *
     * @throws DeskRefused   when there is no such game
     * @throws GameRefused   when the game is over, paused or out of time
     * @throws PuzzleRefused when the puzzle is unknown, locked or solved, or has no hint left
     * @throws InvalidRoom   when the host is erased
     */
    public function pushHint(string $id, string $puzzle, ResourceReference $host): RoomHint
    {
        $game = $this->game($id);
        $hint = $this->rooms->pushHint($game->room, $game->game, $puzzle, $host);
        $this->screens->show($id, new Screen(ScreenKind::Hint, $host, $this->clock->now(), $puzzle, $hint->result->tier, $hint->penalty));
        $this->moved($game, $hint->game, 'hint');

        return $hint;
    }

    /**
     * @throws DeskRefused   when there is no such game
     * @throws GameRefused   when the game is over, paused or out of time
     * @throws PuzzleRefused when the puzzle is unknown, locked or solved
     * @throws InvalidRoom   when the host is erased
     */
    public function skip(string $id, string $puzzle, ResourceReference $host): RoomGuess
    {
        $game = $this->game($id);
        $skip = $this->rooms->skip($game->room, $game->game, $puzzle, $host);
        $this->moved($game, $skip->game, 'skip');

        return $skip;
    }

    /**
     * @throws DeskRefused when there is no such game
     * @throws GameRefused when the game is over, paused already or out of time
     * @throws InvalidRoom when the host is erased
     */
    public function pause(string $id, ResourceReference $host): StoredGame
    {
        $game = $this->game($id);

        return $this->moved($game, $this->rooms->pause($game->room, $game->game, $host), 'pause');
    }

    /**
     * @throws DeskRefused when there is no such game
     * @throws GameRefused when the game is over or not paused
     * @throws InvalidRoom when the host is erased
     */
    public function resume(string $id, ResourceReference $host): StoredGame
    {
        $game = $this->game($id);

        return $this->moved($game, $this->rooms->resume($game->room, $game->game, $host), 'resume');
    }

    /**
     * Shows a message of the host on the hint screen.
     *
     * @throws DeskRefused when there is no such game
     * @throws GameRefused when the game is over
     * @throws InvalidRoom when the host is erased, or the message empty or longer than 280 characters
     */
    public function message(string $id, ResourceReference $host, string $message): void
    {
        $game = $this->game($id);
        $this->rooms->message($game->room, $game->game, $host, $message);
        $this->screens->show($id, new Screen(ScreenKind::Message, $host, $this->clock->now(), message: $message));
        $this->publisher?->moved($id, 'message');
    }

    /**
     * @throws DeskRefused when there is no such game
     * @throws GameRefused when the game is over
     * @throws InvalidRoom when the host is erased
     */
    public function clearScreen(string $id, ResourceReference $host): void
    {
        $game = $this->game($id);
        $this->rooms->clearScreen($game->room, $game->game, $host);
        $this->screens->show($id, new Screen(ScreenKind::Cleared, $host, $this->clock->now()));
        $this->publisher?->moved($id, 'clear');
    }

    /**
     * @throws DeskRefused when there is no such game
     * @throws GameRefused when the game is over
     */
    public function abandon(string $id): StoredGame
    {
        $game = $this->game($id);

        return $this->moved($game, $this->rooms->abandon($game->room, $game->game), null);
    }

    /**
     * Ends the game when its time is up; a game over, or with time left, is left as it is. Calling it again changes
     * nothing.
     *
     * @return bool whether the game ended now
     *
     * @throws DeskRefused when there is no such game
     */
    public function expire(string $id): bool
    {
        $game = $this->game($id);

        if ($game->game->over() || !$this->rooms->timer($game->room, $game->game)->timeUp()) {
            return false;
        }

        $this->moved($game, $this->rooms->expire($game->room, $game->game), null);

        return true;
    }

    /**
     * Ends the games whose countdown reached zero, the earliest first.
     *
     * @param int $limit how many at most, 1 to 1 000
     *
     * @return list<string> the games ended now
     *
     * @throws InvalidRoom when the limit is out of bounds
     */
    public function expireDue(int $limit = 100): array
    {
        if ($limit < 1 || $limit > self::MAX_BATCH) {
            throw InvalidRoom::because(\sprintf('A batch of expiries holds 1 to %d games.', self::MAX_BATCH));
        }

        $ended = [];

        foreach ($this->games->due($this->clock->now(), $limit) as $id) {
            if ($this->expire($id)) {
                $ended[] = $id;
            }
        }

        return $ended;
    }

    /**
     * Records whether the team agrees to show its result on the public leaderboard; it may change its mind at any time.
     *
     * @throws DeskRefused when there is no such game
     */
    public function consent(string $id, bool $public): StoredGame
    {
        $game = $this->game($id);
        $stored = $this->moved($game, $this->rooms->consent($game->room, $game->game, $public), 'consent');
        $this->results->consent($id, $public);

        return $stored;
    }

    /**
     * The public leaderboard of a room: only the teams that agreed.
     *
     * @param int $limit how many places, 1 to 1 000
     *
     * @return list<Standing>
     *
     * @throws InvalidRoom when the limit is out of bounds
     */
    public function leaderboard(string $room, int $limit = 10): array
    {
        if ($limit < 1 || $limit > Leaderboard::MAX_LIMIT) {
            throw InvalidRoom::because(\sprintf('A leaderboard shows 1 to %d places.', Leaderboard::MAX_LIMIT));
        }

        return Leaderboard::rank($room, $this->results->shown($room, $limit), $limit);
    }

    /**
     * The moment the countdown of a running game reaches zero; null while it is paused or over.
     */
    public static function deadline(StoredGame $game): ?DateTimeImmutable
    {
        if ($game->game->over() || $game->game->paused()) {
            return null;
        }

        return $game->game->startedAt->modify(\sprintf('+%d seconds', $game->room->duration + $game->game->pausedSeconds));
    }

    /**
     * @throws DeskRefused when the identifier is malformed
     */
    private function identifier(string $id): void
    {
        if (1 !== preg_match(self::IDENTIFIER, $id)) {
            throw DeskRefused::identifier();
        }
    }

    private function moved(StoredGame $before, Game $after, ?string $move): StoredGame
    {
        $game = new StoredGame($before->id, $before->room, $after);
        $this->write($game);

        if (GameOutcome::Escaped === $after->outcome) {
            $this->results->record($game->id, $this->rooms->result($game->room, $after));
        }

        $finished = $after->over() && !$before->game->over();
        $told = $finished ? 'finish' : $move;

        if (null !== $told) {
            $this->publisher?->moved($game->id, $told);
        }

        return $game;
    }

    private function write(StoredGame $game): void
    {
        $this->games->save($game->id, $game->game->toArray(), self::deadline($game));
    }
}
