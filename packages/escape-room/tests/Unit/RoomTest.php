<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoom\Tests\Unit;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\EscapeRoom\Exception\InvalidRoom;
use Tilttek\EscapeRoom\Room;
use Tilttek\EscapeRoom\Tests\Unit\Fixture\Crypt;
use Tilttek\PuzzleBuilder\Answer\AnswerKey;
use Tilttek\PuzzleBuilder\Exception\InvalidScenario;
use Tilttek\PuzzleBuilder\Puzzle;
use Tilttek\PuzzleBuilder\Record\HintTaken;
use Tilttek\PuzzleBuilder\Scenario;

#[CoversClass(Room::class)]
#[CoversClass(InvalidRoom::class)]
final class RoomTest extends TestCase
{
    public function testAChainedRoomOpensItsPuzzlesOneAfterTheOther(): void
    {
        $room = Crypt::room();

        self::assertSame('crypt', $room->id);
        self::assertSame('The crypt', $room->title);
        self::assertSame('crypt', $room->scenario->id);
        self::assertSame('tomb', $room->scenario->finale);
        self::assertSame(['candle', 'crest', 'tomb'], $room->scenario->order());
        self::assertSame([], $room->scenario->puzzle('candle')->unlock->requires);
        self::assertSame(['candle'], $room->scenario->puzzle('crest')->unlock->requires);
        self::assertSame(['crest'], $room->scenario->puzzle('tomb')->unlock->requires);
        self::assertSame(3_600, $room->duration);
        self::assertSame(120, $room->hintPenalty);
        self::assertSame(300, $room->skipPenalty);
        self::assertSame(2, $room->minPlayers);
        self::assertSame(6, $room->maxPlayers);
    }

    public function testAChainKeepsEveryFieldOfTheRoom(): void
    {
        $room = Room::chain('cell', 'The cell', [new Puzzle('door', 'Door', new AnswerKey(['open']))], 600, 10, 20, 1, 4);

        self::assertSame([600, 10, 20, 1, 4], [$room->duration, $room->hintPenalty, $room->skipPenalty, $room->minPlayers, $room->maxPlayers]);
        self::assertSame('door', $room->scenario->finale);
    }

    public function testAChainTakesItsDefaults(): void
    {
        $room = Room::chain('cell', 'The cell', [new Puzzle('door', 'Door', new AnswerKey(['open']))]);

        self::assertSame([3_600, 0, 0, 2, 6], [$room->duration, $room->hintPenalty, $room->skipPenalty, $room->minPlayers, $room->maxPlayers]);
    }

    public function testAChainWithoutPuzzleIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('A scenario holds 1 to 200 puzzles.');

        Room::chain('cell', 'The cell', []);
    }

    public function testARoomTakesItsDefaults(): void
    {
        $room = new Room('cell', 'The cell', new Scenario('cell', [new Puzzle('door', 'Door', new AnswerKey(['open']))]));

        self::assertSame([3_600, 0, 0, 2, 6], [$room->duration, $room->hintPenalty, $room->skipPenalty, $room->minPlayers, $room->maxPlayers]);
    }

    public function testAHintCostsItsOwnPenaltyOrTheRoomOne(): void
    {
        $room = Crypt::room();
        $at = new DateTimeImmutable('2026-11-13 20:00 UTC');

        self::assertSame(120, $room->penaltyOf(new HintTaken('candle', 1, $at)));
        self::assertSame(30, $room->penaltyOf(new HintTaken('candle', 2, $at)));
    }

    public function testTheBoundsAreTaken(): void
    {
        $scenario = Crypt::room()->scenario;

        self::assertSame(60, new Room('a', 'A', $scenario, 60, 3_600, 3_600, 100, 100)->duration);
        self::assertSame(86_400, new Room(str_repeat('a', 64), str_repeat('é', 200), $scenario, 86_400, 0, 0, 1, 1)->duration);
    }

    /**
     * @return iterable<string, array{string, string, int, int, int, int, int, string}>
     */
    public static function badRooms(): iterable
    {
        yield 'a bad identifier' => ['Crypt', 'The crypt', 3_600, 0, 0, 2, 6, 'The identifier of a room holds 1 to 64 characters among a-z, 0-9, and inner - and _, from a letter; "Crypt" does not.'];
        yield 'a long identifier is cut' => [str_repeat('a', 65), 'The crypt', 3_600, 0, 0, 2, 6, \sprintf('"%s" does not.', str_repeat('a', 64))];
        yield 'a multibyte identifier is cut by characters' => [str_repeat('é', 65), 'The crypt', 3_600, 0, 0, 2, 6, \sprintf('"%s" does not.', str_repeat('é', 64))];
        yield 'an identifier ending with a sign' => ['crypt-', 'The crypt', 3_600, 0, 0, 2, 6, 'The identifier of a room holds'];
        yield 'a blank title' => ['crypt', ' ', 3_600, 0, 0, 2, 6, 'The title of a room holds 1 to 200 characters.'];
        yield 'a long title' => ['crypt', str_repeat('é', 201), 3_600, 0, 0, 2, 6, 'The title of a room holds 1 to 200 characters.'];
        yield 'too short' => ['crypt', 'The crypt', 59, 0, 0, 2, 6, 'A room lasts 60 to 86400 seconds.'];
        yield 'too long' => ['crypt', 'The crypt', 86_401, 0, 0, 2, 6, 'A room lasts 60 to 86400 seconds.'];
        yield 'a negative hint penalty' => ['crypt', 'The crypt', 3_600, -1, 0, 2, 6, 'A penalty is 0 to 3600 seconds.'];
        yield 'a long hint penalty' => ['crypt', 'The crypt', 3_600, 3_601, 0, 2, 6, 'A penalty is 0 to 3600 seconds.'];
        yield 'a negative skip penalty' => ['crypt', 'The crypt', 3_600, 0, -1, 2, 6, 'A penalty is 0 to 3600 seconds.'];
        yield 'a long skip penalty' => ['crypt', 'The crypt', 3_600, 0, 3_601, 2, 6, 'A penalty is 0 to 3600 seconds.'];
        yield 'no player' => ['crypt', 'The crypt', 3_600, 0, 0, 0, 6, 'A team holds 1 to 100 players, the fewest no more than the most.'];
        yield 'the fewest over the most' => ['crypt', 'The crypt', 3_600, 0, 0, 7, 6, 'A team holds 1 to 100 players'];
        yield 'too many players' => ['crypt', 'The crypt', 3_600, 0, 0, 2, 101, 'A team holds 1 to 100 players'];
    }

    #[DataProvider('badRooms')]
    public function testARoomOutOfBoundsIsRefused(string $id, string $title, int $duration, int $hintPenalty, int $skipPenalty, int $minPlayers, int $maxPlayers, string $message): void
    {
        $this->expectException(InvalidRoom::class);
        $this->expectExceptionMessageIsOrContains($message);

        new Room($id, $title, Crypt::room()->scenario, $duration, $hintPenalty, $skipPenalty, $minPlayers, $maxPlayers);
    }
}
