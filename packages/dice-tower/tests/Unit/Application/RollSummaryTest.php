<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceTower\Application\RollSummary;
use Tilttek\DiceTower\Tests\Unit\Fixture\Tower;

#[CoversClass(RollSummary::class)]
final class RollSummaryTest extends TestCase
{
    public function testARecordedRollIsSummedUpInstructionByInstruction(): void
    {
        $roll = new Tower()->dice->rollReplayable('4d6kh3+1; 2d{coin}');
        $summary = RollSummary::of($roll->result());
        $result = $roll->result();

        self::assertCount(2, $summary);
        self::assertSame(['4d6kh3+1', $result[0]['total']], [$summary[0]['expression'], $summary[0]['total']]);
        self::assertSame($result[0]['output'], $summary[0]['output']);
        self::assertCount(1, $summary[0]['sets']);
        self::assertSame(['4d6kh3', null], [$summary[0]['sets'][0]['notation'], $summary[0]['sets'][0]['die']]);
        self::assertCount(4, $summary[0]['sets'][0]['dice']);
        self::assertSame([true, true, true, false], array_map(static fn (array $die): bool => $die['kept'], $this->sorted($summary[0]['sets'][0]['dice'])));
        self::assertSame($result[0]['total'], 1 + array_sum(array_map(static fn (array $die): int => $die['kept'] ? $die['value'] : 0, $summary[0]['sets'][0]['dice'])));
        self::assertSame(['2d{coin}', 'coin'], [$summary[1]['sets'][0]['notation'], $summary[1]['sets'][0]['die']]);
        self::assertSame([], array_diff(array_map(static fn (array $die): ?string => $die['face'], $summary[1]['sets'][0]['dice']), ['heads', 'tails']));
        self::assertSame([null, null, null, null], array_map(static fn (array $die): ?string => $die['face'], $summary[0]['sets'][0]['dice']));
    }

    public function testWhatHasNotTheShapeOfTheJournalIsSkipped(): void
    {
        self::assertSame([], RollSummary::of([]));
        self::assertSame([['expression' => '', 'total' => null, 'output' => '', 'sets' => []]], RollSummary::of([['expression' => 3, 'total' => '7', 'output' => 1.5, 'detail' => 'dice']]));
        self::assertSame(['x', 2], RollSummary::of([['output' => ['x', 1.5, null, 2]]])[0]['output']);
        self::assertSame('fire', RollSummary::of([['output' => 'fire']])[0]['output']);
        self::assertSame(
            [['notation' => '', 'die' => null, 'dice' => [['value' => 3, 'face' => null, 'kept' => false]]]],
            RollSummary::of([['detail' => ['type' => 'dice', 'notation' => 4, 'dice' => ['no', ['value' => '2'], ['value' => 3, 'face' => 5, 'kept' => 'yes']]]]])[0]['sets'],
        );
        self::assertSame([], RollSummary::of([['detail' => ['type' => 'dice', 'dice' => 'none']]])[0]['sets']);
        self::assertSame([], RollSummary::of([['detail' => ['type' => 'list', 'dice' => [['type' => 'dice', 'dice' => [['value' => 1]]]]]]])[0]['sets']);
    }

    public function testNestedSetsAreFoundAndEachInstructionKeepsItsOwn(): void
    {
        $set = static fn (string $notation, int $value): array => ['type' => 'dice', 'notation' => $notation, 'dice' => [['value' => $value, 'face' => null, 'kept' => true]]];
        $summary = RollSummary::of([
            ['detail' => ['type' => 'operation', 'parts' => [$set('1d6', 4), ['type' => 'operation', 'parts' => [$set('1d{coin}', 1)]]]]],
            ['detail' => $set('1d8', 5)],
        ]);

        self::assertSame(['1d6', '1d{coin}'], array_column($summary[0]['sets'], 'notation'));
        self::assertSame([null, 'coin'], array_column($summary[0]['sets'], 'die'));
        self::assertSame(['1d8'], array_column($summary[1]['sets'], 'notation'));
    }

    public function testTheReadingIsBounded(): void
    {
        $node = ['type' => 'dice', 'notation' => 'deep', 'dice' => [['value' => 1]]];
        $atTheLimit = $node;

        for ($level = 1; $level < RollSummary::DEPTH; ++$level) {
            $atTheLimit = ['part' => $atTheLimit];
        }

        self::assertSame(['deep'], array_column(RollSummary::of([['detail' => $atTheLimit]])[0]['sets'], 'notation'));
        self::assertSame([], RollSummary::of([['detail' => ['part' => $atTheLimit]]])[0]['sets']);

        $many = array_fill(0, RollSummary::DICE - 1, ['value' => 2, 'kept' => true]);
        $summary = RollSummary::of([
            ['detail' => ['type' => 'dice', 'notation' => 'a', 'dice' => $many]],
            ['detail' => ['type' => 'dice', 'notation' => 'b', 'dice' => [['value' => 3], ['value' => 4]]]],
            ['detail' => ['type' => 'dice', 'notation' => 'c', 'dice' => [['value' => 5], ['value' => 6]]]],
        ]);

        self::assertCount(RollSummary::DICE - 1, $summary[0]['sets'][0]['dice']);
        self::assertSame([3], array_column($summary[1]['sets'][0]['dice'], 'value'));
        self::assertSame([], $summary[2]['sets'][0]['dice']);
    }

    /**
     * @param list<array{value: int, face: ?string, kept: bool}> $dice
     *
     * @return list<array{value: int, face: ?string, kept: bool}>
     */
    private function sorted(array $dice): array
    {
        usort($dice, static fn (array $a, array $b): int => [$b['kept'], $b['value']] <=> [$a['kept'], $a['value']]);

        return $dice;
    }
}
