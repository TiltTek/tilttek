<?php

declare(strict_types=1);

namespace Tilttek\Generator\Tests\Integration;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Generator\Draw;
use Tilttek\Generator\Exception\LimitExceeded;
use Tilttek\Generator\Generator;
use Tilttek\Generator\SystemTimer;
use Tilttek\Generator\Tests\Unit\Fixture\Catalogs;

/**
 * The costliest draws a caller of the API may ask for, on the system's clock and the default limits: each ends within
 * the time of a draw, whatever its count and its uniqueness.
 */
#[CoversClass(Generator::class)]
final class LoadTest extends TestCase
{
    public function testAUniqueDrawOfLargeMapsStopsAtTheTimeOfTheDraw(): void
    {
        // Fifty by fifty cells that pick nothing — no pick to count, no tick of the clock but the renders —, twenty
        // maps, ten attempts each: seconds of work for one request before the renders were timed.
        $generator = Catalogs::generator([], ['map' => ['map' => ['layout' => 'square', 'width' => 50, 'height' => 50, 'cell' => '{{ x }}-{{ y }}']]], timer: new SystemTimer());
        $start = hrtime(true);

        try {
            $generator->draw('map', new Draw(20, true));
        } catch (LimitExceeded $limitExceeded) {
            self::assertMatchesRegularExpression('/milliseconds per draw/', $limitExceeded->getMessage());
        }

        self::assertLessThan(2_000_000_000, hrtime(true) - $start);
    }
}
