<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom;

use DateTimeImmutable;
use DateTimeZone;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Clock\NativeClock;
use Tilttek\EscapeRoom\Event\ConsentChanged;
use Tilttek\EscapeRoom\Event\GameFinished;
use Tilttek\EscapeRoom\Event\GamePaused;
use Tilttek\EscapeRoom\Event\GameResumed;
use Tilttek\EscapeRoom\Event\GameStarted;
use Tilttek\EscapeRoom\Event\HintPushed;
use Tilttek\EscapeRoom\Event\HintRequested;
use Tilttek\EscapeRoom\Event\PuzzleSkipped;
use Tilttek\EscapeRoom\Event\ScreenCleared;
use Tilttek\EscapeRoom\Event\ScreenMessageSent;
use Tilttek\EscapeRoom\Exception\GameRefused;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\PuzzleBuilder\Exception\InvalidAnswer;
use Tilttek\PuzzleBuilder\Exception\PuzzleRefused;
use Tilttek\PuzzleBuilder\Progress;
use Tilttek\PuzzleBuilder\PuzzleEngine;
use Tilttek\PuzzleBuilder\Record\Solved;

/**
 * The games of escape rooms: the countdown, the puzzles played through `tilttek/puzzle-builder`, the hints asked by
 * the players or pushed by the host and counted in the final time, the pauses, and the hint screen the host drives
 * from the console — every move of it an event.
 *
 * Each move takes the room and the team's `Game` and gives the game to store after it; the service keeps nothing
 * between two calls. The countdown is read from the clock, never kept running: a game whose time is up ends when
 * `expire()` is called — by the next move refused, or by a scheduled task.
 */
