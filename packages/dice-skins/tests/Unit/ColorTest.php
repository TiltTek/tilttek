<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceSkins\Color;
use Tilttek\DiceSkins\Exception\InvalidSkin;

#[CoversClass(Color::class)]
#[CoversClass(InvalidSkin::class)]
final class ColorTest extends TestCase
{
    public function testAColourIsWrittenInSixLowerCaseDigits(): void
    {
        self::assertSame('#aabbcc', Color::fromHex('#ABC')->hex);
        self::assertSame('#a1b2c3', Color::fromHex('#A1b2C3')->hex);
        self::assertSame('#000000', Color::black()->hex);
        self::assertSame('#ffffff', Color::white()->hex);
        self::assertTrue(Color::fromHex('#FFF')->equals(Color::white()));
        self::assertFalse(Color::fromHex('#fffffe')->equals(Color::white()));
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notColours(): iterable
    {
        yield 'empty' => [''];
        yield 'no hash' => ['ffffff'];
        yield 'four digits' => ['#ffff'];
        yield 'eight digits' => ['#ffffffff'];
        yield 'not hexadecimal' => ['#ggg'];
        yield 'a name' => ['red'];
        yield 'a new line after' => ["#ffffff\n"];
        yield 'text before' => ['x#ffffff'];
        yield 'text after' => ['#fffffff'];
    }

    #[DataProvider('notColours')]
    public function testAnythingElseIsRefused(string $text): void
    {
        $this->expectException(InvalidSkin::class);
        $this->expectExceptionMessageIsOrContains('A colour is written #rgb or #rrggbb; "'.$text.'" is not.');

        Color::fromHex($text);
    }

    public function testTheRefusalQuotesSixteenCharactersAtMost(): void
    {
        $this->expectExceptionMessageIsOrContains('"#0123456789abcde" is not.');

        Color::fromHex('#0123456789abcdef0123');
    }

    public function testTheLuminanceFollowsWcag(): void
    {
        self::assertSame(1.0, round(Color::white()->luminance(), 12));
        self::assertSame(0.0, Color::black()->luminance());
        // 10/255 is under the linear threshold of 0.04045, 11/255 above it.
        self::assertEqualsWithDelta(0.003035269835, Color::fromHex('#0a0a0a')->luminance(), 1e-12);
        self::assertEqualsWithDelta(0.003346535764, Color::fromHex('#0b0b0b')->luminance(), 1e-12);
        self::assertEqualsWithDelta(0.2126, Color::fromHex('#ff0000')->luminance(), 1e-12);
        self::assertEqualsWithDelta(0.7152, Color::fromHex('#00ff00')->luminance(), 1e-12);
        self::assertEqualsWithDelta(0.0722, Color::fromHex('#0000ff')->luminance(), 1e-12);
        self::assertEqualsWithDelta(0.079658286327, Color::fromHex('#9b1b30')->luminance(), 1e-12);
    }

    public function testTheContrastRatioIsTheSameBothWays(): void
    {
        self::assertEqualsWithDelta(21.0, Color::black()->contrastWith(Color::white()), 1e-9);
        self::assertEqualsWithDelta(21.0, Color::white()->contrastWith(Color::black()), 1e-9);
        self::assertSame(1.0, Color::fromHex('#9b1b30')->contrastWith(Color::fromHex('#9b1b30')));
        self::assertEqualsWithDelta(4.478089453577, Color::fromHex('#777777')->contrastWith(Color::white()), 1e-9);
        self::assertEqualsWithDelta(4.542224959605, Color::white()->contrastWith(Color::fromHex('#767676')), 1e-9);
        self::assertEqualsWithDelta(2.593165726533, Color::fromHex('#9b1b30')->contrastWith(Color::black()), 1e-9);
    }

    public function testTheBestInkIsBlackOrWhite(): void
    {
        self::assertSame('#ffffff', Color::fromHex('#9b1b30')->bestInk()->hex);
        self::assertSame('#000000', Color::fromHex('#777777')->bestInk()->hex);
        self::assertSame('#000000', Color::white()->bestInk()->hex);
        self::assertSame('#ffffff', Color::black()->bestInk()->hex);
    }
}
