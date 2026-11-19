<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\EscapeRoom\EscapeRooms;
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
use Tilttek\EscapeRoom\Game;
use Tilttek\EscapeRoom\GameOutcome;
use Tilttek\EscapeRoom\Room;
use Tilttek\EscapeRoom\RoomGuess;
use Tilttek\EscapeRoom\RoomHint;
use Tilttek\EscapeRoom\RoomResult;
use Tilttek\EscapeRoom\Tests\Unit\Fixture\Crypt;
use Tilttek\EscapeRoom\Tests\Unit\Fixture\Recorder;
use Tilttek\EscapeRoom\Timer;
use Tilttek\PuzzleBuilder\Answer\AnswerKey;
use Tilttek\PuzzleBuilder\Event\PuzzleSolved;
use Tilttek\PuzzleBuilder\Exception\PuzzleRefused;
use Tilttek\PuzzleBuilder\GuessOutcome;
use Tilttek\PuzzleBuilder\Limiter\InMemoryGuessLimiter;
use Tilttek\PuzzleBuilder\Progress;
use Tilttek\PuzzleBuilder\Puzzle;
use Tilttek\PuzzleBuilder\PuzzleEngine;
use Tilttek\PuzzleBuilder\Record\HintTaken;
use Tilttek\PuzzleBuilder\Scenario;
use Tilttek\PuzzleBuilder\Unlock;

#[CoversClass(EscapeRooms::class)]
#[CoversClass(Game::class)]
#[CoversClass(Timer::class)]
#[CoversClass(RoomGuess::class)]
#[CoversClass(RoomHint::class)]
#[CoversClass(RoomResult::class)]
#[CoversClass(GameRefused::class)]
#[CoversClass(InvalidRoom::class)]
#[CoversClass(ConsentChanged::class)]
#[CoversClass(GameFinished::class)]
#[CoversClass(GamePaused::class)]
#[CoversClass(GameResumed::class)]
#[CoversClass(GameStarted::class)]
#[CoversClass(HintPushed::class)]
#[CoversClass(HintRequested::class)]
#[CoversClass(PuzzleSkipped::class)]
#[CoversClass(ScreenCleared::class)]
#[CoversClass(ScreenMessageSent::class)]
final class EscapeRoomsTest extends TestCase
{
    private Room $room;

    private MockClock $clock;

    private Recorder $events;

    private EscapeRooms $rooms;

    private ResourceReference $team;

    private ResourceReference $host;

    protected function setUp(): void
    {
        $this->room = Crypt::room();
        $this->clock = new MockClock('2026-11-13 20:00:00 UTC');
        $this->events = new Recorder();
        $this->rooms = new EscapeRooms(new PuzzleEngine(new InMemoryGuessLimiter(100, 60, $this->clock), $this->events, $this->clock), $this->events, $this->clock);
        $this->team = ResourceReference::of('team', 'owls');
        $this->host = ResourceReference::of('member', '3');
    }

    public function testAGameStartsTheCountdown(): void
    {
        $game = $this->rooms->start($this->room, $this->team, 'session-9');

        self::assertSame('crypt', $game->room);
        self::assertSame($this->team, $game->team);
        self::assertSame('session-9', $game->session);
        self::assertSame('2026-11-13T20:00:00.000000Z', Game::write($game->startedAt));
        self::assertSame('UTC', $game->startedAt->getTimezone()->getName());
        self::assertFalse($game->over());
        self::assertFalse($game->paused());
        self::assertFalse($game->public);
        self::assertEquals([new GameStarted('crypt', $this->team, 'session-9', $this->clock->now())], $this->events->events);

        $this->clock->sleep(90);
        $timer = $this->rooms->timer($this->room, $game);
        self::assertSame(90, $timer->played);
        self::assertSame(3_510, $timer->remaining);
        self::assertSame(0, $timer->penalty);
        self::assertSame(90, $timer->final());
        self::assertFalse($timer->timeUp());
        self::assertFalse($timer->paused);
    }

    public function testAGameStartsWithoutSession(): void
    {
        self::assertNull($this->rooms->start($this->room, $this->team)->session);
    }

