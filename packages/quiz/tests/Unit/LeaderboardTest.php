<?php

declare(strict_types=1);

namespace Tilttek\Quiz\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Quiz\AnswerResult;
use Tilttek\Quiz\Exception\InvalidQuiz;
use Tilttek\Quiz\Grade;
use Tilttek\Quiz\Leaderboard;
use Tilttek\Quiz\Standing;

#[CoversClass(Leaderboard::class)]
#[CoversClass(Standing::class)]
final class LeaderboardTest extends TestCase
{
    public function testTheHighestScoreFirstThenTheQuickestAndTiesShareARank(): void
    {
        $board = Leaderboard::of(['a', 'b', 'c', 'd', 'e'], [
            ['a' => $this->answered(100, 3000), 'b' => $this->answered(100, 2000), 'c' => $this->answered(100, 2000), 'd' => $this->answered(0, 1000)],
            ['a' => $this->answered(50, 1000), 'b' => $this->answered(0, 1000), 'c' => $this->answered(0, 1000), 'd' => $this->answered(0, 500)],
        ]);

        self::assertSame(
            [[1, 'a', 150, 2, 4000], [2, 'b', 100, 1, 2000], [2, 'c', 100, 1, 2000], [4, 'd', 0, 0, 0], [4, 'e', 0, 0, 0]],
            array_map(static fn (Standing $s): array => [$s->rank, $s->player, $s->score, $s->right, $s->time], $board->standings),
        );
        self::assertSame(['a', 'b', 'c'], array_map(static fn (Standing $s): string => $s->player, $board->top(2)));
        self::assertSame(['a'], array_map(static fn (Standing $s): string => $s->player, $board->top(1)));
        self::assertSame(4, $board->standingOf('e')?->rank);
        self::assertNull($board->standingOf('z'));
    }

    public function testEqualScoresAndTimesAreOrderedByPlayerAndShareARank(): void
    {
        $board = Leaderboard::of(['z', 'y'], [['z' => $this->answered(10, 100), 'y' => $this->answered(10, 100)]]);

        self::assertSame(['y', 'z'], array_map(static fn (Standing $s): string => $s->player, $board->standings));
        self::assertSame([1, 1], array_map(static fn (Standing $s): int => $s->rank, $board->standings));
    }

    public function testASameScoreWithASlowerTimeTakesTheNextRank(): void
    {
        $board = Leaderboard::of(['a', 'b'], [['a' => $this->answered(10, 200), 'b' => $this->answered(10, 100)]]);

        self::assertSame([[1, 'b'], [2, 'a']], array_map(static fn (Standing $s): array => [$s->rank, $s->player], $board->standings));
    }

    public function testTheTopHoldsAPlace(): void
    {
        $this->expectException(InvalidQuiz::class);
        $this->expectExceptionMessageIsOrContains('The top of the standings holds at least one place; 0 asked.');
        Leaderboard::of([], [])->top(0);
    }

    private function answered(int $points, int $elapsed): AnswerResult
    {
        return new AnswerResult('q', $points > 0 ? Grade::right() : Grade::wrong(), $points, 0, $elapsed);
    }

    public function testAQuestionMissedDoesNotHideTheNext(): void
    {
        $board = Leaderboard::of(['a', 'b'], [['b' => $this->answered(10, 100)], ['a' => $this->answered(20, 100), 'b' => $this->answered(0, 100)]]);

        $standing = $board->standingOf('a');
        self::assertInstanceOf(Standing::class, $standing);
        self::assertSame(20, $standing->score);
        self::assertSame(1, $standing->right);
    }
}
