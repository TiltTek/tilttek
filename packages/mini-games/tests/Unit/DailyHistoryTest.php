<?php

declare(strict_types=1);

namespace Tilttek\MiniGames\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\MiniGames\DailyHistory;
use Tilttek\MiniGames\DailyScore;
use Tilttek\MiniGames\Day;
use Tilttek\MiniGames\Exception\InvalidMiniGame;
use Tilttek\MiniGames\ScoreOrder;

#[CoversClass(DailyHistory::class)]
#[CoversClass(DailyScore::class)]
#[CoversClass(InvalidMiniGame::class)]
final class DailyHistoryTest extends TestCase
{
    public function testAnEmptyHistory(): void
    {
        $history = DailyHistory::of([], ScoreOrder::LowerIsBetter);

        self::assertSame(0, $history->played);
        self::assertSame(0, $history->won);
        self::assertNull($history->best);
        self::assertSame([], $history->distribution);
    }

    public function testTheDaysPlayedTheWinsTheBestAndTheDistribution(): void
    {
        $history = DailyHistory::of([
            $this->score('2026-11-01', true, 5),
            $this->score('2026-11-03', true, 3),
            $this->score('2026-11-09', false, null),
            $this->score('2026-11-10', true, 5),
        ], ScoreOrder::LowerIsBetter);

        self::assertSame(4, $history->played);
        self::assertSame(3, $history->won);
        self::assertSame(3, $history->best);
        self::assertSame([3 => 1, 5 => 2], $history->distribution);
    }

    public function testTheDistributionPutsTheBestFirst(): void
    {
        $history = DailyHistory::of([$this->score('2026-11-01', false, 3), $this->score('2026-11-02', true, 10), $this->score('2026-11-03', false, 7)], ScoreOrder::HigherIsBetter);

        self::assertSame([10 => 1, 7 => 1, 3 => 1], $history->distribution);
        self::assertSame(10, $history->best);
        self::assertSame(1, $history->won);
    }

    public function testOneDayTwiceIsRefused(): void
    {
        $this->expectException(InvalidMiniGame::class);
        $this->expectExceptionMessageIsOrContains('A history holds the scores of one game and one player, one per day.');

        DailyHistory::of([$this->score('2026-11-01', true, 5), $this->score('2026-11-01', true, 4)], ScoreOrder::LowerIsBetter);
    }

    public function testAnotherPlayerIsRefused(): void
    {
        $this->expectException(InvalidMiniGame::class);

        DailyHistory::of([$this->score('2026-11-01', true, 5), $this->score('2026-11-02', true, 4, player: 'u-2')], ScoreOrder::LowerIsBetter);
    }

    public function testAnotherGameIsRefused(): void
    {
        $this->expectException(InvalidMiniGame::class);

        DailyHistory::of([$this->score('2026-11-01', true, 5), $this->score('2026-11-02', true, 4, 'other')], ScoreOrder::LowerIsBetter);
    }

    private function score(string $day, bool $won, ?int $score, string $game = 'bulls-and-cows', string $player = 'u-1'): DailyScore
    {
        return new DailyScore($game, Day::fromString($day), $player, $won, $score, ScoreOrder::LowerIsBetter);
    }
}
