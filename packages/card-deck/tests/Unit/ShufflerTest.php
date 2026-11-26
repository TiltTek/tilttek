<?php

declare(strict_types=1);

namespace Tilttek\CardDeck\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\CardDeck\Shuffler;
use Tilttek\Dice\Fairness\Seed;

#[CoversClass(Shuffler::class)]
final class ShufflerTest extends TestCase
{
    public function testTheOrderIsPinnedForASeedAndANumber(): void
    {
        $seed = Seed::fromHex(str_repeat('ab', 32));

        self::assertSame([2, 3, 7, 9, 6, 5, 1, 4, 10, 8], Shuffler::shuffle(range(1, 10), $seed->randomizer('', 0)));
    }

    public function testTheFirstPlaceIsSwappedToo(): void
    {
        $seed = Seed::fromHex(str_repeat('ab', 32));

        self::assertSame(['b', 'a'], Shuffler::shuffle(['a', 'b'], $seed->randomizer('', 2)));
        self::assertSame(['a', 'b'], Shuffler::shuffle(['a', 'b'], $seed->randomizer('', 0)));
        self::assertSame(['c', 'b', 'a'], Shuffler::shuffle(['a', 'b', 'c'], $seed->randomizer('', 2)));
        self::assertSame(['b', 'c', 'a'], Shuffler::shuffle(['a', 'b', 'c'], $seed->randomizer('', 1)));
    }

    public function testNothingOrOneItemIsLeftAsItIs(): void
    {
        $seed = Seed::fromHex(str_repeat('ab', 32));

        self::assertSame([], Shuffler::shuffle([], $seed->randomizer()));
        self::assertSame(['x'], Shuffler::shuffle(['x'], $seed->randomizer()));
    }
}
