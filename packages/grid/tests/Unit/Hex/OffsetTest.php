<?php

declare(strict_types=1);

namespace Tilttek\Grid\Tests\Unit\Hex;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Grid\Exception\InvalidGrid;
use Tilttek\Grid\Exception\OutOfBounds;
use Tilttek\Grid\Hex\Axial;
use Tilttek\Grid\Hex\Offset;
use Tilttek\Grid\Hex\OffsetLayout;
use Tilttek\Grid\Hex\Orientation;
use Tilttek\Grid\Limits;

#[CoversClass(Offset::class)]
#[CoversClass(OffsetLayout::class)]
#[CoversClass(Axial::class)]
final class OffsetTest extends TestCase
{
    /**
     * @return iterable<string, array{OffsetLayout, int, int, int, int}>
     */
    public static function conversions(): iterable
    {
        yield 'odd-r, even row' => [OffsetLayout::OddRows, 3, 2, 2, 2];
        yield 'odd-r, odd row' => [OffsetLayout::OddRows, 3, 5, 1, 5];
        yield 'odd-r, negative odd row' => [OffsetLayout::OddRows, 0, -1, 1, -1];
        yield 'even-r, even row' => [OffsetLayout::EvenRows, 3, 2, 2, 2];
        yield 'even-r, odd row' => [OffsetLayout::EvenRows, 3, 5, 0, 5];
        yield 'even-r, negative odd row' => [OffsetLayout::EvenRows, 0, -1, 0, -1];
        yield 'odd-q, even column' => [OffsetLayout::OddColumns, 2, 3, 2, 2];
        yield 'odd-q, odd column' => [OffsetLayout::OddColumns, 5, 3, 5, 1];
        yield 'odd-q, negative odd column' => [OffsetLayout::OddColumns, -1, 0, -1, 1];
        yield 'even-q, even column' => [OffsetLayout::EvenColumns, 2, 3, 2, 2];
        yield 'even-q, odd column' => [OffsetLayout::EvenColumns, 5, 3, 5, 0];
        yield 'even-q, negative odd column' => [OffsetLayout::EvenColumns, -1, 0, -1, 0];
    }

    #[DataProvider('conversions')]
    public function testConversions(OffsetLayout $layout, int $col, int $row, int $q, int $r): void
    {
        $offset = new Offset($col, $row);
        $axial = $offset->toAxial($layout);

        self::assertSame(['q' => $q, 'r' => $r], $axial->toArray());
        self::assertTrue($axial->toOffset($layout)->equals($offset));
    }

    public function testItIsWrittenAndReadBack(): void
    {
        self::assertSame(['col' => 4, 'row' => -2], Offset::fromArray(['col' => 4, 'row' => -2])->toArray());
        self::assertTrue(new Offset(1, 2)->equals(new Offset(1, 2)));
        self::assertFalse(new Offset(1, 2)->equals(new Offset(2, 2)));
        self::assertFalse(new Offset(1, 2)->equals(new Offset(1, 3)));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>}>
     */
    public static function badArrays(): iterable
    {
        yield 'no row' => [['col' => 1]];
        yield 'no col' => [['row' => 1]];
        yield 'text col' => [['col' => '1', 'row' => 1]];
        yield 'text row' => [['col' => 1, 'row' => '1']];
        yield 'extra key' => [['col' => 1, 'row' => 1, 'q' => 1]];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('badArrays')]
    public function testABadArrayIsRefused(array $data): void
    {
        $this->expectException(InvalidGrid::class);
        $this->expectExceptionMessageIsOrContains('Offset coordinates are written as {"col": int, "row": int}.');

        Offset::fromArray($data);
    }

    public function testColumnsAreBounded(): void
    {
        $this->expectException(OutOfBounds::class);

        new Offset(Limits::COORDINATE + 1, 0);
    }

    public function testRowsAreBounded(): void
    {
        $this->expectException(OutOfBounds::class);

        new Offset(0, Limits::COORDINATE + 1);
    }

    public function testOrientations(): void
    {
        self::assertSame(Orientation::Pointy, OffsetLayout::OddRows->orientation());
        self::assertSame(Orientation::Pointy, OffsetLayout::EvenRows->orientation());
        self::assertSame(Orientation::Flat, OffsetLayout::OddColumns->orientation());
        self::assertSame(Orientation::Flat, OffsetLayout::EvenColumns->orientation());
    }
}
