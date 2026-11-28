<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Tests\Unit;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Encounter\Difficulty\Assessment;
use Tilttek\Encounter\Difficulty\BudgetRules;
use Tilttek\Encounter\Difficulty\Declaration;
use Tilttek\Encounter\Difficulty\LevelRules;
use Tilttek\Encounter\Difficulty\Multiplier;
use Tilttek\Encounter\Exception\EncounterRefused;
use Tilttek\Encounter\Exception\InvalidEncounter;
use Tilttek\Encounter\Kind;
use Tilttek\Encounter\Participant;
use Tilttek\Encounter\Tests\Unit\Fixture\Systems;

#[CoversClass(BudgetRules::class)]
#[CoversClass(LevelRules::class)]
#[CoversClass(Declaration::class)]
#[CoversClass(Assessment::class)]
#[CoversClass(EncounterRefused::class)]
final class DifficultyTest extends TestCase
{
    private static function character(string $id, string $level): Participant
    {
        return new Participant($id, Kind::Character, $level, 'player-'.$id);
    }

    private function adversary(string $id, string $rating, int $quantity = 1): Participant
    {
        return new Participant($id, Kind::Adversary, $rating, 'gm', $quantity);
    }

    public function testTheBudgetAddsTheCharactersAmountsAndTheAdversariesCosts(): void
    {
        $rules = Systems::budget();
        $assessment = $rules->assess([
            self::character('a', '1'),
            self::character('b', '2'),
            $this->adversary('wolf', '1/2'),
            new Participant('dog', Kind::Ally, '2', 'player-a'),
        ]);

        self::assertSame('budget-game', $rules->system());
        self::assertSame(['low' => 150, 'moderate' => 225, 'high' => 300], array_column($assessment->toArray()['bands'], 'threshold', 'key'));
        self::assertSame(25, $assessment->cost);
        self::assertSame(2, $assessment->characters);
        self::assertSame(1, $assessment->adversaries);
        self::assertNull($assessment->difficulty);
        self::assertSame('budget-game', $assessment->system);
    }

    public function testTheBudgetMultipliesTheCostByTheNumberOfAdversaries(): void
    {
        $rules = Systems::budget();
        $hero = self::character('a', '2');

        // One adversary: no multiplier holds.
        self::assertSame(100, $rules->assess([$hero, $this->adversary('o', '2')])->cost);
        // Two or three: 150 %, rounded down.
        self::assertSame(30, $rules->assess([$hero, $this->adversary('g', '1/4', 2)])->cost);
        self::assertSame(52, $rules->assess([$hero, $this->adversary('g', '1/4'), $this->adversary('w', '1/2')])->cost);
        self::assertSame(45, $rules->assess([$hero, $this->adversary('g', '1/4', 3)])->cost);
        // Four and more: 200 %.
        self::assertSame(80, $rules->assess([$hero, $this->adversary('g', '1/4', 4)])->cost);
        self::assertSame(4, $rules->assess([$hero, $this->adversary('g', '1/4', 4)])->adversaries);
        self::assertSame('high', $rules->assess([$hero, $this->adversary('o', '2', 2)])->difficulty);
        self::assertSame('moderate', $rules->assess([$hero, $this->adversary('o', '1', 2)])->difficulty);
    }

    public function testTheBudgetWithoutMultiplierKeepsTheCost(): void
    {
        $rules = new BudgetRules('s', ['low'], ['1' => ['low' => 10]], ['1' => 7]);

        self::assertSame(70, $rules->assess([self::character('a', '1'), $this->adversary('g', '1', 10)])->cost);
        self::assertSame('low', $rules->assess([self::character('a', '1'), $this->adversary('g', '1', 10)])->difficulty);
    }

    public function testTheBudgetRefusesAnEncounterWithoutCharacter(): void
    {
        $this->expectException(EncounterRefused::class);
        $this->expectExceptionMessageIsOrContains('An encounter is assessed for one character or more.');

        Systems::budget()->assess([$this->adversary('g', '1'), new Participant('dog', Kind::Ally, '1', 'm')]);
    }

    public function testTheBudgetRefusesALevelItDoesNotRate(): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('The rules of budget-game give no budget to the rating 3.');

