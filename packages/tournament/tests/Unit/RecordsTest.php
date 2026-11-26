<?php

declare(strict_types=1);

namespace Tilttek\Tournament\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Tournament\Game;
use Tilttek\Tournament\Outcome;
use Tilttek\Tournament\Pairing;
use Tilttek\Tournament\Record;
use Tilttek\Tournament\Records;
use Tilttek\Tournament\Result;
use Tilttek\Tournament\Round;
use Tilttek\Tournament\Scoring;

#[CoversClass(Game::class)]
#[CoversClass(Record::class)]
#[CoversClass(Records::class)]
final class RecordsTest extends TestCase
{
    public function testRecordsAreBuiltFromTheRoundsAndTheResults(): void
    {
        $rounds = [
            new Round('s', 1, [new Pairing(1, 'alice', 'bob'), new Pairing(2, 'carol')]),
            new Round('s', 2, [new Pairing(1, 'carol', 'alice'), new Pairing(2, 'bob')]),
            new Round('s', 3, [new Pairing(1, 'bob', 'carol'), new Pairing(2, 'alice', null, rest: true)]),
        ];
        $results = [
            's#1#1' => Result::firstWins(12, 4),
            's#2#1' => Result::draw(5, 5),
            's#3#1' => new Result(Outcome::FirstForfeits),
        ];
        $records = Records::build(['alice', 'bob', 'carol', 'dave'], $rounds, $results, new Scoring());

        self::assertSame(['alice', 'bob', 'carol', 'dave'], $records->participants());
        self::assertSame(3, $records->points('alice'));
        self::assertSame(2, $records->points('bob'));
        self::assertSame(5, $records->points('carol'));
        self::assertSame(0, $records->points('dave'));
        self::assertSame(0, $records->points('erin'));
        self::assertSame([], $records->of('erin')->games);
        self::assertSame('erin', $records->of('erin')->participant);

        $alice = $records->of('alice');
        self::assertCount(3, $alice->games);
        self::assertSame(17, $alice->mission());
        self::assertSame(1, $alice->wins());
        self::assertSame(0, $alice->byes());
        self::assertSame(['bob' => [1], 'carol' => [2]], $alice->met());
        self::assertSame(['bob', 'carol'], $alice->opponents());
        self::assertSame([true, false], $alice->sides());
        self::assertSame(0, $alice->sideBalance());
        self::assertTrue($alice->games[2]->rest);
        self::assertSame(0, $alice->games[2]->points);

        $bob = $records->of('bob');
        self::assertSame(1, $bob->byes());
        self::assertSame(0, $bob->wins());
        self::assertSame(['alice' => [1]], $bob->met());
        self::assertSame(['alice', 'carol'], $bob->opponents());
        self::assertSame([false], $bob->sides());
        self::assertSame(-1, $bob->sideBalance());
        self::assertSame(4, $bob->mission());

        $carol = $records->of('carol');
        self::assertSame(1, $carol->byes());
        self::assertSame(0, $carol->wins(), 'A forfeit win is not a win over the board.');
        self::assertTrue($carol->games[2]->isWin());
        self::assertFalse($carol->games[2]->isPlayed());
        self::assertSame(['alice' => [2]], $carol->met(), 'A forfeit is not a meeting.');
        self::assertSame(1, $carol->sideBalance());
    }

    public function testATableWithoutAResultIsLeftOut(): void
    {
        $records = Records::build(['alice', 'bob'], [new Round('s', 1, [new Pairing(1, 'alice', 'bob')])], [], new Scoring());

        self::assertSame([], $records->of('alice')->games);
        self::assertSame([], $records->of('bob')->games);
    }

    public function testAParticipantNotListedIsRecordedAllTheSame(): void
    {
        $records = Records::build([], [new Round('s', 1, [new Pairing(1, 'alice', 'bob'), new Pairing(2, 'carol')])], ['s#1#1' => Result::secondWins()], new Scoring());

        self::assertSame(2, $records->points('bob'));
        self::assertSame(2, $records->points('carol'));
        self::assertSame(['alice', 'bob', 'carol'], $records->participants());
    }

    public function testAGameTellsAWinFromEitherSide(): void
    {
        self::assertTrue(new Game(1, 'bob', true, 2, Outcome::FirstWins)->isWin());
        self::assertFalse(new Game(1, 'bob', false, 0, Outcome::FirstWins)->isWin());
        self::assertTrue(new Game(1, 'bob', false, 2, Outcome::SecondWins)->isWin());
        self::assertFalse(new Game(1, null, true, 2)->isWin(), 'A bye is not a win.');
        self::assertFalse(new Game(1, null, true, 2)->isPlayed());
        self::assertFalse(new Game(1, null, true, 2, Outcome::FirstWins)->isPlayed());
        self::assertTrue(new Game(1, 'bob', true, 1, Outcome::Draw)->isPlayed());
    }

    public function testTheLastSideAgainstAnOpponentIsTheLatestMeeting(): void
    {
        $record = new Record('alice', [
            new Game(1, 'bob', true, 2, Outcome::FirstWins),
            new Game(2, 'carol', false, 2, Outcome::SecondWins),
            new Game(3, 'bob', false, 1, Outcome::Draw),
            new Game(4, 'dave', true, 2, Outcome::SecondForfeits),
        ]);

        self::assertFalse($record->lastSideAgainst('bob'));
        self::assertFalse($record->lastSideAgainst('carol'));
        self::assertNull($record->lastSideAgainst('dave'), 'A forfeit sets no side.');
        self::assertNull($record->lastSideAgainst('erin'));
        self::assertSame(-1, $record->sideBalance());
        self::assertSame(7, $record->points());
    }
}
