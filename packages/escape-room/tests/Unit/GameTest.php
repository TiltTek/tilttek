<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\EscapeRoom\EscapeRooms;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\EscapeRoom\Game;
use Tilttek\EscapeRoom\GameOutcome;
use Tilttek\EscapeRoom\Tests\Unit\Fixture\Crypt;
use Tilttek\PuzzleBuilder\Limiter\InMemoryGuessLimiter;
use Tilttek\PuzzleBuilder\Progress;
use Tilttek\PuzzleBuilder\PuzzleEngine;

#[CoversClass(Game::class)]
#[CoversClass(InvalidRoom::class)]
final class GameTest extends TestCase
{
    public function testAGameReadsBackAsItWasWritten(): void
    {
        $clock = new MockClock('2026-11-13 15:00:00.5', 'America/Toronto');
        $room = Crypt::room();
        $host = ResourceReference::of('member', '3');
        $rooms = new EscapeRooms(new PuzzleEngine(new InMemoryGuessLimiter(100, 60, $clock), clock: $clock), clock: $clock);
        $game = $rooms->start($room, ResourceReference::of('team', 'owls'), 's-1');
        $game = $rooms->requestHint($room, $game, 'candle')->game;
        $game = $rooms->consent($room, $game, true);

        $clock->sleep(10);
        $game = $rooms->pause($room, $game, $host);
        $clock->sleep(20);
        $game = $rooms->resume($room, $game, $host);
        $game = $rooms->pause($room, $game, $host);
        $game = $rooms->abandon($room, $game);

        self::assertSame([
            'room' => 'crypt',
            'team' => 'team:owls',
            'session' => 's-1',
            'started_at' => '2026-11-13T20:00:00.500000Z',
            'paused_at' => null,
            'paused_seconds' => 20,
            'ended_at' => '2026-11-13T20:00:30.500000Z',
            'outcome' => 'abandoned',
            'progress' => [
                'solved' => [],
                'opened' => [],
                'hints' => [['puzzle' => 'candle', 'tier' => 1, 'at' => '2026-11-13T20:00:00.500000Z', 'pushed' => false]],
                'guesses' => [],
            ],
            'public' => true,
        ], $game->toArray());
        self::assertSame($game->toArray(), Game::fromArray($room, $game->toArray())->toArray());
    }

    public function testAPausedGameReadsBack(): void
    {
        $values = $this->values(['paused_at' => '2026-11-13T20:10:00.000000Z', 'session' => null]);
        $game = Game::fromArray(Crypt::room(), $values);

        self::assertTrue($game->paused());
        self::assertNull($game->session);
        self::assertFalse($game->over());
        self::assertSame($values, $game->toArray());
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function badValues(): iterable
    {
        yield 'an unknown field' => [['score' => 1], 'The field "score" is not one of a game.'];
        yield 'another room' => [['room' => 'cell'], 'The game is not one of the room "crypt".'];
        yield 'no team' => [['team' => null], 'A game holds its team, its session, its paused seconds, its progress and its consent.'];
        yield 'a session not a text' => [['session' => 4], 'A game holds its team'];
        yield 'paused seconds not a number' => [['paused_seconds' => '0'], 'A game holds its team'];
        yield 'negative paused seconds' => [['paused_seconds' => -1], 'A game holds its team'];
        yield 'a consent not a flag' => [['public' => 'yes'], 'A game holds its team'];
        yield 'a progress not an array' => [['progress' => 'none'], 'A game holds its team'];
        yield 'a bad outcome' => [['outcome' => 'won', 'ended_at' => '2026-11-13T21:00:00.000000Z'], 'The outcome of a game is escaped, time-up or abandoned.'];
        yield 'an outcome not a text' => [['outcome' => 1, 'ended_at' => '2026-11-13T21:00:00.000000Z'], 'The outcome of a game is escaped, time-up or abandoned.'];
        yield 'an outcome without its end' => [['outcome' => 'escaped'], 'A game that ended holds its outcome and the time it ended.'];
        yield 'an end without its outcome' => [['ended_at' => '2026-11-13T21:00:00.000000Z'], 'A game that ended holds its outcome and the time it ended.'];
        yield 'a bad start' => [['started_at' => '2026-11-13'], 'A time is written in UTC to the microsecond, as 2026-11-13T20:15:03.250000Z.'];
        yield 'a start not a text' => [['started_at' => 0], 'A time is written in UTC'];
        yield 'a bad pause' => [['paused_at' => 'soon'], 'A time is written in UTC'];
        yield 'a bad team' => [['team' => 'owls'], 'The game does not read back: "owls" is not a resource reference of the form "kind:identifier".'];
        yield 'a bad progress' => [['progress' => ['guesses' => ['door' => 1]]], 'The game does not read back: A progress names the puzzles of its scenario only.'];
    }

    /**
     * @param array<array-key, mixed> $fields
     */
    #[DataProvider('badValues')]
    public function testValuesThatAreNotAGameAreRefused(array $fields, string $message): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains($message);

        Game::fromArray(Crypt::room(), $this->values($fields));
    }

    public function testALongUnknownFieldIsCut(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The field "%s" is not one of a game.', str_repeat('x', 64)));

        Game::fromArray(Crypt::room(), [str_repeat('x', 65) => 1]);
    }

    public function testAMultibyteUnknownFieldIsCutByCharacters(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The field "%s" is not one of a game.', str_repeat('é', 64)));

        Game::fromArray(Crypt::room(), [str_repeat('é', 65) => 1]);
    }

    public function testAMoveReplacesWhatIsAlreadySet(): void
    {
        $first = new DateTimeImmutable('2026-11-13 20:10 UTC');
        $second = new DateTimeImmutable('2026-11-13 20:20 UTC');
        $game = new Game('crypt', ResourceReference::of('team', 'owls'), null, new DateTimeImmutable('2026-11-13 20:00 UTC'), Progress::start(), $first, 0, $first, GameOutcome::TimeUp);

        $moved = $game->with(pausedAt: $second, outcome: GameOutcome::Abandoned, endedAt: $second);

        self::assertSame($second, $moved->pausedAt);
        self::assertSame($second, $moved->endedAt);
        self::assertSame(GameOutcome::Abandoned, $moved->outcome);
        self::assertSame($first, $game->with()->pausedAt);
        self::assertSame($first, $game->with()->endedAt);
        self::assertSame(GameOutcome::TimeUp, $game->with()->outcome);
    }

    public function testAGameWithoutRoomIsRefused(): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains('The game is not one of the room "crypt".');

        Game::fromArray(Crypt::room(), []);
    }

    /**
     * @param array<array-key, mixed> $fields
     *
     * @return array<array-key, mixed>
     */
    private function values(array $fields): array
    {
        return [...[
            'room' => 'crypt',
            'team' => 'team:owls',
            'session' => 's-1',
            'started_at' => '2026-11-13T20:00:00.000000Z',
            'paused_at' => null,
            'paused_seconds' => 0,
            'ended_at' => null,
            'outcome' => null,
            'progress' => ['solved' => [], 'opened' => [], 'hints' => [], 'guesses' => []],
            'public' => false,
        ], ...$fields];
    }
}