        Systems::budget()->assess([self::character('a', '3')]);
    }

    public function testTheBudgetRefusesAChallengeItDoesNotRate(): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('The rules of budget-game give no cost to the rating 1/8.');

        Systems::budget()->assess([self::character('a', '1'), $this->adversary('g', '1/8')]);
    }

    public function testTheRulesRefuseMoreParticipantsThanAnEncounterHolds(): void
    {
        $participants = array_map(static fn (int $i): Participant => self::character('c'.$i, '1'), range(0, 100));

        foreach ([Systems::budget(), Systems::level()] as $rules) {
            try {
                $rules->assess($participants);
                self::fail('Too many participants are assessed.');
            } catch (InvalidEncounter $exception) {
                self::assertSame('An encounter holds at most 100 participants.', $exception->getMessage());
            }

            self::assertSame(100, $rules->assess(\array_slice($participants, 0, 100))->characters);
        }
    }

    public function testTheBudgetTakesItsBoundsAtTheirLimits(): void
    {
        $bands = array_map(static fn (int $i): string => 'b'.$i, range(1, 12));
        $row = array_combine($bands, array_fill(0, 12, 1_000_000_000));
        $budgets = [];
        $costs = [];
        for ($i = 1; $i <= 1000; ++$i) {
            $budgets['r'.$i] = $row;
            $costs['r'.$i] = 0;
        }

        $multipliers = array_map(static fn (int $i): Multiplier => new Multiplier($i, 100), range(1, 20));
        $rules = new BudgetRules('s', $bands, $budgets, $costs, $multipliers);

        self::assertSame(1_000_000_000, $rules->assess([self::character('a', 'r1000')])->threshold('b12'));
    }

    /**
     * @return iterable<string, array{Closure(): BudgetRules, string}>
     */
    public static function invalidBudgets(): iterable
    {
        $budgets = ['1' => ['low' => 1, 'high' => 2]];

        yield 'a system' => [static fn (): BudgetRules => new BudgetRules('', ['low', 'high'], $budgets, ['1' => 1]), 'The identifier of a game system'];
        yield 'no band' => [static fn (): BudgetRules => new BudgetRules('s', [], $budgets, ['1' => 1]), 'Rules of difficulty declare 1 to 12 bands.'];
        yield 'thirteen bands' => [static fn (): BudgetRules => new BudgetRules('s', array_map(static fn (int $i): string => 'b'.$i, range(1, 13)), $budgets, ['1' => 1]), 'Rules of difficulty declare 1 to 12 bands.'];
        yield 'a band key' => [static fn (): BudgetRules => new BudgetRules('s', ['low', '2'], $budgets, ['1' => 1]), 'The identifier of a band of difficulty'];
        yield 'a band twice' => [static fn (): BudgetRules => new BudgetRules('s', ['low', 'low'], ['1' => ['low' => 1]], ['1' => 1]), 'A band of difficulty comes twice.'];
        yield 'no budget' => [static fn (): BudgetRules => new BudgetRules('s', ['low', 'high'], [], ['1' => 1]), 'The budgets hold 1 to 1000 ratings.'];
        yield 'too many budgets' => [static fn (): BudgetRules => new BudgetRules('s', ['low'], array_fill_keys(array_map(static fn (int $i): string => 'r'.$i, range(0, 1000)), ['low' => 1]), ['1' => 1]), 'The budgets hold 1 to 1000 ratings.'];
        yield 'a rating of the budgets' => [static fn (): BudgetRules => new BudgetRules('s', ['low'], ['a b' => ['low' => 1]], ['1' => 1]), 'A rating of the budgets is 1 to 16'];
        yield 'a band missing' => [static fn (): BudgetRules => new BudgetRules('s', ['low', 'high'], ['1' => ['low' => 1]], ['1' => 1]), 'The budget of the rating 1 has no amount for the band high.'];
        yield 'a band unknown' => [static fn (): BudgetRules => new BudgetRules('s', ['low'], ['1' => ['low' => 1, 'high' => 2]], ['1' => 1]), 'The budget of the rating 1 names a band the rules do not declare.'];
        yield 'an amount below 0' => [static fn (): BudgetRules => new BudgetRules('s', ['low'], ['1' => ['low' => -1]], ['1' => 1]), 'An amount of the rating 1 is 0 to 1000000000.'];
        yield 'an amount too high' => [static fn (): BudgetRules => new BudgetRules('s', ['low'], ['1' => ['low' => 1_000_000_001]], ['1' => 1]), 'An amount of the rating 1 is 0 to 1000000000.'];
        yield 'amounts out of order' => [static fn (): BudgetRules => new BudgetRules('s', ['low', 'high'], ['1' => ['low' => 2, 'high' => 1]], ['1' => 1]), 'The amounts of the rating 1 grow with the bands, from the easiest.'];
        yield 'no cost' => [static fn (): BudgetRules => new BudgetRules('s', ['low', 'high'], $budgets, []), 'The costs hold 1 to 1000 ratings.'];
        yield 'too many costs' => [static fn (): BudgetRules => new BudgetRules('s', ['low', 'high'], $budgets, array_fill_keys(array_map(static fn (int $i): string => 'r'.$i, range(0, 1000)), 1)), 'The costs hold 1 to 1000 ratings.'];
        yield 'a rating of the costs' => [static fn (): BudgetRules => new BudgetRules('s', ['low', 'high'], $budgets, ['' => 1]), 'A rating of the costs is 1 to 16'];
        yield 'a cost below 0' => [static fn (): BudgetRules => new BudgetRules('s', ['low', 'high'], $budgets, ['1/2' => -1]), 'An amount of the rating 1/2 is 0 to'];
        yield 'a cost too high' => [static fn (): BudgetRules => new BudgetRules('s', ['low', 'high'], $budgets, ['1/2' => 1_000_000_001]), 'An amount of the rating 1/2 is 0 to'];
        yield 'too many multipliers' => [static fn (): BudgetRules => new BudgetRules('s', ['low', 'high'], $budgets, ['1' => 1], array_map(static fn (int $i): Multiplier => new Multiplier($i, 100), range(1, 21))), 'Rules of difficulty declare at most 20 multipliers.'];
        yield 'multipliers out of order' => [static fn (): BudgetRules => new BudgetRules('s', ['low', 'high'], $budgets, ['1' => 1], [new Multiplier(3, 150), new Multiplier(2, 120)]), 'The multipliers are in the order of the adversaries they hold from, each number once.'];
        yield 'a multiplier twice' => [static fn (): BudgetRules => new BudgetRules('s', ['low', 'high'], $budgets, ['1' => 1], [new Multiplier(2, 150), new Multiplier(2, 120)]), 'The multipliers are in the order'];
    }

    /**
     * @param Closure(): BudgetRules $make
     */
    #[DataProvider('invalidBudgets')]
    public function testBudgetRulesOutOfTheirShapeAreRefused(Closure $make, string $message): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testTheLevelRulesCostEachAdversaryByItsLevelAboveTheParty(): void
    {
        $rules = Systems::level();
        $assessment = $rules->assess([
            self::character('a', '3'),
            self::character('b', '3'),
            self::character('c', '4'),
            self::character('d', '4'),
            $this->adversary('boss', '5'),
            $this->adversary('minions', '1', 2),
            $this->adversary('rat', '0', 3),
            new Participant('dog', Kind::Ally, '9', 'player-a'),
        ]);

        // The party is level 3; the boss costs 80, each minion 20, a rat — three levels under — nothing.
        self::assertSame('level-game', $rules->system());
        self::assertSame(120, $assessment->cost);
        self::assertSame(6, $assessment->adversaries);
        self::assertSame(4, $assessment->characters);
        self::assertSame(['trivial' => 40, 'moderate' => 80, 'severe' => 120], array_column($assessment->toArray()['bands'], 'threshold', 'key'));
        self::assertSame('severe', $assessment->difficulty);
    }

    public function testTheLevelRulesAdjustTheBudgetToTheSizeOfTheParty(): void
    {
        $rules = Systems::level();

        $five = $rules->assess(array_map(static fn (int $i): Participant => self::character('c'.$i, '1'), range(1, 5)));
        self::assertSame(['trivial' => 50, 'moderate' => 100, 'severe' => 150], array_column($five->toArray()['bands'], 'threshold', 'key'));

        $one = $rules->assess([self::character('a', '1')]);
        self::assertSame(['trivial' => 10, 'moderate' => 20, 'severe' => 30], array_column($one->toArray()['bands'], 'threshold', 'key'));

        $floored = new LevelRules('s', ['low' => 10, 'high' => 40], 4, ['low' => 5, 'high' => 10], [0 => 1]);
        self::assertSame(['low' => 0, 'high' => 10], array_column($floored->assess([self::character('a', '1')])->toArray()['bands'], 'threshold', 'key'));
    }

    public function testTheLevelRulesRoundThePartyLevelDown(): void
    {
        $rules = Systems::level();

        // Levels 2 and 3 make a party of level 2: an adversary of level 4 stands 2 above it.
        self::assertSame(80, $rules->assess([self::character('a', '2'), self::character('b', '3'), $this->adversary('o', '4')])->cost);
        // Levels -1 and 0 make a party of level -1: an adversary of level 1 stands 2 above it.
        self::assertSame(80, $rules->assess([self::character('a', '-1'), self::character('b', '0'), $this->adversary('o', '1')])->cost);
        self::assertSame(20, $rules->assess([self::character('a', '5'), $this->adversary('o', '3')])->cost);
    }

    public function testTheLevelRulesRefuseAnAdversaryFurtherAboveThanTheyRate(): void
    {
        $this->expectException(EncounterRefused::class);
        $this->expectExceptionMessageIsOrContains('The rules of level-game rate no adversary 3 levels above the party.');

        Systems::level()->assess([self::character('a', '1'), $this->adversary('o', '4')]);
    }

    public function testTheLevelRulesRefuseAnEncounterWithoutCharacter(): void
    {
        $this->expectException(EncounterRefused::class);
        $this->expectExceptionMessageIsOrContains('An encounter is assessed for one character or more.');

        Systems::level()->assess([$this->adversary('o', '1')]);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function notLevels(): iterable
    {
        yield 'a fraction' => ['1/2'];
        yield 'four digits' => ['1000'];
        yield 'a plus' => ['+1'];
        yield 'a letter' => ['A'];
        yield 'a trailing letter' => ['1a'];
        yield 'a leading letter' => ['a1'];
    }

    #[DataProvider('notLevels')]
    public function testTheLevelRulesRateWholeLevels(string $rating): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The rules of level-game rate whole levels, not %s.', $rating));

        Systems::level()->assess([self::character('a', '1'), $this->adversary('o', $rating)]);
    }

    public function testTheLevelRulesTakeThreeDigitLevels(): void
    {
        $rules = new LevelRules('s', ['low' => 1], 1, ['low' => 0], [0 => 5]);

        self::assertSame(5, $rules->assess([self::character('a', '999'), $this->adversary('o', '999')])->cost);
        self::assertSame(5, $rules->assess([self::character('a', '-99'), $this->adversary('o', '-99')])->cost);
    }

    public function testTheLevelRulesTakeTheirBoundsAtTheirLimits(): void
    {
        $bands = array_map(static fn (int $i): string => 'b'.$i, range(1, 12));
        $costs = array_fill_keys(range(-50, 50), 1_000_000_000);
        $rules = new LevelRules('s', array_fill_keys($bands, 1_000_000_000), 20, array_fill_keys($bands, -1_000_000_000), $costs);

        $assessment = $rules->assess([self::character('a', '0'), $this->adversary('o', '50'), $this->adversary('p', '-50')]);
        self::assertSame(2_000_000_000, $assessment->cost);
        self::assertSame(20_000_000_000, $assessment->threshold('b12'));
        self::assertSame(1_000_000_000, new LevelRules('s', ['low' => 0], 1, ['low' => 1_000_000_000], [0 => 0])->assess([self::character('a', '0'), self::character('b', '0')])->threshold('low'));
    }

    /**
     * @return iterable<string, array{Closure(): LevelRules, string}>
     */
    public static function invalidLevels(): iterable
    {
        $budgets = ['low' => 10, 'high' => 20];
        $adjustments = ['low' => 1, 'high' => 2];
        $costs = [-1 => 1, 0 => 2];

        yield 'a system' => [static fn (): LevelRules => new LevelRules('9', $budgets, 4, $adjustments, $costs), 'The identifier of a game system'];
        yield 'no band' => [static fn (): LevelRules => new LevelRules('s', [], 4, [], $costs), 'Rules of difficulty declare 1 to 12 bands.'];
        yield 'thirteen bands' => [static fn (): LevelRules => new LevelRules('s', array_fill_keys(array_map(static fn (int $i): string => 'b'.$i, range(1, 13)), 1), 4, array_fill_keys(array_map(static fn (int $i): string => 'b'.$i, range(1, 13)), 1), $costs), 'Rules of difficulty declare 1 to 12 bands.'];
        yield 'a band key' => [static fn (): LevelRules => new LevelRules('s', [3 => 10], 4, [3 => 1], $costs), 'The identifier of a band of difficulty'];
        yield 'a budget below 0' => [static fn (): LevelRules => new LevelRules('s', ['low' => -1], 4, ['low' => 1], $costs), 'A budget is 0 to 1000000000.'];
        yield 'a budget too high' => [static fn (): LevelRules => new LevelRules('s', ['low' => 1_000_000_001], 4, ['low' => 1], $costs), 'A budget is 0 to 1000000000.'];
        yield 'budgets out of order' => [static fn (): LevelRules => new LevelRules('s', ['low' => 20, 'high' => 10], 4, $adjustments, $costs), 'The budgets grow with the bands, from the easiest.'];
        yield 'an adjustment missing' => [static fn (): LevelRules => new LevelRules('s', $budgets, 4, ['low' => 1], $costs), 'The rules give no adjustment to the band high.'];
        yield 'an adjustment too low' => [static fn (): LevelRules => new LevelRules('s', $budgets, 4, ['low' => -1_000_000_001, 'high' => 1], $costs), 'An adjustment, either way, is 0 to 1000000000.'];
        yield 'an adjustment too high' => [static fn (): LevelRules => new LevelRules('s', $budgets, 4, ['low' => 1_000_000_001, 'high' => 1], $costs), 'An adjustment, either way, is 0 to'];
        yield 'an adjustment unknown' => [static fn (): LevelRules => new LevelRules('s', $budgets, 4, [...$adjustments, 'deadly' => 3], $costs), 'An adjustment names a band the rules do not declare.'];
        yield 'a party of 0' => [static fn (): LevelRules => new LevelRules('s', $budgets, 0, $adjustments, $costs), 'The standard size of a party is 1 to 20.'];
        yield 'a party of 21' => [static fn (): LevelRules => new LevelRules('s', $budgets, 21, $adjustments, $costs), 'The standard size of a party is 1 to 20.'];
        yield 'no cost' => [static fn (): LevelRules => new LevelRules('s', $budgets, 4, $adjustments, []), 'The rules give a cost to one level difference or more.'];
        yield 'a difference too low' => [static fn (): LevelRules => new LevelRules('s', $budgets, 4, $adjustments, [-51 => 1]), 'The costs are for differences of -50 to 50 levels.'];
        yield 'a difference too high' => [static fn (): LevelRules => new LevelRules('s', $budgets, 4, $adjustments, [51 => 1]), 'The costs are for differences of -50 to 50 levels.'];
        yield 'a gap in the differences' => [static fn (): LevelRules => new LevelRules('s', $budgets, 4, $adjustments, [-1 => 1, 1 => 2]), 'The costs give every level difference from the lowest to the highest.'];
        yield 'a cost below 0' => [static fn (): LevelRules => new LevelRules('s', $budgets, 4, $adjustments, [0 => -1]), 'A cost is 0 to 1000000000.'];
        yield 'a cost too high' => [static fn (): LevelRules => new LevelRules('s', $budgets, 4, $adjustments, [0 => 1_000_000_001]), 'A cost is 0 to 1000000000.'];
    }

    /**
     * @param Closure(): LevelRules $make
     */
    #[DataProvider('invalidLevels')]
    public function testLevelRulesOutOfTheirShapeAreRefused(Closure $make, string $message): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testADeclarationByBudgetIsRead(): void
    {
        $rules = Declaration::read([
            'method' => 'budget',
            'system' => 'my-system',
            'bands' => ['low', 'high'],
            'budgets' => ['1' => ['low' => 50, 'high' => 100], '2' => ['low' => 100, 'high' => 200]],
            'costs' => ['1/2' => 25, '1' => 50],
            'multipliers' => [['from' => 2, 'percent' => 150]],
        ]);

        self::assertInstanceOf(BudgetRules::class, $rules);
        self::assertSame('my-system', $rules->system());
        $assessment = $rules->assess([self::character('a', '2'), $this->adversary('g', '1/2', 2)]);
        self::assertSame(75, $assessment->cost);
        self::assertSame(200, $assessment->threshold('high'));
        self::assertSame(50, Declaration::read(['method' => 'budget', 'system' => 's', 'bands' => ['low'], 'budgets' => [1 => ['low' => 1]], 'costs' => [1 => 25]])->assess([self::character('a', '1'), $this->adversary('g', '1', 2)])->cost);
    }

    public function testADeclarationByLevelIsRead(): void
    {
        $rules = Declaration::read([
            'method' => 'level',
            'system' => 'my-system',
            'partySize' => 4,
            'budgets' => ['low' => 60, 'high' => 120],
            'adjustments' => ['low' => 15, 'high' => 30],
            'costs' => ['-2' => 20, '-1' => 30, '0' => 40, '1' => 60, 2 => 80],
        ]);

        self::assertInstanceOf(LevelRules::class, $rules);
        self::assertSame('my-system', $rules->system());
        $assessment = $rules->assess([self::character('a', '3'), self::character('b', '3'), $this->adversary('g', '1'), $this->adversary('o', '5')]);
        self::assertSame(100, $assessment->cost);
        self::assertSame(30, $assessment->threshold('low'));
        self::assertSame(60, $assessment->threshold('high'));
    }

    /**
     * @return iterable<string, array{array<array-key, mixed>, string}>
     */
    public static function invalidDeclarations(): iterable
    {
        $budget = ['method' => 'budget', 'system' => 's', 'bands' => ['low'], 'budgets' => ['1' => ['low' => 1]], 'costs' => ['1' => 1]];
        $level = ['method' => 'level', 'system' => 's', 'partySize' => 4, 'budgets' => ['low' => 1], 'adjustments' => ['low' => 1], 'costs' => ['0' => 1]];

        yield 'no method' => [[], 'Rules of difficulty declare their method: "budget" or "level".'];
        yield 'another method' => [['method' => 'xp'], 'Rules of difficulty declare their method'];
        yield 'an unknown key' => [[...$budget, 'deadly' => true], 'Rules of difficulty have no key deadly.'];
        yield 'an integer key' => [[...$level, 3], 'Rules of difficulty have no key 0.'];
        yield 'a missing key' => [array_diff_key($budget, ['costs' => true]), 'Rules of difficulty declare their costs.'];
        yield 'a missing level key' => [array_diff_key($level, ['partySize' => true]), 'Rules of difficulty declare their partySize.'];
        yield 'a multiplier key with a level' => [[...$level, 'multipliers' => []], 'Rules of difficulty have no key multipliers.'];
        yield 'a system of another type' => [[...$budget, 'system' => 7], 'The system of rules of difficulty is text.'];
        yield 'bands as a map' => [[...$budget, 'bands' => ['a' => 'low']], 'The bands of rules of difficulty are a list of 12 entries at most.'];
        yield 'too many bands' => [[...$budget, 'bands' => array_fill(0, 13, 'b')], 'The bands of rules of difficulty are a list of 12 entries at most.'];
        yield 'a band of another type' => [[...$budget, 'bands' => [1]], 'The bands of rules of difficulty is text.'];
        yield 'budgets of another type' => [[...$budget, 'budgets' => 'none'], 'The budgets of rules of difficulty are a map of 1000 entries at most.'];
        yield 'too many budgets' => [[...$budget, 'budgets' => array_fill(0, 1001, ['low' => 1])], 'The budgets of rules of difficulty are a map of 1000 entries at most.'];
        yield 'a budget of another type' => [[...$budget, 'budgets' => ['1' => 1]], 'The a budget of rules of difficulty are a map of 12 entries at most.'];
        yield 'an amount of another type' => [[...$budget, 'budgets' => ['1' => ['low' => '1']]], 'The a budget of rules of difficulty are whole numbers.'];
        yield 'a cost as a float' => [[...$budget, 'costs' => ['1' => 1.5]], 'The costs of rules of difficulty are whole numbers.'];
        yield 'too many costs' => [[...$budget, 'costs' => array_fill(0, 1001, 1)], 'The costs of rules of difficulty are a map of 1000 entries at most.'];
        yield 'multipliers as a map' => [[...$budget, 'multipliers' => ['a' => ['from' => 1, 'percent' => 100]]], 'The multipliers of rules of difficulty are a list of 20 entries at most.'];
        yield 'too many multipliers' => [[...$budget, 'multipliers' => array_fill(0, 21, ['from' => 1, 'percent' => 100])], 'The multipliers of rules of difficulty are a list of 20 entries at most.'];
        yield 'a multiplier of another type' => [[...$budget, 'multipliers' => [2]], 'The a multiplier of rules of difficulty are a map of 2 entries at most.'];
        yield 'a multiplier with a third key' => [[...$budget, 'multipliers' => [['from' => 1, 'percent' => 100, 'to' => 3]]], 'The a multiplier of rules of difficulty are a map of 2 entries at most.'];
        yield 'a multiplier with another key' => [[...$budget, 'multipliers' => [['from' => 1, 'to' => 3]]], 'Rules of difficulty have no key to.'];
        yield 'a multiplier without percent' => [[...$budget, 'multipliers' => [['from' => 1]]], 'Rules of difficulty declare their percent.'];
        yield 'a multiplier from text' => [[...$budget, 'multipliers' => [['from' => '1', 'percent' => 100]]], 'The from of rules of difficulty are whole numbers.'];
        yield 'a multiplier percent as text' => [[...$budget, 'multipliers' => [['from' => 1, 'percent' => '100']]], 'The percent of rules of difficulty are whole numbers.'];
        yield 'a party size as text' => [[...$level, 'partySize' => '4'], 'The partySize of rules of difficulty are whole numbers.'];
        yield 'a level budget as text' => [[...$level, 'budgets' => ['low' => '1']], 'The budgets of rules of difficulty are whole numbers.'];
        yield 'too many level budgets' => [[...$level, 'budgets' => array_fill(0, 13, 1)], 'The budgets of rules of difficulty are a map of 12 entries at most.'];
        yield 'too many adjustments' => [[...$level, 'adjustments' => array_fill(0, 13, 1)], 'The adjustments of rules of difficulty are a map of 12 entries at most.'];
        yield 'too many level costs' => [[...$level, 'costs' => array_fill(-50, 102, 1)], 'The costs of rules of difficulty are a map of 101 entries at most.'];
        yield 'a difference of text' => [[...$level, 'costs' => ['one' => 1]], 'The costs are by a whole number of levels.'];
        yield 'a difference of three digits' => [[...$level, 'costs' => ['100' => 1]], 'The costs are by a whole number of levels.'];
        yield 'a difference with a plus' => [[...$level, 'costs' => ['+1' => 1]], 'The costs are by a whole number of levels.'];
        yield 'a difference with a space' => [[...$level, 'costs' => ['1 ' => 1]], 'The costs are by a whole number of levels.'];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    #[DataProvider('invalidDeclarations')]
    public function testADeclarationOutOfItsShapeIsRefused(array $data, string $message): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains($message);

        Declaration::read($data);
    }

    public function testADeclarationReadsEveryWrittenDifferenceAsANumber(): void
    {
        $rules = Declaration::read(['method' => 'level', 'system' => 's', 'partySize' => 1, 'budgets' => ['low' => 1], 'adjustments' => ['low' => 0], 'costs' => ['-0' => 4, '01' => 7]]);

        self::assertSame(4, $rules->assess([self::character('a', '2'), $this->adversary('o', '2')])->cost);
        self::assertSame(7, $rules->assess([self::character('a', '2'), $this->adversary('o', '3')])->cost);
    }

    public function testADeclarationTakesItsBoundsAtTheirLimits(): void
    {
        $rules = Declaration::read([
            'method' => 'level',
            'system' => 's',
            'partySize' => 1,
            'budgets' => ['low' => 1],
            'adjustments' => ['low' => 0],
            'costs' => array_fill(-50, 101, 3),
        ]);

        self::assertSame(3, $rules->assess([self::character('a', '0'), $this->adversary('o', '-50')])->cost);
        self::assertSame(3, $rules->assess([self::character('a', '0'), $this->adversary('o', '50')])->cost);

        $multipliers = array_map(static fn (int $i): array => ['from' => $i, 'percent' => 100 + $i], range(1, 20));
        $budget = Declaration::read(['method' => 'budget', 'system' => 's', 'bands' => array_map(static fn (int $i): string => 'b'.$i, range(1, 12)), 'budgets' => ['1' => array_fill_keys(array_map(static fn (int $i): string => 'b'.$i, range(1, 12)), 1)], 'costs' => ['1' => 100], 'multipliers' => $multipliers]);
        self::assertSame(2400, $budget->assess([self::character('a', '1'), $this->adversary('o', '1', 20)])->cost);
    }
}
