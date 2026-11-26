<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\ScoreSheets\Category;
use Tilttek\ScoreSheets\Direction;
use Tilttek\ScoreSheets\Entries;
use Tilttek\ScoreSheets\Exception\InvalidEntry;
use Tilttek\ScoreSheets\Operation\Product;
use Tilttek\ScoreSheets\ParticipantId;
use Tilttek\ScoreSheets\Scoring\ParticipantScore;
use Tilttek\ScoreSheets\Scoring\Scoreboard;
use Tilttek\ScoreSheets\Scoring\Scorer;
use Tilttek\ScoreSheets\Scoring\TeamScore;
use Tilttek\ScoreSheets\SheetDefinition;
use Tilttek\ScoreSheets\Team;
use Tilttek\ScoreSheets\TeamCombine;
use Tilttek\ScoreSheets\TeamId;
use Tilttek\ScoreSheets\Tiebreaker;

#[CoversClass(Scorer::class)]
#[CoversClass(Scoreboard::class)]
#[CoversClass(ParticipantScore::class)]
#[CoversClass(TeamScore::class)]
final class ScorerTest extends TestCase
{
    /**
     * @param array<int|string, array<string, int>> $values
     * @param list<Team>                            $teams
     */
    public static function entries(array $values, array $teams = []): Entries
    {
        $sheet = LanternHarbor::sheet();
        $entries = new Entries(array_map(static fn (int|string $id): ParticipantId => new ParticipantId((string) $id), array_keys($values)), $teams);

        foreach ($values as $participant => $categories) {
            foreach ($categories as $category => $value) {
                $entries = $entries->with($sheet, new ParticipantId((string) $participant), $category, $value);
            }
        }

        return $entries;
    }

    /**
     * @return array<int|string, array<string, int>>
     */
    public static function evening(): array
    {
        return [
            'ada' => ['boats' => 5, 'lanterns' => 12, 'sets' => 2, 'coins' => 3],
            'bo' => ['boats' => 7, 'lanterns' => 8, 'sets' => 1, 'coins' => 1],
            '42' => ['boats' => 5, 'lanterns' => 12, 'sets' => 2, 'coins' => 3],
            'cy' => ['boats' => 2, 'lanterns' => 10, 'sets' => 3, 'coins' => 9],
        ];
    }

    public function testEveryCategoryIsComputedAndExplained(): void
    {
        $board = new Scorer()->score(LanternHarbor::sheet(), self::entries(self::evening()));
        $ada = $board->participant(new ParticipantId('ada'));

        self::assertSame('lantern-harbor', $board->game);
        self::assertTrue($board->complete);
        self::assertSame(['boats' => 5, 'lanterns' => 12, 'harbor' => 17, 'sets' => 2, 'set_points' => 8, 'fleet' => 3, 'lighthouse' => 5, 'coins' => 3], $ada?->values);
        self::assertSame(33, $ada->total);
        self::assertSame([
            ['category' => 'boats', 'operation' => 'entered', 'operands' => [], 'value' => 5],
            ['category' => 'lanterns', 'operation' => 'entered', 'operands' => [], 'value' => 12],
            ['category' => 'harbor', 'operation' => 'sum', 'operands' => ['boats' => 5, 'lanterns' => 12], 'value' => 17],
            ['category' => 'sets', 'operation' => 'entered', 'operands' => [], 'value' => 2],
            ['category' => 'set_points', 'operation' => 'product', 'operands' => ['sets' => 2, '#factor' => 4], 'value' => 8],
            ['category' => 'fleet', 'operation' => 'majority', 'operands' => ['boats' => 5, '#place' => 2], 'value' => 3],
            ['category' => 'lighthouse', 'operation' => 'bonus', 'operands' => ['lanterns' => 12, '#threshold' => 10], 'value' => 5],
            ['category' => 'coins', 'operation' => 'entered', 'operands' => [], 'value' => 3],
        ], $ada->toArray()['steps']);
        self::assertSame(25, $board->participant(new ParticipantId('bo'))?->total);
        self::assertSame(29, $board->participant(new ParticipantId('cy'))?->total);
        self::assertNull($board->participant(new ParticipantId('zed')));
    }

    public function testParticipantsAreRankedByTotalThenTiebreakersThenShare(): void
    {
        $board = new Scorer()->score(LanternHarbor::sheet(), self::entries(self::evening()));

        self::assertSame([['ada', 1, 33], ['42', 1, 33], ['cy', 3, 29], ['bo', 4, 25]], array_map(static fn (ParticipantScore $score): array => [$score->participant->value, $score->rank, $score->total], $board->participants));

        $coins = self::evening();
        $coins[42]['coins'] = 4;
        self::assertSame(['42', 'ada'], array_map(static fn (ParticipantScore $score): string => $score->participant->value, \array_slice(new Scorer()->score(LanternHarbor::sheet(), self::entries($coins))->participants, 0, 2)));
        self::assertSame([1, 2], array_map(static fn (ParticipantScore $score): int => $score->rank, \array_slice(new Scorer()->score(LanternHarbor::sheet(), self::entries($coins))->participants, 0, 2)));
    }

