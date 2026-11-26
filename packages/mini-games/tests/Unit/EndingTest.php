<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\GameEngine\Outcome;
use Tilttek\GameEngine\Statistics;
use Tilttek\MiniGames\Ending;
use Tilttek\MiniGames\ScoreOrder;
use Tilttek\MiniGames\Scoring;
use Tilttek\MiniGames\Tone;

#[CoversClass(Ending::class)]
#[CoversClass(Tone::class)]
final class EndingTest extends TestCase
{
    public function testAVictoryIsCelebrated(): void
    {
        $ending = Ending::of(Outcome::won(['ann'], 'solved', new Statistics([], ['ann' => ['score' => 4]])), 'ann', new Scoring(ScoreOrder::LowerIsBetter));

        self::assertSame(Tone::Victory, $ending->tone);
        self::assertTrue($ending->tone->celebrates());
        self::assertSame(4, $ending->score);
        self::assertTrue($ending->record);
    }

    public function testAnotherEndingIsToldKindly(): void
    {
        $ending = Ending::of(Outcome::ended('missed', new Statistics([], ['ann' => ['score' => 6]])), 'ann', new Scoring(ScoreOrder::HigherIsBetter), 5);

        self::assertSame(Tone::Completed, $ending->tone);
        self::assertFalse($ending->tone->celebrates());
        self::assertSame(6, $ending->score);
        self::assertTrue($ending->record);
        self::assertSame('completed', Tone::Completed->value);
        self::assertSame('victory', Tone::Victory->value);
    }

    public function testAnEndingWithoutAScoreSetsNoRecord(): void
    {
        $ending = Ending::of(Outcome::ended('out-of-guesses'), 'ann', new Scoring(ScoreOrder::LowerIsBetter));

        self::assertNull($ending->score);
        self::assertFalse($ending->record);
    }

    public function testAnEqualOrWorseScoreSetsNoRecord(): void
    {
        $outcome = Outcome::won(['ann'], 'solved', new Statistics([], ['ann' => ['score' => 4]]));
        $scoring = new Scoring(ScoreOrder::LowerIsBetter);

        self::assertFalse(Ending::of($outcome, 'ann', $scoring, 4)->record);
        self::assertFalse(Ending::of($outcome, 'ann', $scoring, 3)->record);
        self::assertTrue(Ending::of($outcome, 'ann', $scoring, 5)->record);
    }

    public function testTheScoreIsReadFromTheStatisticOfTheScoring(): void
    {
        $outcome = Outcome::won(['ann'], 'solved', new Statistics([], ['ann' => ['score' => 4, 'seconds' => 70]]));

        self::assertSame(70, Ending::of($outcome, 'ann', new Scoring(ScoreOrder::LowerIsBetter, 'seconds'))->score);
        self::assertSame(Tone::Completed, Ending::of($outcome, 'bob', new Scoring(ScoreOrder::LowerIsBetter))->tone);
    }
}
