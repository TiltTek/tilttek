<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Games;
use Tilttek\GameEngine\Move;
use Tilttek\GameEngine\Store\InMemoryLogStore;
use Tilttek\GameEngine\Tables;
use Tilttek\GameEngineBundle\Application\AudienceViewerResolver;
use Tilttek\GameEngineBundle\Application\Exception\IdentifyingSeat;
use Tilttek\GameEngineBundle\Application\GameTables;
use Tilttek\GameEngineBundle\Application\SeatIdentifiers;
use Tilttek\GameEngineBundle\Application\Viewer;
use Tilttek\GameEngineBundle\Tests\Fixture\RecordingLock;
use Tilttek\GameEngineBundle\Tests\Fixture\RollRace;

#[CoversClass(GameTables::class)]
#[CoversClass(SeatIdentifiers::class)]
#[CoversClass(IdentifyingSeat::class)]
#[CoversClass(Viewer::class)]
#[CoversClass(AudienceViewerResolver::class)]
final class GameTablesTest extends TestCase
{
    private const string SEED = '00112233445566778899aabbccddeeff00112233445566778899aabbccddeeff';

    public function testMovesAreMadeUnderTheLockOfTheirTable(): void
    {
        $lock = new RecordingLock();
        $tables = new GameTables(new Tables(new Games(new RollRace()), new InMemoryLogStore()), $lock);

        $opened = $tables->open('roll-race', 'table:1', ['ann', 'bob'], ['target' => 4], self::SEED);
        $played = $tables->play('table:1', new Move('roll.wait', 'ann'), 0);
        $undone = $tables->undo('table:1', 'ann');

        self::assertSame(['table:1', 'table:1', 'table:1'], $lock->held);
        self::assertNull($lock->holding);
        self::assertSame([0, 1, 0], [$opened->sequence, $played->sequence, $undone->sequence]);
        self::assertSame(4, $tables->state('table:1')->options->int('target'));
        $secrets = $tables->state('table:1')->data['secrets'];
        self::assertIsArray($secrets);
        self::assertIsInt($secrets['bob']);
        self::assertSame(['totals' => ['ann' => 0, 'bob' => 0], 'secret' => $secrets['bob']], $tables->view('table:1', 'bob'));
        self::assertSame(['totals' => ['ann' => 0, 'bob' => 0], 'secret' => null], $tables->view('table:1', null, ['leader-first' => true]));
        self::assertSame(self::SEED, $tables->log('table:1')->seed);
        self::assertSame(hash('sha256', self::SEED), $tables->commitment('table:1'));
        self::assertSame(['table:1', 'table:1', 'table:1'], $lock->held);
    }

    public function testATableIsNeverOpenedForSeatsThatIdentifyAPerson(): void
    {
        $lock = new RecordingLock();
        $logs = new InMemoryLogStore();
        $tables = new GameTables(new Tables(new Games(new RollRace()), $logs), $lock);

        try {
            $tables->open('roll-race', 'table:1', ['ann@example.com', 'bob'], [], self::SEED);
            self::fail('A seat that is an email address was accepted.');
        } catch (IdentifyingSeat) {
            self::assertSame([], $lock->held);
            self::assertNull($logs->header('table:1'));
        }
    }

    public function testWithoutALockMovesAreMadeAsTheyCome(): void
    {
        $tables = new GameTables(new Tables(new Games(new RollRace()), new InMemoryLogStore()));
        $tables->open('roll-race', 'table:1', ['ann', 'bob'], [], self::SEED);

        self::assertSame(1, $tables->play('table:1', new Move('roll.wait', 'ann'))->sequence);
        self::assertSame(0, $tables->undo('table:1')->sequence);
    }

    public function testAViewerIsASeatTheAudienceOrNoOne(): void
    {
        self::assertEquals([true, 'ann', ['a' => 1]], [Viewer::seat('ann', ['a' => 1])->allowed, Viewer::seat('ann', ['a' => 1])->seat, Viewer::seat('ann', ['a' => 1])->preferences]);
        self::assertEquals([true, null, ['b' => true]], [Viewer::audience(['b' => true])->allowed, Viewer::audience(['b' => true])->seat, Viewer::audience(['b' => true])->preferences]);
        self::assertSame([], Viewer::seat('ann')->preferences);
        self::assertSame([], Viewer::audience()->preferences);
        self::assertEquals([false, null, []], [Viewer::denied()->allowed, Viewer::denied()->seat, Viewer::denied()->preferences]);

        self::assertEquals(Viewer::audience(), new AudienceViewerResolver(true)->resolve('table:1'));
        self::assertEquals(Viewer::denied(), new AudienceViewerResolver()->resolve('table:1'));
        self::assertEquals(Viewer::denied(), new AudienceViewerResolver(false)->resolve('table:1'));
    }
}