    public function testATeamNamedByAnEmailAddressIsRefused(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('A team is named by a stable identifier, never an email address.');

        $this->rooms->start($this->room, ResourceReference::of('member', 'ana@example.org'));
    }

    public function testAnErasedTeamIsRefused(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('A team is never an erased reference: only an erasure writes one.');

        $this->rooms->start($this->room, ResourceReference::of(EscapeRooms::ERASED_KIND, '0123456789abcdef0123456789abcdef'));
    }

    public function testAnErasedHostIsRefusedByEveryMoveOfTheConsole(): void
    {
        $game = $this->rooms->start($this->room, $this->team);
        $paused = $this->rooms->pause($this->room, $game, $this->host);
        $erased = ResourceReference::of('erased', '0123456789abcdef0123456789abcdef');
        $moves = [
            fn (): mixed => $this->rooms->pushHint($this->room, $game, 'candle', $erased),
            fn (): mixed => $this->rooms->skip($this->room, $game, 'candle', $erased),
            fn (): mixed => $this->rooms->pause($this->room, $game, $erased),
            fn (): mixed => $this->rooms->resume($this->room, $paused, $erased),
            function () use ($game, $erased): void {
                $this->rooms->message($this->room, $game, $erased, 'Look up');
            },
            function () use ($game, $erased): void {
                $this->rooms->clearScreen($this->room, $game, $erased);
            },
        ];
        $this->events->events = [];

        foreach ($moves as $move) {
            try {
                $move();
                self::fail('An erased host is refused.');
            } catch (InvalidRoom $invalidRoom) {
                self::assertSame('A host is never an erased reference: only an erasure writes one.', $invalidRoom->getMessage());
            }
        }

        self::assertSame([], $this->events->events);
    }

    public function testAnEmptySessionIsRefused(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('A session names itself.');

        $this->rooms->start($this->room, $this->team, '');
    }

    public function testSolvingTheChainEscapesWithHintsAndSkipsInTheFinalTime(): void
    {
        $game = $this->rooms->start($this->room, $this->team, 's');
        $this->clock->sleep(60);
        $asked = $this->rooms->requestHint($this->room, $game, 'candle');

        self::assertSame(120, $asked->penalty);
        self::assertSame('Look at the top', $asked->result->hint->text);
        $this->clock->sleep(60);
        $pushed = $this->rooms->pushHint($this->room, $asked->game, 'candle', $this->host);

        self::assertSame(30, $pushed->penalty);
        self::assertSame(2, $pushed->result->tier);
        $this->clock->sleep(60);
        $candle = $this->rooms->guess($this->room, $pushed->game, 'candle', 'Wick!');

        self::assertTrue($candle->result->solved());
        self::assertFalse($candle->escaped());
        self::assertSame(['crest'], $candle->result->opened);
        $skipped = $this->rooms->skip($this->room, $candle->game, 'crest', $this->host);

        self::assertFalse($skipped->escaped());
        $this->clock->sleep(60);
        $tomb = $this->rooms->guess($this->room, $skipped->game, 'tomb', '1347');

        self::assertTrue($tomb->escaped());
        self::assertTrue($tomb->game->over());
        self::assertSame(GameOutcome::Escaped, $tomb->game->outcome);
        self::assertSame('2026-11-13T20:04:00.000000Z', Game::write($tomb->game->endedAt ?? $this->clock->now()->modify('+1 day')));

        $this->clock->sleep(600);
        $timer = $this->rooms->timer($this->room, $tomb->game);
        self::assertSame(240, $timer->played);
        self::assertSame(450, $timer->penalty);
        self::assertSame(2, $timer->hints);
        self::assertSame(1, $timer->skips);
        self::assertSame(690, $timer->final());

        $at = $this->clock->now()->modify('-600 seconds');
        self::assertEquals(new HintRequested('crypt', $this->team, 's', 'candle', 1, 120, $at->modify('-180 seconds')), $this->events->events[2]);
        self::assertEquals(new HintPushed('crypt', $this->team, 's', 'candle', 2, 30, $this->host, $at->modify('-120 seconds')), $this->events->events[4]);
        self::assertEquals(new PuzzleSkipped('crypt', $this->team, 's', 'crest', 300, $this->host, $at->modify('-60 seconds')), $this->events->events[9]);
        self::assertEquals(new GameFinished('crypt', $this->team, 's', GameOutcome::Escaped, 690, $at), $this->events->events[12]);
        self::assertInstanceOf(PuzzleSolved::class, $this->events->events[10]);

        $result = $this->rooms->result($this->room, $tomb->game);
        self::assertSame(690, $result->finalSeconds);
        self::assertSame(240, $result->playedSeconds);
        self::assertSame(450, $result->penaltySeconds);
        self::assertSame(2, $result->hints);
        self::assertSame(1, $result->skips);
        self::assertSame('crypt', $result->room);
        self::assertSame('s', $result->session);
        self::assertSame($this->team, $result->team);
        self::assertSame($tomb->game->endedAt, $result->escapedAt);
        self::assertFalse($result->public);
    }

