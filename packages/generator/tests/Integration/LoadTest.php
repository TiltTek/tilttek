<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Exception\LimitExceeded;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Limits;
use Tilttek\Generator\Tests\Unit\Fixture\Catalogs;
use Tilttek\Generator\Tests\Unit\Fixture\SteppingTimer;

/**
 * The costliest draws a caller of the API may ask for, on the default limits: each ends within the time of a draw,
 * whatever its count and its uniqueness. The clock moves forward each time it is read, so the work of a draw is
 * counted in readings of the clock rather than measured on a wall clock that a busy machine slows down: a draw that
 * reads the clock before each piece of work, and stops at the time of the draw, does a bounded amount of work.
 */
#[CoversClass(Generator::class)]
final class LoadTest extends TestCase
{
    /** The milliseconds the clock moves forward at each reading: a map of 2,500 cells stays within one result. */
    private const float STEP = 0.05;

    public function testAUniqueDrawOfLargeMapsStopsAtTheTimeOfTheDraw(): void
    {
        // Fifty by fifty cells that pick nothing — no pick to count, no reading of the clock but the renders —, twenty
        // maps, ten attempts each: seconds of work for one request if the renders were not timed.
        $timer = new SteppingTimer(self::STEP);
        $generator = Catalogs::generator([], ['map' => ['map' => ['layout' => 'square', 'width' => 50, 'height' => 50, 'cell' => '{{ x }}-{{ y }}']]], timer: $timer);
        $limit = new Limits()->drawMilliseconds;

        try {
            $generator->draw('map', new Draw(20, true));
            self::fail('A unique draw of twenty identical maps stops at the time of the draw.');
        } catch (LimitExceeded $limitExceeded) {
            self::assertSame(LimitExceeded::of('milliseconds per draw', $limit)->getMessage(), $limitExceeded->getMessage());
        }

        // The draw reads the clock when it starts, once per cell it renders, and stops at the first reading past its
        // time: no more readings, and so no more renders, than the time of the draw holds.
        self::assertLessThanOrEqual((int) ($limit / self::STEP) + 2, $timer->reads);
    }
}
