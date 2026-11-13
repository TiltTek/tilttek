<?php

declare(strict_types=1);

namespace Tilttek\GameEngine\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Turn;
use Tilttek\GameEngine\TurnOrder;

#[CoversClass(Turn::class)]
final class TurnTest extends TestCase
{
    private const array SEATS = ['ann', 'bob', 'cy', 'dee'];

    public function testAPhaseStartsInTheOrderOfItsKind(): void
    {
        self::assertSame(self::SEATS, Turn::start(TurnOrder::Free, self::SEATS, ['cy' => 20])->toAct());
        self::assertSame(['ann'], Turn::start(TurnOrder::Sequential, self::SEATS, ['cy' => 20])->toAct());
        self::assertSame(self::SEATS, Turn::start(TurnOrder::Simultaneous, self::SEATS, [])->toAct());

        $initiative = Turn::start(TurnOrder::Initiative, self::SEATS, ['bob' => 12, 'dee' => 18, 'cy' => 12]);
        self::assertSame(['dee', 'bob', 'cy', 'ann'], $initiative->order);
        self::assertSame(['dee'], $initiative->toAct());
        self::assertSame(['bob', 'cy', 'ann', 'dee'], Turn::start(TurnOrder::Initiative, self::SEATS, ['dee' => -1, 'bob' => 0, 'cy' => 0, 'ann' => -1])->order);
        self::assertSame(self::SEATS, Turn::start(TurnOrder::Initiative, self::SEATS, [])->order);
        self::assertSame([1, 0, 0], [$initiative->round, $initiative->current, $initiative->moves]);
    }

    public function testATurnEndsToTheNextSeatAndARoundToTheFirst(): void
    {
        $turn = Turn::start(TurnOrder::Sequential, ['ann', 'bob'], []);

        self::assertTrue($turn->allows('ann'));
        self::assertTrue($turn->allows(null));
        self::assertFalse($turn->allows('bob'));
        $turn = $turn->end('ann');
        self::assertSame([['bob'], 1], [$turn->toAct(), $turn->round]);
        $turn = $turn->end('bob');
        self::assertSame([['ann'], 2, 0], [$turn->toAct(), $turn->round, $turn->current]);
    }

    public function testASimultaneousRoundEndsWhenEverySeatHasEndedItsTurn(): void
    {
        $turn = Turn::start(TurnOrder::Simultaneous, ['ann', 'bob', 'cy'], []);
        $turn = $turn->end('bob');

        self::assertSame(['ann', 'cy'], $turn->toAct());
        self::assertFalse($turn->allows('bob'));
        self::assertSame(1, $turn->round);
        $turn = $turn->end('ann')->end('cy');
        self::assertSame([['ann', 'bob', 'cy'], 2], [$turn->toAct(), $turn->round]);
        $turn = $turn->end(null);
        self::assertSame([['ann', 'bob', 'cy'], 3], [$turn->toAct(), $turn->round]);
    }

    public function testMovesAreCountedAndEndATurnAtTheirNumber(): void
    {
        $turn = Turn::start(TurnOrder::Sequential, ['ann', 'bob'], []);

        self::assertSame(1, $turn->counted('ann', null)->moves);
        self::assertSame(['ann'], $turn->counted('ann', 2)->toAct());
        self::assertSame(1, $turn->counted('ann', 2)->moves);
        self::assertSame(['bob'], $turn->counted('ann', 2)->counted('ann', 2)->toAct());
        self::assertSame(0, $turn->counted('ann', 2)->counted('ann', 2)->moves);
        self::assertSame(['bob'], $turn->counted('ann', 1)->toAct());
        $simultaneous = Turn::start(TurnOrder::Simultaneous, ['ann', 'bob'], [])->counted('bob', 1);
        self::assertSame(['ann'], $simultaneous->toAct());
        $free = Turn::start(TurnOrder::Free, ['ann', 'bob'], []);
        self::assertSame($free, $free->counted('ann', 1));
    }

    public function testTheInitiativeReordersTheRoundAndASeatDelaysItsTurn(): void
    {
        $turn = Turn::start(TurnOrder::Initiative, self::SEATS, [])->end('ann')->end('bob')->end('cy')->end('dee')->end('ann');

        $reordered = $turn->reordered(self::SEATS, ['cy' => 15, 'ann' => 3]);
        self::assertSame([['cy', 'ann', 'bob', 'dee'], 0, 2], [$reordered->order, $reordered->current, $reordered->round]);

        $delayed = Turn::start(TurnOrder::Initiative, self::SEATS, [])->end('ann')->delayed('dee');
        self::assertNotNull($delayed);
        self::assertSame([['ann', 'cy', 'dee', 'bob'], 1, ['cy']], [$delayed->order, $delayed->current, $delayed->toAct()]);
        self::assertSame(['ann', 'cy', 'bob', 'dee'], Turn::start(TurnOrder::Initiative, self::SEATS, [])->end('ann')->delayed('cy')?->order);
        self::assertNull(Turn::start(TurnOrder::Initiative, self::SEATS, [])->end('ann')->delayed('ann'));
        self::assertNull(Turn::start(TurnOrder::Initiative, self::SEATS, [])->end('ann')->delayed('bob'));
        self::assertNull(Turn::start(TurnOrder::Initiative, self::SEATS, [])->delayed('zed'));
    }
}
