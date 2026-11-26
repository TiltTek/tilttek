<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\ScoreSheets\Entries;
use Tilttek\ScoreSheets\Exception\ConcurrentChange;
use Tilttek\ScoreSheets\Exception\IllegalTransition;
use Tilttek\ScoreSheets\Exception\InvalidEntry;
use Tilttek\ScoreSheets\Exception\UnknownMatch;
use Tilttek\ScoreSheets\MatchId;
use Tilttek\ScoreSheets\MatchResult;
use Tilttek\ScoreSheets\MatchSheet;
use Tilttek\ScoreSheets\Message\MatchResultRecorded;
use Tilttek\ScoreSheets\ParticipantId;
use Tilttek\ScoreSheets\Placement;
use Tilttek\ScoreSheets\ScoreKeeper;
use Tilttek\ScoreSheets\SheetStatus;
use Tilttek\ScoreSheets\Store\InMemoryMatchSheetStore;
use Tilttek\ScoreSheets\Team;
use Tilttek\ScoreSheets\TeamId;

#[CoversClass(ScoreKeeper::class)]
#[CoversClass(MatchSheet::class)]
#[CoversClass(MatchResult::class)]
#[CoversClass(Placement::class)]
#[CoversClass(MatchResultRecorded::class)]
#[CoversClass(InMemoryMatchSheetStore::class)]
#[CoversClass(ConcurrentChange::class)]
#[CoversClass(IllegalTransition::class)]
#[CoversClass(UnknownMatch::class)]
final class ScoreKeeperTest extends TestCase
{
    private InMemoryMatchSheetStore $store;

    private ScoreKeeper $keeper;

    private MatchId $match;

    private ParticipantId $ada;

    private ParticipantId $bo;

    protected function setUp(): void
    {
        $this->store = new InMemoryMatchSheetStore();
        $this->keeper = new ScoreKeeper($this->store);
        $this->match = new MatchId('m1');
        $this->ada = new ParticipantId('ada');
        $this->bo = new ParticipantId('bo');
    }

    public function testASheetIsPreparedPublishedFilledAndClosed(): void
    {
        $prepared = $this->prepare();
        self::assertSame(SheetStatus::Prepared, $prepared->status);
        self::assertFalse($prepared->isVisible());
        self::assertSame(0, $prepared->version);

        $board = $this->keeper->record($this->match, 0, $this->ada, 'boats', 4);
        self::assertSame(4, $board->participant($this->ada)?->values['boats']);
        self::assertFalse($this->keeper->sheet($this->match)->isVisible(), 'A score entered ahead leaves the sheet hidden.');

        $published = $this->keeper->publish($this->match, 1);
        self::assertTrue($published->isVisible());
        self::assertSame(SheetStatus::Published, $published->status);
        self::assertSame(2, $published->version);

        $board = $this->keeper->record($this->match, 2, $this->bo, 'boats', 6);
        self::assertSame(6, $board->participant($this->bo)?->values['fleet']);
        self::assertEquals($board, $this->keeper->scoreboard($this->match));

        $result = $this->keeper->close($this->match, 3);
        self::assertSame(SheetStatus::Closed, $this->keeper->sheet($this->match)->status);
        self::assertTrue($this->keeper->sheet($this->match)->isVisible());
        self::assertSame(1, $result->revision);
        self::assertFalse($result->isCorrection());
        self::assertSame([
            'match' => 'm1',
            'game' => 'lantern-harbor',
            'revision' => 1,
            'complete' => false,
            'participants' => [['id' => 'bo', 'rank' => 1, 'total' => 12, 'members' => []], ['id' => 'ada', 'rank' => 2, 'total' => 7, 'members' => []]],
            'teams' => [['id' => 'duo', 'rank' => 1, 'total' => 19, 'members' => ['ada', 'bo']]],
        ], $result->toArray());
        self::assertEquals(new MatchResultRecorded('m1', 1), $result->message());
    }

    public function testASheetClosedTooEarlyIsReopenedAndCorrected(): void
    {
        $this->prepare();
        $this->keeper->publish($this->match, 0);
        $this->keeper->record($this->match, 1, $this->ada, 'boats', 4);
        $this->keeper->close($this->match, 2);

        try {
            $this->keeper->record($this->match, 3, $this->ada, 'boats', 5);
            self::fail('A closed sheet took a score.');
        } catch (IllegalTransition $illegalTransition) {
            self::assertSame('A closed sheet takes no score: reopen it first.', $illegalTransition->getMessage());
        }

        $reopened = $this->keeper->reopen($this->match, 3);
        self::assertSame(SheetStatus::Published, $reopened->status);
        self::assertSame(1, $reopened->revision);

        $board = $this->keeper->record($this->match, 4, $this->ada, 'boats', null);
        self::assertSame(0, $board->participant($this->ada)?->values['boats']);
        self::assertNull($this->keeper->sheet($this->match)->entries->value($this->ada, 'boats'));

        $corrected = $this->keeper->close($this->match, 5);
        self::assertSame(2, $corrected->revision);
        self::assertTrue($corrected->isCorrection());
        self::assertSame(2, $corrected->message()->revision);
        self::assertSame('m1', $corrected->message()->match);
    }