    public function testAnOverGameRefusesEveryMoveButConsent(): void
    {
        $game = $this->rooms->abandon($this->room, $this->rooms->start($this->room, $this->team));

        self::assertSame(GameOutcome::Abandoned, $game->outcome);
        self::assertTrue($this->rooms->consent($this->room, $game, true)->public);

        foreach ([
            fn (): RoomGuess => $this->rooms->guess($this->room, $game, 'candle', 'wick'),
            fn (): RoomHint => $this->rooms->requestHint($this->room, $game, 'candle'),
            fn (): RoomHint => $this->rooms->pushHint($this->room, $game, 'candle', $this->host),
            fn (): RoomGuess => $this->rooms->skip($this->room, $game, 'candle', $this->host),
            fn (): Game => $this->rooms->pause($this->room, $game, $this->host),
            fn (): Game => $this->rooms->resume($this->room, $game, $this->host),
            fn () => $this->rooms->message($this->room, $game, $this->host, 'Hello'),
            fn () => $this->rooms->clearScreen($this->room, $game, $this->host),
            fn (): Game => $this->rooms->expire($this->room, $game),
            fn (): Game => $this->rooms->abandon($this->room, $game),
        ] as $move) {
            try {
                $move();
                self::fail('A move of a game over is refused.');
            } catch (GameRefused $gameRefused) {
                self::assertSame('The game of the room "crypt" is over.', $gameRefused->getMessage());
            }
        }
    }

    public function testAnAbandonedGameHasNoResult(): void
    {
        $game = $this->rooms->abandon($this->room, $this->rooms->start($this->room, $this->team));

        $this->expectException(GameRefused::class);
        $this->expectExceptionMessageIsOrContains('The team has not escaped the room "crypt": it has no result to show.');

        $this->rooms->result($this->room, $game);
    }

    public function testARunningGameHasNoResult(): void
    {
        $this->expectException(GameRefused::class);
        $this->expectExceptionMessageIsOrContains('The team has not escaped the room "crypt"');

        $this->rooms->result($this->room, $this->rooms->start($this->room, $this->team));
    }

    public function testAPauseStopsTheCountdown(): void
    {
        $game = $this->rooms->start($this->room, $this->team);
        $this->clock->sleep(100);
        $paused = $this->rooms->pause($this->room, $game, $this->host);

        self::assertTrue($paused->paused());
        $this->clock->sleep(500);
        self::assertSame(100, $this->rooms->timer($this->room, $paused)->played);
        self::assertTrue($this->rooms->timer($this->room, $paused)->paused);

        try {
            $this->rooms->guess($this->room, $paused, 'candle', 'wick');
            self::fail('A paused game takes no guess.');
        } catch (GameRefused $gameRefused) {
            self::assertSame('The game of the room "crypt" is paused.', $gameRefused->getMessage());
        }

        $resumed = $this->rooms->resume($this->room, $paused, $this->host);
        self::assertFalse($resumed->paused());
        self::assertSame(500, $resumed->pausedSeconds);
        $this->clock->sleep(50);
        self::assertSame(150, $this->rooms->timer($this->room, $resumed)->played);
        self::assertEquals([
            new GamePaused('crypt', $this->team, null, $this->host, $this->clock->now()->modify('-550 seconds')),
            new GameResumed('crypt', $this->team, null, $this->host, $this->clock->now()->modify('-50 seconds')),
        ], \array_slice($this->events->events, 1));

        $again = $this->rooms->resume($this->room, $this->rooms->pause($this->room, $resumed, $this->host), $this->host);
        self::assertSame(500, $again->pausedSeconds);
    }

