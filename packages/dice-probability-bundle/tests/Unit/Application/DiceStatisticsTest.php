<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RoundingMode;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Limits;
use Tilttek\DiceProbability\Event\AnyOf;
use Tilttek\DiceProbability\Event\Count;
use Tilttek\DiceProbability\Event\OfAKind;
use Tilttek\DiceProbability\Probabilities;
use Tilttek\DiceProbability\Settings;
use Tilttek\DiceProbabilityBundle\Application\DiceStatistics;
use Tilttek\DiceProbabilityBundle\Application\Exception\GroupsTooCostly;
use Tilttek\DiceProbabilityBundle\Application\Exception\TooManyOutcomes;
use Tilttek\DiceProbabilityBundle\Application\GroupSearch;
use Tilttek\DiceProbabilityBundle\Application\Rounding;

#[CoversClass(DiceStatistics::class)]
#[CoversClass(Rounding::class)]
#[CoversClass(TooManyOutcomes::class)]
final class DiceStatisticsTest extends TestCase
{
    public function testADistributionReadInALanguage(): void
    {
        $distribution = $this->statistics()->distribution('2W6', 'de');

        self::assertSame('6/36', (string) $distribution->probability(7));
    }

    public function testTheConfiguredRoundingAppliesUnlessAnotherIsGiven(): void
    {
        self::assertSame([0, 1, 2], $this->statistics()->distribution('1d6/3')->values());
        self::assertSame([1, 2], $this->statistics(Rounding::Ceil)->distribution('1d6/3')->values());
        self::assertSame([0, 1, 2], $this->statistics(Rounding::Ceil)->distribution('1d6/3', rounding: Rounding::Floor)->values());
        self::assertSame('20/36', (string) $this->statistics(Rounding::Ceil)->compare('1d6/4', '1d6/4')->tie);
        self::assertSame('20/36', (string) $this->statistics()->compare('1d6/4', '1d6/4', rounding: Rounding::Ceil)->tie);
        self::assertSame('18/36', (string) $this->statistics()->compare('1d6/4', '1d6/4')->tie);
    }

    public function testTwoExpressionsAgainstEachOther(): void
    {
        $versus = $this->statistics()->compare('1W20+5', '2W10+4', 'de');

        self::assertSame('1000/2000', (string) $versus->first);
        self::assertSame('900/2000', (string) $versus->second);
    }

    public function testAnEventOnASetOfDice(): void
    {
        self::assertSame('156/216', (string) $this->statistics()->chance(new AnyOf(Count::atLeastOne(1), OfAKind::double()), '3W6', 'de')->probability);
    }

    public function testADistributionOfTooManyValuesIsRefused(): void
    {
        $statistics = new DiceStatistics(new DiceRoller(), new Probabilities(), maxOutcomes: 11);

        self::assertCount(11, $statistics->distribution('2d6')->weights);
        self::assertSame(500, DiceStatistics::MAX_OUTCOMES);
        self::assertCount(991, new DiceStatistics(new DiceRoller(), new Probabilities(), maxOutcomes: 991)->distribution('10d100')->weights);

        try {
            $statistics->compare('2d6', '3d6');
            self::fail('The second distribution has 16 values.');
        } catch (TooManyOutcomes $tooManyOutcomes) {
            self::assertSame('The distribution of "3d6" has 16 values; at most 11 are drawn.', $tooManyOutcomes->getMessage());
            self::assertSame('refusal.outcomes', $tooManyOutcomes->key());
            self::assertSame(['%outcomes%' => 16, '%limit%' => 11], $tooManyOutcomes->parameters());
        }

        $this->expectException(TooManyOutcomes::class);
        $this->expectExceptionMessageIsOrContains('The distribution of "10d1000" has 9991 values; at most 500 are drawn.');

        $this->statistics()->distribution('10d1000');
    }

    /**
     * Groups whose search took seconds over the samples of an estimate are refused before the library rolls them.
     */
    public function testTheCostliestGroupsAreRefusedBeforeTheyAreRolled(): void
    {
        $statistics = $this->statistics();

        foreach (['12d6g12', '10d10g10'] as $expression) {
            $start = hrtime(true);

            foreach ([static fn (): mixed => $statistics->distribution($expression), static fn (): mixed => $statistics->chance(Count::atLeastOne(6), $expression)] as $ask) {
                try {
                    $ask();
                    self::fail(\sprintf('The groups of %s are refused.', $expression));
                } catch (GroupsTooCostly $refused) {
                    self::assertStringStartsWith(\sprintf('The groups of "%s" would take', $expression), $refused->getMessage());
                }
            }

            self::assertLessThan(1.0, (hrtime(true) - $start) / 1e9, $expression);
        }
    }

    public function testTheGroupsAreWeighedByTheGivenSearch(): void
    {
        $probabilities = new Probabilities(settings: new Settings(samples: 50));

        self::assertTrue(new DiceStatistics(new DiceRoller(), $probabilities, groups: new GroupSearch(samples: 50, workLimit: 50 * 64))->distribution('4d6g6')->isEstimated());
        self::assertTrue(new DiceStatistics(new DiceRoller(), $probabilities, groups: new GroupSearch(samples: 50, workLimit: 50 * 64))->chance(Count::atLeastOne(6), '4d6g6')->isEstimated());

        $this->expectException(GroupsTooCostly::class);

        new DiceStatistics(new DiceRoller(), $probabilities, groups: new GroupSearch(samples: 50, workLimit: 50 * 64 - 1))->chance(Count::atLeastOne(6), '4d6g6');
    }

    public function testTheCanonicalFormOrTheExpressionAsWritten(): void
    {
        self::assertSame('4d6kh3', $this->statistics()->canonical('4D6KH3'));
        self::assertSame('2d6', $this->statistics()->canonical('2W6', 'de'));
        self::assertSame('2x6', $this->statistics()->canonical('2x6'));
        self::assertSame('9d6', new DiceStatistics(new DiceRoller(new Limits(dice: 5)), new Probabilities())->canonical('9d6'));
    }

    public function testAnInvalidExpressionIsRefused(): void
    {
        $this->expectException(InvalidExpression::class);

        $this->statistics()->distribution('2x6');
    }

    public function testEachRoundingNamesAMode(): void
    {
        self::assertSame(RoundingMode::NegativeInfinity, Rounding::Floor->mode());
        self::assertSame(RoundingMode::PositiveInfinity, Rounding::Ceil->mode());
        self::assertSame(RoundingMode::HalfAwayFromZero, Rounding::Round->mode());
        self::assertSame(RoundingMode::TowardsZero, Rounding::TowardsZero->mode());
        self::assertSame(RoundingMode::AwayFromZero, Rounding::AwayFromZero->mode());
        self::assertSame(['floor', 'ceil', 'round', 'towards_zero', 'away_from_zero'], array_map(static fn (Rounding $rounding): string => $rounding->value, Rounding::cases()));
    }

    private function statistics(Rounding $rounding = Rounding::Floor): DiceStatistics
    {
        return new DiceStatistics(new DiceRoller(), new Probabilities(), $rounding);
    }
}
