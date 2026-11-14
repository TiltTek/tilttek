<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Dice\Exception\InvalidDefinition;
use Tilttek\DiceBundle\Application\Contrast;

/**
 * The contrast of WCAG 2, checked against values worked out by hand, and the ink that reads on every face.
 */
#[CoversClass(Contrast::class)]
final class ContrastTest extends TestCase
{
    public function testTheRelativeLuminanceWeighsTheLinearChannels(): void
    {
        self::assertSame([0.0, 1.0], [Contrast::luminance('#000000'), Contrast::luminance('#FFFFFF')]);
        self::assertEqualsWithDelta(0.2126, Contrast::luminance('#ff0000'), 1e-12);
        self::assertEqualsWithDelta(0.7152, Contrast::luminance('#00ff00'), 1e-12);
        self::assertEqualsWithDelta(0.0722, Contrast::luminance('#0000ff'), 1e-12);
        self::assertEqualsWithDelta(0.003035269835488375, Contrast::luminance('#0a0a0a'), 1e-15);
        self::assertEqualsWithDelta(0.0033465357638991604, Contrast::luminance('#0b0b0b'), 1e-15);
        self::assertEqualsWithDelta(0.1973419970627483, Contrast::luminance('#2e8b57'), 1e-15);
        self::assertEqualsWithDelta(0.08546047599232483, Contrast::luminance('#aa0000'), 1e-15);
    }

    public function testTheRatioGoesFromOneToTwentyOneEitherWay(): void
    {
        self::assertSame(21.0, Contrast::ratio('#000000', '#ffffff'));
        self::assertSame(21.0, Contrast::ratio('#ffffff', '#000000'));
        self::assertSame(1.0, Contrast::ratio('#2e8b57', '#2e8b57'));
        self::assertEqualsWithDelta(4.542224959605253, Contrast::ratio('#767676', '#ffffff'), 1e-12);
        self::assertEqualsWithDelta(4.6232848849972035, Contrast::ratio('#000000', '#767676'), 1e-12);
    }

    public function testTheInkIsTheOfBlackAndWhiteThatReadsBetter(): void
    {
        self::assertSame(
            ['#ffffff', '#ffffff', '#000000', '#000000', '#ffffff', '#000000', '#ffffff', '#000000'],
            array_map(Contrast::readableInk(...), ['#000000', '#757575', '#767676', '#ffffff', '#aa0000', '#2e8b57', '#0000ff', '#ff0000']),
        );
    }

    public function testEveryFaceGetsAnInkOfAtLeastFourAndAHalfToOne(): void
    {
        $lowest = 21.0;

        for ($channel = 0; $channel < 256; $channel += 17) {
            foreach (['#%1$02x%1$02x%1$02x', '#%1$02x0000', '#00%1$02x00', '#0000%1$02x', '#%1$02x%1$02x00', '#00%1$02x%1$02x', '#%1$02x00%1$02x'] as $format) {
                $face = \sprintf($format, $channel);
                $lowest = min($lowest, Contrast::ratio($face, Contrast::readableInk($face)));
            }
        }

        self::assertGreaterThanOrEqual(4.5, $lowest);
    }

    public function testAColourNotWrittenWithSixDigitsIsRefused(): void
    {
        foreach (['#fff', 'ffffff', '#gggggg', "#ffffff\n", '#ffffff0'] as $colour) {
            try {
                Contrast::luminance($colour);
                self::fail(\sprintf('"%s" was read.', $colour));
            } catch (InvalidDefinition $exception) {
                self::assertSame(\sprintf('A colour is written #rrggbb; "%s" is not.', $colour), $exception->getMessage());
            }
        }
    }
}