    public function testARunningGameIsNotResumed(): void
    {
        $this->expectException(GameRefused::class);
        $this->expectExceptionMessageIsOrContains('The game of the room "crypt" is not paused.');

        $this->rooms->resume($this->room, $this->rooms->start($this->room, $this->team), $this->host);
    }

    public function testAGameAbandonedWhilePausedCountsNoPausedTime(): void
    {
        $game = $this->rooms->start($this->room, $this->team);
        $this->clock->sleep(100);
        $paused = $this->rooms->pause($this->room, $game, $this->host);
        $this->clock->sleep(300);
        $ended = $this->rooms->abandon($this->room, $paused);

        self::assertFalse($ended->paused());
        self::assertSame(300, $ended->pausedSeconds);
        self::assertSame(100, $this->rooms->timer($this->room, $ended)->played);
    }

    public function testATimeUpRefusesMovesUntilItExpires(): void
    {
        $game = $this->rooms->start($this->room, $this->team, 's');
        $this->clock->sleep(3_599);

        try {
            $this->rooms->expire($this->room, $game);
            self::fail('A game with time left does not expire.');
        } catch (GameRefused $gameRefused) {
            self::assertSame('The time of the room "crypt" is not up yet.', $gameRefused->getMessage());
        }

        self::assertSame(1, $this->rooms->timer($this->room, $game)->remaining);
        $this->clock->sleep(1);
        self::assertTrue($this->rooms->timer($this->room, $game)->timeUp());

        try {
            $this->rooms->guess($this->room, $game, 'candle', 'wick');
            self::fail('A game out of time takes no guess.');
        } catch (GameRefused $gameRefused) {
            self::assertSame('The time of the room "crypt" is up: the game ends by expire().', $gameRefused->getMessage());
        }

        $this->clock->sleep(500);
        self::assertSame(3_600, $this->rooms->timer($this->room, $game)->played);
        $expired = $this->rooms->expire($this->room, $game);

        self::assertSame(GameOutcome::TimeUp, $expired->outcome);
        self::assertSame('2026-11-13T21:00:00.000000Z', Game::write($expired->endedAt ?? $this->clock->now()));
        self::assertEquals(new GameFinished('crypt', $this->team, 's', GameOutcome::TimeUp, 3_600, $expired->endedAt ?? $this->clock->now()), $this->events->events[1]);
    }

    public function testAnExpiryCountsThePausesOfTheGame(): void
    {
        $game = $this->rooms->start($this->room, $this->team);
        $this->clock->sleep(100);
        $paused = $this->rooms->pause($this->room, $game, $this->host);
        $this->clock->sleep(200);
        $resumed = $this->rooms->resume($this->room, $paused, $this->host);
        $this->clock->sleep(3_600);
        $expired = $this->rooms->expire($this->room, $resumed);

        self::assertSame('2026-11-13T21:03:20.000000Z', Game::write($expired->endedAt ?? $this->clock->now()));
    }

    public function testTheHostDrivesTheHintScreen(): void
    {
        $game = $this->rooms->start($this->room, $this->team, 's');
        $this->rooms->message($this->room, $game, $this->host, 'Look behind the clock');
        $this->rooms->message($this->room, $game, $this->host, str_repeat('é', 280));
        $this->rooms->clearScreen($this->room, $game, $this->host);

        $now = $this->clock->now();
        self::assertEquals([
            new ScreenMessageSent('crypt', $this->team, 's', 'Look behind the clock', $this->host, $now),
            new ScreenMessageSent('crypt', $this->team, 's', str_repeat('é', 280), $this->host, $now),
            new ScreenCleared('crypt', $this->team, 's', $this->host, $now),
        ], \array_slice($this->events->events, 1));
    }

    public function testAPausedGameStillTakesMessages(): void
    {
        $game = $this->rooms->pause($this->room, $this->rooms->start($this->room, $this->team), $this->host);
        $this->rooms->message($this->room, $game, $this->host, 'Back in a minute');

        self::assertInstanceOf(ScreenMessageSent::class, $this->events->events[2]);
    }

