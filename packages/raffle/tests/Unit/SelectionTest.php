<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Raffle\Selection;

/**
 * The expected draws were computed by a separate implementation of the documented algorithm, in Python.
 */
#[CoversClass(Selection::class)]
final class SelectionTest extends TestCase
{
    public function testTheDrawIsTheDocumentedPartialShuffle(): void
    {
        $entrants = ['a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j'];

        self::assertSame(['j', 'g', 'c'], Selection::draw(str_repeat('aa', 32), $entrants, 3));
        self::assertSame(['j', 'g', 'c', 'h', 'b', 'd', 'e', 'f', 'i', 'a'], Selection::draw(str_repeat('aa', 32), $entrants, 10));
        self::assertSame(48, Selection::BITS);
    }

    public function testAValuePastTheLastWholeMultipleIsDrawnAgain(): void
    {
        // With 2 bits, a bound of 3 rejects the value 3: the seed 04… draws 3, 3, then 1 at the first step.
        self::assertSame(['y', 'z', 'x'], Selection::draw(str_repeat('04', 32), ['x', 'y', 'z'], 3, 2));
        self::assertSame(['z', 'y', 'x'], Selection::draw(str_repeat('02', 32), ['x', 'y', 'z'], 3, 2));
        self::assertSame(['y', 'z', 'x'], Selection::draw(str_repeat('02', 32), ['x', 'y', 'z'], 3, 3));
        self::assertSame(['w', 'x', 'z', 'y'], Selection::draw(str_repeat('aa', 32), ['x', 'y', 'z', 'w'], 4, 2));
    }

    public function testNothingIsDrawnForACountOfZero(): void
    {
        self::assertSame([], Selection::draw(str_repeat('aa', 32), ['a', 'b'], 0));
    }

    public function testEveryEntrantIsDrawnEquallyOften(): void
    {
        $counts = ['a' => 0, 'b' => 0, 'c' => 0];

        for ($draw = 0; $draw < 3000; ++$draw) {
            ++$counts[Selection::draw(hash('sha256', (string) $draw), ['a', 'b', 'c'], 1)[0]];
        }

        foreach ($counts as $count) {
            self::assertGreaterThan(900, $count);
            self::assertLessThan(1100, $count);
        }
    }
}
