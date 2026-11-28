<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\ReferenceTables\Exception\InvalidTable;
use Tilttek\ReferenceTables\Range;

#[CoversClass(Range::class)]
#[CoversClass(InvalidTable::class)]
final class RangeTest extends TestCase
{
    /**
     * @return iterable<string, array{string, bool, int, int, string}>
     */
    public static function texts(): iterable
    {
        yield 'a value' => ['12', false, 12, 12, '12'];
        yield 'a negative value' => ['-3', false, -3, -3, '-3'];
        yield 'a hyphen' => ['3-5', false, 3, 5, '3–5'];
        yield 'an en dash with spaces' => [' 3 – 5 ', false, 3, 5, '3–5'];
        yield 'an em dash' => ['3—5', false, 3, 5, '3–5'];
        yield 'negative ends' => ['-3--1', false, -3, -1, '-3–-1'];
        yield 'open upwards' => ['21+', false, 21, Range::LIMIT, '21+'];
        yield 'open upwards with a space' => ['21 +', false, 21, Range::LIMIT, '21+'];
        yield 'open downwards' => ['≤0', false, -Range::LIMIT, 0, '≤0'];
        yield 'open downwards, written <=' => ['<= -2', false, -Range::LIMIT, -2, '≤-2'];
        yield 'the end of a d100' => ['96-00', true, 96, 100, '96–100'];
        yield '00 alone on a d100' => ['00', true, 100, 100, '100'];
        yield '00 on another die' => ['00', false, 0, 0, '0'];
        yield 'a leading zero on a d100' => ['01-05', true, 1, 5, '1–5'];
        yield 'the longest text' => [str_pad('-999999–-999999', 32, ' ', \STR_PAD_BOTH), false, -999_999, -999_999, '-999999'];
    }

    #[DataProvider('texts')]
    public function testParsesARange(string $text, bool $percentile, int $min, int $max, string $printed): void
    {
        $range = Range::parse($text, $percentile);

        self::assertSame([$min, $max], [$range->min, $range->max]);
        self::assertSame($printed, $range->toString());
        self::assertSame($printed, (string) $range);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function refused(): iterable
    {
        yield 'empty' => [''];
        yield 'a word' => ['high'];
        yield 'backwards' => ['5-3'];
        yield 'beyond the limit' => ['1000001'];
        yield 'beyond the limit downwards' => ['≤-1000001'];
        yield 'beyond the limit open upwards' => ['1000001+'];
        yield 'too many digits' => ['12345678'];
        yield 'two plus signs' => ['3++'];
        yield 'a trailing text' => ['3-5x'];
        yield 'a leading text' => ['x3'];
        yield 'a leading text before an upper bound' => ['x≤3'];
        yield 'a trailing text after an upper bound' => ['≤3x'];
        yield 'a leading text before an open end' => ['x3+'];
        yield 'a line break after it' => ["3\n-"];
        yield 'longer than 32 bytes' => [str_pad('-999999–-999999', 33, ' ', \STR_PAD_BOTH)];
    }

    public function testReadsTheEndOfAD100OnlyWhenToldTo(): void
    {
        self::assertSame(0, Range::parse('00')->min);
        self::assertSame(100, Range::parse('00', true)->min);
        self::assertSame(100, Range::parse('00+', true)->min);
        self::assertSame([100, 100], [Range::parse('00-00', true)->min, Range::parse('00-00', true)->max]);
    }

    #[DataProvider('refused')]
    public function testRefusesAText(string $text): void
    {
        $this->expectException(InvalidTable::class);

        Range::parse($text);
    }

    public function testNamesWhatIsWrong(): void
    {
        $this->expectExceptionMessageIsOrContains('"high" is not a range such as "12", "3–5", "21+" or "≤0".');

        Range::parse(' high ');
    }

    public function testRefusesALongTextBeforeReadingIt(): void
    {
        $this->expectExceptionMessageIsOrContains('A range is written in 32 bytes at most.');

        Range::parse(str_repeat('1', 33));
    }

    public function testRefusesABackwardRange(): void
    {
        $this->expectExceptionMessageIsOrContains('The range 5–3 runs backwards.');

        new Range(5, 3);
    }

    public function testKeepsWithinTheLimit(): void
    {
        $range = new Range(-Range::LIMIT, Range::LIMIT);

        self::assertSame('-1000000+', $range->toString());
        self::assertSame('≤-999999', new Range(-Range::LIMIT, -999_999)->toString());
        self::assertSame(Range::LIMIT, Range::of(Range::LIMIT)->max);
    }

    public function testRefusesAnEndBeyondTheLimit(): void
    {
        $this->expectExceptionMessageIsOrContains('A range covers values from -1000000 to 1000000 only.');

        new Range(-Range::LIMIT - 1, 0);
    }

    public function testRefusesAMaximumBeyondTheLimit(): void
    {
        $this->expectException(InvalidTable::class);

        new Range(0, Range::LIMIT + 1);
    }

    public function testContainsItsEnds(): void
    {
        $range = new Range(3, 5);

        self::assertFalse($range->contains(2));
        self::assertTrue($range->contains(3));
        self::assertTrue($range->contains(5));
        self::assertFalse($range->contains(6));
    }

    public function testOverlaps(): void
    {
        $range = new Range(3, 5);

        self::assertTrue($range->overlaps(new Range(5, 8)));
        self::assertTrue($range->overlaps(new Range(1, 3)));
        self::assertTrue($range->overlaps(Range::of(4)));
        self::assertFalse($range->overlaps(new Range(6, 8)));
        self::assertFalse($range->overlaps(new Range(1, 2)));
    }
}
