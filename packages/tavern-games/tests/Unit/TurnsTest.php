<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Exception\InvalidGame;
use Tilttek\TavernGames\Turns;

#[CoversClass(Turns::class)]
final class TurnsTest extends TestCase
{
    public function testTheFirstSeatPlaysFirst(): void
    {
        $turns = Turns::start(['ann', 'bob', 'cid']);

        self::assertSame('ann', $turns->seat());
        self::assertTrue($turns->isTurnOf('ann'));
        self::assertFalse($turns->isTurnOf('bob'));
        self::assertFalse($turns->isTurnOf(null));
        self::assertSame(['order' => ['ann', 'bob', 'cid'], 'current' => 0], $turns->toData());
    }

    public function testTheTurnGoesRoundTheTable(): void
    {
        $turns = Turns::start(['ann', 'bob', 'cid'])->next();

        self::assertSame('bob', $turns->seat());
        self::assertSame('cid', $turns->next()->seat());
        self::assertSame('ann', $turns->next()->next()->seat());
        self::assertSame(1, $turns->current);
    }

    public function testTheTurnsAreReadBack(): void
    {
        $turns = Turns::fromData(['order' => ['ann', 'bob'], 'current' => 1]);

        self::assertSame('bob', $turns->seat());
        self::assertSame(['ann', 'bob'], $turns->order);
        self::assertSame(0, Turns::fromData(['order' => ['ann'], 'current' => 0])->current);
    }

    public function testThePlaceOfASeat(): void
    {
        $turns = Turns::start(['ann', 'bob']);

        self::assertSame(0, $turns->placeOf('ann'));
        self::assertSame(1, $turns->placeOf('bob'));
    }

    public function testASeatNotAtTheTableHasNoPlace(): void
    {
        $this->expectException(InvalidGame::class);
        $this->expectExceptionMessageIsOrContains('The seat "cid" is not at the table.');

        Turns::start(['ann', 'bob'])->placeOf('cid');
    }

    public function testTurnsNeedASeat(): void
    {
        $this->expectException(InvalidGame::class);
        $this->expectExceptionMessageIsOrContains('The turns of a tavern game need a seat.');

        Turns::start([]);
    }

    /**
     * @return iterable<string, array{mixed}>
     */
    public static function corrupted(): iterable
    {
        yield 'not a mapping' => ['ann'];
        yield 'null' => [null];
        yield 'no order' => [['current' => 0]];
        yield 'no current' => [['order' => ['ann']]];
        yield 'an empty order' => [['order' => [], 'current' => 0]];
        yield 'an order that is a text' => [['order' => 'ann', 'current' => 0]];
        yield 'a current that is a text' => [['order' => ['ann'], 'current' => '0']];
        yield 'a current below 0' => [['order' => ['ann'], 'current' => -1]];
        yield 'a current past the last seat' => [['order' => ['ann', 'bob'], 'current' => 2]];
    }

    #[DataProvider('corrupted')]
    public function testCorruptedTurnsAreRefused(mixed $data): void
    {
        $this->expectException(InvalidGame::class);
        $this->expectExceptionMessageIsOrContains('The turns of a tavern game are seats and the place of the one playing.');

        Turns::fromData($data);
    }

    public function testASeatThatIsNotATextIsRefused(): void
    {
        $this->expectException(InvalidGame::class);
        $this->expectExceptionMessageIsOrContains('A seat of the turns of a tavern game is a text.');

        Turns::fromData(['order' => ['ann', 7], 'current' => 0]);
    }
}