    public function testEachStatusMovesOneWayOnly(): void
    {
        $this->prepare();

        foreach ([
            'Only a published sheet is closed.' => fn (): mixed => $this->keeper->close($this->match, 0),
            'Only a closed sheet is reopened.' => fn (): mixed => $this->keeper->reopen($this->match, 0),
        ] as $message => $call) {
            try {
                $call();
                self::fail($message);
            } catch (IllegalTransition $illegal) {
                self::assertSame($message, $illegal->getMessage());
            }
        }

        $this->keeper->publish($this->match, 0);

        $this->expectExceptionObject(IllegalTransition::because('Only a prepared sheet is published.'));
        $this->keeper->publish($this->match, 1);
    }

    public function testAChangeIsMadeOnTheLastVersionOnly(): void
    {
        $this->prepare();
        $this->keeper->record($this->match, 0, $this->ada, 'boats', 1);

        try {
            $this->keeper->record($this->match, 0, $this->ada, 'boats', 2);
            self::fail('A stale version was accepted.');
        } catch (ConcurrentChange $concurrentChange) {
            self::assertSame('The sheet of the match "m1" is at version 1, not 0.', $concurrentChange->getMessage());
        }

        self::assertSame(1, $this->keeper->sheet($this->match)->entries->value($this->ada, 'boats'));

        try {
            $this->prepare();
            self::fail('A match was prepared twice.');
        } catch (ConcurrentChange $concurrentChange) {
            self::assertSame('The sheet of the match "m1" changed since version -1 was read.', $concurrentChange->getMessage());
        }

        $this->expectExceptionObject(ConcurrentChange::because('The sheet of the match "m1" changed since version 0 was read.'));
        $this->store->save(new MatchSheet($this->match, LanternHarbor::sheet(), new Entries([$this->ada]), version: 1));
    }

    public function testARefusedValueIsNotRecorded(): void
    {
        $this->prepare();

        try {
            $this->keeper->record($this->match, 0, $this->ada, 'boats', 99);
            self::fail('A value out of bounds was accepted.');
        } catch (InvalidEntry) {
            self::assertSame(0, $this->keeper->sheet($this->match)->version);
        }
    }

    public function testAnUnknownMatchIsRefused(): void
    {
        self::assertNull($this->store->find($this->match));

        foreach ([
            fn (): mixed => $this->keeper->sheet($this->match),
            fn (): mixed => $this->keeper->scoreboard($this->match),
            fn (): mixed => $this->keeper->publish($this->match, 0),
        ] as $call) {
            try {
                $call();
                self::fail('An unknown match was found.');
            } catch (UnknownMatch $unknown) {
                self::assertSame('The store holds no sheet for the match "m1".', $unknown->getMessage());
            }
        }
    }

    public function testASheetChangesOneVersionAtATime(): void
    {
        $sheet = new MatchSheet($this->match, LanternHarbor::sheet(), new Entries([$this->ada]));
        $closed = $sheet->withStatus(SheetStatus::Closed);
        $reopened = $closed->withStatus(SheetStatus::Published);
        $filled = $reopened->withEntries(new Entries([$this->bo]));

        self::assertSame([0, 1, 2, 3], [$sheet->version, $closed->version, $reopened->version, $filled->version]);
        self::assertSame([0, 1, 1, 1], [$sheet->revision, $closed->revision, $reopened->revision, $filled->revision]);
        self::assertSame(SheetStatus::Published, $filled->status);
        self::assertSame('bo', $filled->entries->participants[0]->value);
        self::assertSame(1, $sheet->withStatus(SheetStatus::Published)->version);
        self::assertSame(0, $sheet->withStatus(SheetStatus::Published)->revision);
    }

    private function prepare(): MatchSheet
    {
        return $this->keeper->prepare($this->match, LanternHarbor::sheet(), new Entries([$this->ada, $this->bo], [new Team(new TeamId('duo'), [$this->ada, $this->bo])]));
    }
}
