<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Tilttek\ScoreSheets\Category;
use Tilttek\ScoreSheets\Entries;
use Tilttek\ScoreSheets\ParticipantId;
use Tilttek\ScoreSheets\Scoring\Scorer;
use Tilttek\ScoreSheets\Scoring\Step;
use Tilttek\ScoreSheets\Team;
use Tilttek\ScoreSheets\TeamId;

/**
 * Properties over random entries drawn from fixed seeds: the total is the sum of the categories that count, the ranks
 * follow the totals, and a team of the sum holds the sum of its members.
 */
#[CoversClass(Scorer::class)]
final class PropertyTest extends TestCase
{
    /**
     * @return iterable<string, array{int}>
     */
    public static function seeds(): iterable
    {
        foreach ([3, 17, 256, 4096, 90210] as $seed) {
            yield 'seed '.$seed => [$seed];
        }
    }

    #[DataProvider('seeds')]
    public function testTheTotalIsTheSumOfTheCategoriesThatCount(int $seed): void
    {
        $chance = new Randomizer(new Mt19937($seed));
        $sheet = LanternHarbor::sheet();
        $participants = array_map(static fn (int $index): ParticipantId => new ParticipantId((string) $index), range(1, $chance->getInt(1, 12)));
        $teams = \count($participants) > 1 ? [new Team(new TeamId('t1'), \array_slice($participants, 0, 2))] : [];
        $entries = new Entries($participants, $teams);
        $scorer = new Scorer();

        for ($step = 0; $step < 200; ++$step) {
            $participant = $participants[$chance->getInt(0, \count($participants) - 1)];
            $category = ['boats', 'lanterns', 'sets', 'coins'][$chance->getInt(0, 3)];
            $declared = $sheet->category($category);
            self::assertInstanceOf(Category::class, $declared);
            $entries = $entries->with($sheet, $participant, $category, $chance->getInt($declared->min, min($declared->max, 100)));
            $board = $scorer->score($sheet, $entries);
            $previous = null;

            foreach ($board->participants as $score) {
                $counted = 0;

                foreach ($sheet->categories() as $declaredCategory) {
                    $counted += $declaredCategory->counted ? $score->values[$declaredCategory->key] : 0;
                }

                self::assertSame($counted, $score->total);
                self::assertSame(array_sum(array_map(static fn (Step $step): int => $step->value, $score->steps)), array_sum($score->values));

                if (null !== $previous) {
                    self::assertGreaterThanOrEqual($score->total, $previous->total);
                    self::assertGreaterThanOrEqual($previous->rank, $score->rank);
                }

                $previous = $score;
            }

            foreach ($board->teams as $team) {
                self::assertSame(array_sum(array_column($team->members, 'total')), $team->total);
            }
        }
    }
}