    public function testABlankMessageIsRefused(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('A message to the hint screen holds 1 to 280 characters.');

        $this->rooms->message($this->room, $this->rooms->start($this->room, $this->team), $this->host, '  ');
    }

    public function testALongerMessageIsRefused(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('A message to the hint screen holds 1 to 280 characters.');

        $this->rooms->message($this->room, $this->rooms->start($this->room, $this->team), $this->host, str_repeat('é', 281));
    }

    public function testAMessageThatIsNotUtf8IsRefused(): void
    {
        $this->expectException(InvalidRoom::class);

        $this->rooms->message($this->room, $this->rooms->start($this->room, $this->team), $this->host, "\xC3\x28");
    }

    public function testTheTeamChoosesToShowItsResult(): void
    {
        $game = $this->rooms->start($this->room, $this->team, 's');
        $shown = $this->rooms->consent($this->room, $game, true);
        $hidden = $this->rooms->consent($this->room, $shown, false);

        self::assertTrue($shown->public);
        self::assertFalse($hidden->public);
        self::assertEquals([new ConsentChanged('crypt', $this->team, 's', true), new ConsentChanged('crypt', $this->team, 's', false)], \array_slice($this->events->events, 1));
    }

    public function testAGameOfAnotherRoomIsRefused(): void
    {
        $other = Room::chain('cell', 'The cell', [new Puzzle('door', 'Door', new AnswerKey(['open']))]);
        $game = $this->rooms->start($other, $this->team);

        $this->expectException(GameRefused::class);
        $this->expectExceptionMessageIsOrContains('The game is one of the room "cell", not "crypt".');

        $this->rooms->consent($this->room, $game, true);
    }

    public function testATimerOfAnotherRoomIsRefused(): void
    {
        $other = Room::chain('cell', 'The cell', [new Puzzle('door', 'Door', new AnswerKey(['open']))]);

        $this->expectException(GameRefused::class);

        $this->rooms->timer($this->room, $this->rooms->start($other, $this->team));
    }

    public function testTheHostOpensAPuzzleThatWaitsForTheHost(): void
    {
        $room = new Room('study', 'The study', new Scenario('study', [
            new Puzzle('desk', 'Desk', new AnswerKey(['ink'])),
            new Puzzle('safe', 'Safe', new AnswerKey(['0451']), [], Unlock::manual()),
        ]));
        $game = $this->rooms->open($room, $this->rooms->start($room, $this->team), 'safe');

        self::assertTrue($game->progress->isOpened('safe'));
        self::assertSame(GuessOutcome::Correct, $this->rooms->guess($room, $game, 'safe', '0451')->result->outcome);
    }

    public function testThePuzzleRulesStillHold(): void
    {
        $this->expectException(PuzzleRefused::class);
        $this->expectExceptionMessageIsOrContains('The puzzle "tomb" is still locked.');

        $this->rooms->guess($this->room, $this->rooms->start($this->room, $this->team), 'tomb', '1347');
    }

    public function testAServiceWithoutDispatcherMovesAllTheSame(): void
    {
        $rooms = new EscapeRooms(new PuzzleEngine(new InMemoryGuessLimiter(100, 60, $this->clock), clock: $this->clock), clock: $this->clock);
        $game = $rooms->start($this->room, $this->team);
        $game = $rooms->requestHint($this->room, $game, 'candle')->game;
        $game = $rooms->pushHint($this->room, $game, 'candle', $this->host)->game;
        $game = $rooms->resume($this->room, $rooms->pause($this->room, $game, $this->host), $this->host);

        $rooms->message($this->room, $game, $this->host, 'Hi');
        $rooms->clearScreen($this->room, $game, $this->host);

        $game = $rooms->consent($this->room, $game, true);
        $game = $rooms->skip($this->room, $game, 'candle', $this->host)->game;
        $game = $rooms->skip($this->room, $game, 'crest', $this->host)->game;
        $game = $rooms->skip($this->room, $game, 'tomb', $this->host)->game;

        self::assertSame(GameOutcome::Escaped, $game->outcome);
        self::assertSame([], $this->events->events);
    }