    public function testTheTiebreakersAreReadInOrderAndInTheirDirection(): void
    {
        $sheet = new SheetDefinition('g', [new Category('points'), new Category('a', counted: false), new Category('b', counted: false)], [new Tiebreaker('a', Direction::Fewest), new Tiebreaker('b')]);
        $score = static function (array $values) use ($sheet): array {
            $entries = new Entries([new ParticipantId('x'), new ParticipantId('y')]);

            foreach ($values as $participant => $categories) {
                foreach ($categories as $category => $value) {
                    $entries = $entries->with($sheet, new ParticipantId($participant), $category, $value);
                }
            }

            return array_map(static fn (ParticipantScore $score): string => $score->participant->value.$score->rank, new Scorer()->score($sheet, $entries)->participants);
        };

        self::assertSame(['y1', 'x2'], $score(['x' => ['points' => 5, 'a' => 2, 'b' => 9], 'y' => ['points' => 5, 'a' => 1, 'b' => 0]]));
        self::assertSame(['y1', 'x2'], $score(['x' => ['points' => 5, 'a' => 1, 'b' => 0], 'y' => ['points' => 5, 'a' => 1, 'b' => 3]]));
        self::assertSame(['x1', 'y1'], $score(['x' => ['points' => 5, 'a' => 1, 'b' => 3], 'y' => ['points' => 5, 'a' => 1, 'b' => 3]]));
        self::assertSame(['x1', 'y2'], $score(['x' => ['points' => 6, 'a' => 9, 'b' => 0], 'y' => ['points' => 5, 'a' => 0, 'b' => 9]]));
        self::assertSame(['y1', 'x2'], $score(['x' => ['points' => -1], 'y' => ['points' => 0]]));
    }

    public function testTeamsAreComposedOfTheirMembers(): void
    {
        $teams = [new Team(new TeamId('red'), [new ParticipantId('ada'), new ParticipantId('bo')]), new Team(new TeamId('blue'), [new ParticipantId('42'), new ParticipantId('cy')])];
        $board = new Scorer()->score(LanternHarbor::sheet(), self::entries(self::evening(), $teams));

        self::assertSame([
            ['team' => 'blue', 'members' => [['participant' => '42', 'total' => 33], ['participant' => 'cy', 'total' => 29]], 'combine' => 'sum', 'total' => 62, 'rank' => 1],
            ['team' => 'red', 'members' => [['participant' => 'ada', 'total' => 33], ['participant' => 'bo', 'total' => 25]], 'combine' => 'sum', 'total' => 58, 'rank' => 2],
        ], array_map(static fn (TeamScore $team): array => $team->toArray(), $board->teams));

        $best = SheetDefinition::fromArray(['teams' => 'best'] + LanternHarbor::sheet()->toArray());
        $bestBoard = new Scorer()->score($best, self::entries(self::evening(), $teams));
        self::assertSame([['red', 33, 1], ['blue', 33, 1]], array_map(static fn (TeamScore $team): array => [$team->team->value, $team->total, $team->rank], $bestBoard->teams));
        self::assertSame(TeamCombine::Best, $bestBoard->teams[0]->combine);
        self::assertSame([], new Scorer()->score(LanternHarbor::sheet(), self::entries(self::evening()))->teams);
    }

    public function testAMissingValueCountsAsZeroAndLeavesTheBoardIncomplete(): void
    {
        $board = new Scorer()->score(LanternHarbor::sheet(), self::entries(['ada' => ['lanterns' => 10], 'bo' => []]));

        self::assertFalse($board->complete);
        self::assertSame(21, $board->participant(new ParticipantId('ada'))?->total, 'Harbour 10, a fleet shared first with no boat each 6, lighthouse 5.');
        self::assertSame(6, $board->participant(new ParticipantId('bo'))?->total);
    }

    public function testTheBoardGoesToAnArray(): void
    {
        $array = new Scorer()->score(LanternHarbor::sheet(), self::entries(['ada' => ['boats' => 1]]))->toArray();

        self::assertSame('lantern-harbor', $array['game']);
        self::assertFalse($array['complete']);
        self::assertSame('ada', $array['participants'][0]['participant']);
        self::assertSame(1, $array['participants'][0]['rank']);
        self::assertSame(7, $array['participants'][0]['total']);
        self::assertSame([], $array['teams']);
    }

    public function testTheBoardCarriesEveryValueAndEveryTeamToAnArray(): void
    {
        $teams = [new Team(new TeamId('red'), [new ParticipantId('ada')])];
        $array = new Scorer()->score(LanternHarbor::sheet(), self::entries(['ada' => ['boats' => 1]], $teams))->toArray();

        self::assertSame(['boats' => 1, 'lanterns' => 0, 'harbor' => 1, 'sets' => 0, 'set_points' => 0, 'fleet' => 6, 'lighthouse' => 0, 'coins' => 0], $array['participants'][0]['values']);
        self::assertSame([['team' => 'red', 'members' => [['participant' => 'ada', 'total' => 7]], 'combine' => 'sum', 'total' => 7, 'rank' => 1]], $array['teams']);
    }

    public function testATotalTooLargeIsRefused(): void
    {
        $categories = array_map(static fn (int $index): Category => new Category('c'.$index, operation: new Product(['base', 'base'], 1_000_000)), range(1, 63));
        $sheet = new SheetDefinition('g', [new Category('base', counted: false), ...$categories]);
        $entries = new Entries([new ParticipantId('a')])->with($sheet, new ParticipantId('a'), 'base', 1_000_000);

        $this->expectExceptionObject(InvalidEntry::because('The category "total" is too large to compute.'));
        new Scorer()->score($sheet, $entries);
    }
}