final readonly class EscapeRooms
{
    public const int MAX_MESSAGE = 280;

    /**
     * The kind of the references an erasure writes in place of a member: `erased:` and an identifier of its own. A
     * team or a host of this kind given by the caller is refused.
     */
    public const string ERASED_KIND = 'erased';

    public function __construct(
        private PuzzleEngine $puzzles,
        private ?EventDispatcherInterface $events = null,
        private ClockInterface $clock = new NativeClock(),
    ) {
    }

    /**
     * Starts the countdown for a team.
     *
     * @param ResourceReference $team    the team, by a stable identifier, never an email address
     * @param ?string           $session the booked session, when there is one
     *
     * @throws InvalidRoom when the team is named by an email address or erased, or the session is empty
     */
    public function start(Room $room, ResourceReference $team, ?string $session = null): Game
    {
        if (str_contains($team->id, '@')) {
            throw InvalidRoom::because('A team is named by a stable identifier, never an email address.');
        }

        if (self::ERASED_KIND === $team->key) {
            throw InvalidRoom::erased('team');
        }

        if ('' === $session) {
            throw InvalidRoom::because('A session names itself.');
        }

        $now = $this->clock->now();
        $this->events?->dispatch(new GameStarted($room->id, $team, $session, $now));

        return new Game($room->id, $team, $session, $now->setTimezone(new DateTimeZone('UTC')), Progress::start());
    }

    /**
     * The countdown, the time played and the penalties, now or as the game ended.
     *
     * @throws GameRefused when the game is one of another room
     */
    public function timer(Room $room, Game $game): Timer
    {
        $this->same($room, $game);
        $end = $game->endedAt ?? $game->pausedAt ?? $this->clock->now();
        $played = min($room->duration, max(0, $end->getTimestamp() - $game->startedAt->getTimestamp() - $game->pausedSeconds));
        $hints = $game->progress->hints();
        $skips = \count(array_filter($game->progress->solved(), static fn (Solved $solved): bool => $solved->skipped));
        $penalty = $skips * $room->skipPenalty;

        foreach ($hints as $hint) {
            $penalty += $room->penaltyOf($hint);
        }

        return new Timer($played, $room->duration - $played, $penalty, \count($hints), $skips, $game->paused());
    }

    /**
     * Checks a guess; the game ends, escaped, when it solves the finale.
     *
     * @throws GameRefused   when the game is over, paused, out of time or of another room
     * @throws PuzzleRefused when the puzzle is unknown, locked or solved
     * @throws InvalidAnswer when the guess is not one the check reads
     */
    public function guess(Room $room, Game $game, string $puzzle, string $answer): RoomGuess
    {
        $this->playing($room, $game);
        $result = $this->puzzles->guess($room->scenario, $game->progress, $game->team, $puzzle, $answer);

        return new RoomGuess($result, $this->moved($room, $game->with($result->progress), $result->completed));
    }

    /**
     * Reveals the next hint of a puzzle, asked by the players.
     *
     * @throws GameRefused   when the game is over, paused, out of time or of another room
     * @throws PuzzleRefused when the puzzle is unknown, locked or solved, or has no hint left
     */
    public function requestHint(Room $room, Game $game, string $puzzle): RoomHint
    {
        $this->playing($room, $game);
        $result = $this->puzzles->hint($room->scenario, $game->progress, $game->team, $puzzle);
        $penalty = $room->penaltyOf($result->progress->hints()[\count($result->progress->hints()) - 1]);
        $this->events?->dispatch(new HintRequested($room->id, $game->team, $game->session, $puzzle, $result->tier, $penalty, $this->clock->now()));

        return new RoomHint($result, $penalty, $game->with($result->progress));
    }

    /**
     * Pushes the next hint of a puzzle from the console to the hint screen.
     *
     * @throws GameRefused   when the game is over, paused, out of time or of another room
     * @throws PuzzleRefused when the puzzle is unknown, locked or solved, or has no hint left
     * @throws InvalidRoom   when the host is an erased reference
     */
    public function pushHint(Room $room, Game $game, string $puzzle, ResourceReference $host): RoomHint
    {
        $this->host($host);
        $this->playing($room, $game);
        $result = $this->puzzles->hint($room->scenario, $game->progress, $game->team, $puzzle, true);
        $penalty = $room->penaltyOf($result->progress->hints()[\count($result->progress->hints()) - 1]);
        $this->events?->dispatch(new HintPushed($room->id, $game->team, $game->session, $puzzle, $result->tier, $penalty, $host, $this->clock->now()));

        return new RoomHint($result, $penalty, $game->with($result->progress));
    }

    /**
     * Marks an open puzzle solved for players who are stuck; the room's skip penalty is added to the final time.
     *
     * @throws GameRefused   when the game is over, paused, out of time or of another room
     * @throws PuzzleRefused when the puzzle is unknown, locked or solved
     * @throws InvalidRoom   when the host is an erased reference
     */
    public function skip(Room $room, Game $game, string $puzzle, ResourceReference $host): RoomGuess
    {
        $this->host($host);
        $this->playing($room, $game);
        $result = $this->puzzles->skip($room->scenario, $game->progress, $game->team, $puzzle);
        $this->events?->dispatch(new PuzzleSkipped($room->id, $game->team, $game->session, $puzzle, $room->skipPenalty, $host, $this->clock->now()));

        return new RoomGuess($result, $this->moved($room, $game->with($result->progress), $result->completed));
    }

    /**
     * Opens a puzzle the host opens.
     *
     * @throws GameRefused   when the game is over, paused, out of time or of another room
     * @throws PuzzleRefused when the puzzle is unknown, opens by its unlock, or is open already
     */
    public function open(Room $room, Game $game, string $puzzle): Game
    {
        $this->playing($room, $game);

        return $game->with($this->puzzles->open($room->scenario, $game->progress, $game->team, $puzzle));
    }

    /**
     * Stops the countdown.
     *
     * @throws GameRefused when the game is over, paused already, out of time or of another room
     * @throws InvalidRoom when the host is an erased reference
     */
    public function pause(Room $room, Game $game, ResourceReference $host): Game
    {
        $this->host($host);
        $this->playing($room, $game);
        $now = $this->clock->now();
        $this->events?->dispatch(new GamePaused($room->id, $game->team, $game->session, $host, $now));

        return $game->with(pausedAt: $now);
    }

    /**
     * Runs the countdown again.
     *
     * @throws GameRefused when the game is over, not paused, or of another room
     * @throws InvalidRoom when the host is an erased reference
     */
    public function resume(Room $room, Game $game, ResourceReference $host): Game
    {
        $this->host($host);
        $this->live($room, $game);

        if (!$game->pausedAt instanceof DateTimeImmutable) {
            throw GameRefused::running($room->id);
        }

        $now = $this->clock->now();
        $this->events?->dispatch(new GameResumed($room->id, $game->team, $game->session, $host, $now));

        return $game->with(resume: true, pausedSeconds: max(0, $now->getTimestamp() - $game->pausedAt->getTimestamp()));
    }

    /**
     * Sends a message from the console to the hint screen.
     *
     * @throws GameRefused when the game is over or of another room
     * @throws InvalidRoom when the host is an erased reference, or the message is empty or longer than 280 characters
     */
    public function message(Room $room, Game $game, ResourceReference $host, string $message): void
    {
        $this->host($host);
        $this->live($room, $game);

        if ('' === trim($message) || !mb_check_encoding($message, 'UTF-8') || mb_strlen($message, 'UTF-8') > self::MAX_MESSAGE) {
            throw InvalidRoom::because(\sprintf('A message to the hint screen holds 1 to %d characters.', self::MAX_MESSAGE));
        }

        $this->events?->dispatch(new ScreenMessageSent($room->id, $game->team, $game->session, $message, $host, $this->clock->now()));
    }

    /**
     * Clears the hint screen.
     *
     * @throws GameRefused when the game is over or of another room
     * @throws InvalidRoom when the host is an erased reference
     */
    public function clearScreen(Room $room, Game $game, ResourceReference $host): void
    {
        $this->host($host);
        $this->live($room, $game);
        $this->events?->dispatch(new ScreenCleared($room->id, $game->team, $game->session, $host, $this->clock->now()));
    }

    /**
     * Ends a game whose time is up.
     *
     * @throws GameRefused when the game is over, its time is not up, or it is of another room
     */
    public function expire(Room $room, Game $game): Game
    {
        $this->live($room, $game);

        if (!$this->timer($room, $game)->timeUp()) {
            throw GameRefused::timeLeft($room->id);
        }

        return $this->end($room, $game, GameOutcome::TimeUp, $game->startedAt->modify(\sprintf('+%d seconds', $room->duration + $game->pausedSeconds)));
    }

    /**
     * Ends a game before its time, by the host.
     *
     * @throws GameRefused when the game is over or of another room
     */
    public function abandon(Room $room, Game $game): Game
    {
        $this->live($room, $game);

        return $this->end($room, $game, GameOutcome::Abandoned, $this->clock->now());
    }

    /**
     * Records whether the team agrees to show its result on the public leaderboard; it may change its mind at any
     * time.
     *
     * @throws GameRefused when the game is of another room
     */
    public function consent(Room $room, Game $game, bool $public): Game
    {
        $this->same($room, $game);
        $this->events?->dispatch(new ConsentChanged($room->id, $game->team, $game->session, $public));

        return $game->with(public: $public);
    }

    /**
     * The result of a team that escaped, for the leaderboard.
     *
     * @throws GameRefused when the team has not escaped, or the game is of another room
     */
    public function result(Room $room, Game $game): RoomResult
    {
        $timer = $this->timer($room, $game);

        if (GameOutcome::Escaped !== $game->outcome || !$game->endedAt instanceof DateTimeImmutable) {
            throw GameRefused::notEscaped($room->id);
        }

        return new RoomResult($room->id, $game->team, $game->session, $timer->final(), $timer->played, $timer->penalty, $timer->hints, $timer->skips, $game->endedAt, $game->public);
    }

    private function moved(Room $room, Game $game, bool $completed): Game
    {
        return $completed ? $this->end($room, $game, GameOutcome::Escaped, $this->clock->now()) : $game;
    }

    private function end(Room $room, Game $game, GameOutcome $outcome, DateTimeImmutable $at): Game
    {
        if ($game->pausedAt instanceof DateTimeImmutable) {
            $game = $game->with(resume: true, pausedSeconds: max(0, $at->getTimestamp() - $game->pausedAt->getTimestamp()));
        }

        $game = $game->with(outcome: $outcome, endedAt: $at->setTimezone(new DateTimeZone('UTC')));
        $this->events?->dispatch(new GameFinished($room->id, $game->team, $game->session, $outcome, $this->timer($room, $game)->final(), $at));

        return $game;
    }

    private function host(ResourceReference $host): void
    {
        if (self::ERASED_KIND === $host->key) {
            throw InvalidRoom::erased('host');
        }
    }

    private function same(Room $room, Game $game): void
    {
        if ($game->room !== $room->id) {
            throw GameRefused::otherRoom($room->id, $game->room);
        }
    }

    private function live(Room $room, Game $game): void
    {
        $this->same($room, $game);

        if ($game->over()) {
            throw GameRefused::over($room->id);
        }
    }

    private function playing(Room $room, Game $game): void
    {
        $this->live($room, $game);

        if ($game->paused()) {
            throw GameRefused::paused($room->id);
        }

        if ($this->timer($room, $game)->timeUp()) {
            throw GameRefused::timeUp($room->id);
        }
    }
}