    public function testAPushedHintIsMarkedPushedAndAnAskedOneIsNot(): void
    {
        $asked = $this->rooms->requestHint($this->room, $this->rooms->start($this->room, $this->team), 'candle');
        $pushed = $this->rooms->pushHint($this->room, $asked->game, 'candle', $this->host);

        self::assertSame([false, true], array_map(static fn (HintTaken $hint): bool => $hint->pushed, $pushed->game->progress->hints()));
    }

    public function testTheTimerReadsTheEndBeforeThePause(): void
    {
        $started = $this->clock->now();
        $game = new Game('crypt', $this->team, null, $started, Progress::start(), $started->modify('+600 seconds'), 0, $started->modify('+1200 seconds'), GameOutcome::Abandoned);

        self::assertSame(1_200, $this->rooms->timer($this->room, $game)->played);
    }

    public function testThePlayedTimeStartsAtZeroAndNeverGoesUnderIt(): void
    {
        $game = $this->rooms->start($this->room, $this->team);

        self::assertSame(0, $this->rooms->timer($this->room, $game)->played);
        self::assertSame(0, $this->rooms->timer($this->room, $game->with(pausedSeconds: 500))->played);
    }

    public function testAPausedGameOpensNoPuzzle(): void
    {
        $paused = $this->rooms->pause($this->room, $this->rooms->start($this->room, $this->team), $this->host);

        $this->expectException(GameRefused::class);
        $this->expectExceptionMessageIsOrContains('The game of the room "crypt" is paused.');

        $this->rooms->open($this->room, $paused, 'candle');
    }

    public function testAnInstantResumeAddsNoPausedSecond(): void
    {
        $paused = $this->rooms->pause($this->room, $this->rooms->start($this->room, $this->team), $this->host);

        self::assertSame(0, $this->rooms->resume($this->room, $paused, $this->host)->pausedSeconds);
    }

    public function testAResumeBeforeThePauseAddsNoPausedSecond(): void
    {
        $game = new Game('crypt', $this->team, null, $this->clock->now(), Progress::start(), $this->clock->now()->modify('+100 seconds'));

        self::assertSame(0, $this->rooms->resume($this->room, $game, $this->host)->pausedSeconds);
    }

    public function testEndingAPausedGameAddsTheSecondsOfThePauseOnly(): void
    {
        $now = $this->clock->now();
        $future = new Game('crypt', $this->team, null, $now, Progress::start(), $now->modify('+100 seconds'));
        $instant = new Game('crypt', $this->team, null, $now, Progress::start(), $now);
        $earlier = new Game('crypt', $this->team, null, $now->modify('-100 seconds'), Progress::start(), $now->modify('-40 seconds'));

        self::assertSame(0, $this->rooms->abandon($this->room, $future)->pausedSeconds);
        self::assertSame(0, $this->rooms->abandon($this->room, $instant)->pausedSeconds);
        self::assertSame(40, $this->rooms->abandon($this->room, $earlier)->pausedSeconds);
        self::assertInstanceOf(DateTimeImmutable::class, $this->rooms->abandon($this->room, $earlier)->endedAt);
    }

    public function testAGameOfAnotherRoomCannotChangeItsConsent(): void
    {
        $game = $this->rooms->start($this->room, $this->team);
        $other = Room::chain('cell', 'The cell', [new Puzzle('door', 'Door', new AnswerKey(['open']))]);

        $this->expectException(GameRefused::class);

        $this->rooms->consent($other, $game, true);
    }

    public function testAGameOfAnotherRoomIsRefusedByEveryMoveOfALiveGame(): void
    {
        $other = Room::chain('cell', 'The cell', [new Puzzle('door', 'Door', new AnswerKey(['open']))]);
        $game = $this->rooms->start($other, $this->team);

        foreach ([
            fn (): Game => $this->rooms->abandon($this->room, $game),
            fn () => $this->rooms->clearScreen($this->room, $game, $this->host),
            fn (): Game => $this->rooms->expire($this->room, $game),
        ] as $move) {
            try {
                $move();
                self::fail('A game of another room is refused.');
            } catch (GameRefused $gameRefused) {
                self::assertStringContainsString('"crypt"', $gameRefused->getMessage());
            }
        }
    }
}
