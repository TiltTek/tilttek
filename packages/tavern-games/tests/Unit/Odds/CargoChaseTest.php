<?php

declare(strict_types=1);

namespace Tilttek\TavernGames\Tests\Unit\Odds;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\TavernGames\Odds\CargoChase;
use Tilttek\TavernGames\Odds\Estimator;
use Tilttek\TavernGames\Odds\Trial;

#[CoversClass(CargoChase::class)]
final class CargoChaseTest extends TestCase
{
    public function testWithoutRollsTheCargoIsJudgedAsItIs(): void
    {
        $engine = new FacesEngine();
        $random = new Randomizer($engine);

        self::assertSame(Trial::Tie, new CargoChase(3, [3, 4], 0, 7)->trial($random));
        self::assertSame(Trial::Win, new CargoChase(3, [3, 4], 0, 6)->trial($random));
        self::assertSame(Trial::Loss, new CargoChase(3, [3, 4], 0, 8)->trial($random));
        self::assertSame(Trial::Tie, new CargoChase(2, [3, 4, 2], 0, 0)->trial($random));
        self::assertSame(Trial::Loss, new CargoChase(0, [], 0, 3)->trial($random));
        self::assertSame(0, $engine->draws);
    }

    public function testACargoAboveTheTargetStopsTheRolls(): void
    {
        $engine = new FacesEngine();

        self::assertSame(Trial::Win, new CargoChase(3, [6, 6], 2, 11)->trial(new Randomizer($engine)));
        self::assertSame(0, $engine->draws);
    }

    public function testFiveDiceAreRolledWithNothingSetAside(): void
    {
        $engine = new FacesEngine([6, 5, 4, 3, 3]);

        self::assertSame(Trial::Win, new CargoChase(0, [], 1, 5)->trial(new Randomizer($engine)));
        self::assertSame(5, $engine->draws);
    }

    public function testTheDiceNotSetAsideAreRolled(): void
    {
        $captain = new FacesEngine([5, 4, 2, 2]);
        self::assertSame(Trial::Win, new CargoChase(1, [], 1, 3)->trial(new Randomizer($captain)));
        self::assertSame(4, $captain->draws);

        $crew = new FacesEngine([4, 1, 1]);
        self::assertSame(Trial::Tie, new CargoChase(2, [], 1, 2)->trial(new Randomizer($crew)));
        self::assertSame(3, $crew->draws);
    }

    public function testTheCargoIsRolledAgainUntilItBeatsTheTarget(): void
    {
        $engine = new FacesEngine([6, 6]);

        self::assertSame(Trial::Win, new CargoChase(3, [1, 1], 2, 5)->trial(new Randomizer($engine)));
        self::assertSame(2, $engine->draws);
    }

    public function testAnEqualCargoIsRolledAgain(): void
    {
        $engine = new FacesEngine([1, 1]);

        self::assertSame(Trial::Loss, new CargoChase(3, [3, 3], 1, 6)->trial(new Randomizer($engine)));
        self::assertSame(2, $engine->draws);
    }

    public function testEveryRollLeftIsUsedUntilTheTargetIsBeaten(): void
    {
        $engine = new FacesEngine([1, 1, 2, 1, 6, 6]);

        self::assertSame(Trial::Win, new CargoChase(3, [1, 1], 3, 5)->trial(new Randomizer($engine)));
        self::assertSame(6, $engine->draws);
    }

    public function testACargoOfTwelveCannotBeBeaten(): void
    {
        $estimate = Estimator::estimate(new CargoChase(0, [], 3, 12), 500, new Randomizer(new Xoshiro256StarStar(7)));

        self::assertSame(0, $estimate->wins);
        self::assertSame(500, $estimate->samples());
        self::assertSame(3, new CargoChase(3, [1, 2], 3, 12)->rolls);
        self::assertSame(12, new CargoChase(3, [1, 2], 3, 12)->target);
        self::assertSame([1, 2], new CargoChase(3, [1, 2], 3, 12)->dice);
        self::assertSame(3, new CargoChase(3, [1, 2], 3, 12)->held);
    }
}
