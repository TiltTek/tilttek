<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\MathTrade\Exception\InvalidTradeList;
use Tilttek\MathTrade\Want;
use Tilttek\MathTrade\WantGroup;

#[CoversClass(Want::class)]
#[CoversClass(WantGroup::class)]
final class WantTest extends TestCase
{
    public function testAWantNamesOneOffer(): void
    {
        $want = new Want('b1');

        self::assertSame('b1', $want->offer);
        self::assertSame(['b1'], $want->offers());
    }

    public function testAWantRefusesAnInvalidIdentifier(): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a wished offer is 1 to 100 bytes');

        new Want('');
    }

    public function testAGroupNamesItsOffersInOrder(): void
    {
        $group = new WantGroup(['c2', 'c1', 'c3']);

        self::assertSame(['c2', 'c1', 'c3'], $group->offers);
        self::assertSame(['c2', 'c1', 'c3'], $group->offers());
    }

    public function testAGroupOfTwoIsAccepted(): void
    {
        self::assertSame(['a', 'b'], new WantGroup(['a', 'b'])->offers());
    }

    public function testAGroupOfOneIsRefused(): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('A group of wishes names two offers or more; a single offer is a Want.');

        new WantGroup(['c1']);
    }

    public function testAnEmptyGroupIsRefused(): void
    {
        $this->expectException(InvalidTradeList::class);

        new WantGroup([]);
    }

    public function testAGroupRefusesAnOfferTwice(): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('The group of wishes names the offer "c1" twice.');

        new WantGroup(['c1', 'c2', 'c1']);
    }

    public function testAGroupRefusesAnInvalidIdentifier(): void
    {
        $this->expectException(InvalidTradeList::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a wished offer');

        new WantGroup(['c1', "c\n2"]);
    }
}
