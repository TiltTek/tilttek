<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Encounter\Difficulty\BudgetRules;
use Tilttek\Encounter\Difficulty\Declaration;
use Tilttek\Encounter\Difficulty\LevelRules;
use Tilttek\Encounter\Exception\InvalidEncounter;
use Tilttek\Encounter\Exception\UnknownSystem;
use Tilttek\Encounter\Kind;
use Tilttek\Encounter\Participant;
use Tilttek\Encounter\Rulebooks;
use Tilttek\Encounter\Tests\Unit\Fixture\Systems as Fixtures;
use Tilttek\RpgSystems\Difficulty;
use Tilttek\RpgSystems\DifficultyBand;
use Tilttek\RpgSystems\GameSystem;
use Tilttek\RpgSystems\Import\Importer;
use Tilttek\RpgSystems\Import\ImportProfile;
use Tilttek\RpgSystems\Licence;
use Tilttek\RpgSystems\Provenance;
use Tilttek\RpgSystems\Systems;
use Tilttek\RpgSystems\Translations;

/**
 * The rules of difficulty read from the difficulty numbers of a `tilttek/rpg-systems` system: the SRD 5.2 by budget,
 * Pathfinder Second Edition by level, as their import profiles state them.
 */
#[CoversClass(Declaration::class)]
#[CoversClass(Rulebooks::class)]
#[CoversClass(UnknownSystem::class)]
final class SystemDifficultyTest extends TestCase
{
    private function character(string $id, string $level): Participant
    {
        return new Participant($id, Kind::Character, $level, 'player-'.$id);
    }

    private function adversary(string $id, string $rating, int $quantity = 1): Participant
    {
        return new Participant($id, Kind::Adversary, $rating, 'gm', $quantity);
    }

    private function srd52(): GameSystem
    {
        return new Importer()->import(ImportProfile::srd52(), [], new Provenance('SRD 5.2', Licence::CcBy40, 'Material from the SRD 5.2, CC BY 4.0.'));
    }

    private function pathfinder(): GameSystem
    {
        return new Importer()->import(ImportProfile::pathfinder2e(), [], new Provenance('Pathfinder', Licence::Orc, 'Material used under the ORC License.'));
    }

    /**
     * @param list<DifficultyBand>    $bands
     * @param array<array-key, mixed> $numbers
     */
    private static function system(?string $method, array $bands, array $numbers, string $key = 'encounter'): GameSystem
    {
        $source = new Provenance('Own', Licence::Owned);

        return new GameSystem('own', Translations::of(['en' => 'Own']), $source, difficulties: [new Difficulty($key, Translations::of(['en' => 'Encounter']), $source, method: $method, bands: $bands, numbers: $numbers)]);
    }

    private static function band(string $key): DifficultyBand
    {
        return new DifficultyBand($key, Translations::of(['en' => ucfirst($key)]));
    }

    public function testTheSrdBudgetIsReadFromItsDifficultyNumbers(): void
    {
        $rules = Declaration::fromSystem($this->srd52());

        self::assertInstanceOf(BudgetRules::class, $rules);
        self::assertSame('srd-5.2', $rules->system());

        $party = [$this->character('a', '1'), $this->character('b', '1'), $this->character('c', '1'), $this->character('d', '1')];
        $assessment = $rules->assess([...$party, $this->adversary('goblins', '1/2', 3)]);
        self::assertSame(['low' => 200, 'moderate' => 300, 'high' => 400], array_column($assessment->toArray()['bands'], 'threshold', 'key'));
        // The rules of the SRD 5.2 multiply nothing: three adversaries cost their sum.
        self::assertSame(300, $assessment->cost);
        self::assertSame('moderate', $assessment->difficulty);
        self::assertSame(22_000, $rules->assess([$this->character('a', '20'), $this->adversary('dragon', '30')])->threshold('high'));
        self::assertSame(155_000, $rules->assess([$this->character('a', '20'), $this->adversary('dragon', '30')])->cost);
        self::assertSame(10, $rules->assess([$this->character('a', '1'), $this->adversary('rat', '0')])->cost);
    }

    public function testThePathfinderBudgetIsReadFromItsDifficultyNumbers(): void
    {
        $rules = Declaration::fromSystem($this->pathfinder());

        self::assertInstanceOf(LevelRules::class, $rules);
        self::assertSame('pf2e', $rules->system());

        $party = [$this->character('a', '1'), $this->character('b', '1'), $this->character('c', '1'), $this->character('d', '1')];
        $assessment = $rules->assess([...$party, $this->adversary('wolves', '1', 2)]);
        self::assertSame(['trivial' => 40, 'low' => 60, 'moderate' => 80, 'severe' => 120, 'extreme' => 160], array_column($assessment->toArray()['bands'], 'threshold', 'key'));
        // Two creatures of the party's level cost 40 each.
        self::assertSame(80, $assessment->cost);
        self::assertSame('moderate', $assessment->difficulty);
        self::assertSame('severe', $rules->assess([...$party, $this->adversary('ogre', '4')])->difficulty);

        // A fifth character raises each budget by its adjustment; a creature four levels under the party costs 10.
        $five = $rules->assess([...$party, $this->character('e', '5'), $this->adversary('rat', '-3')]);
        self::assertSame(['trivial' => 50, 'low' => 75, 'moderate' => 100, 'severe' => 150, 'extreme' => 200], array_column($five->toArray()['bands'], 'threshold', 'key'));
        self::assertSame(10, $five->cost);
    }

    public function testOtherDifficultyNumbersAreReadByTheirKey(): void
    {
        $system = self::system('budget', [self::band('calm'), self::band('tense')], ['budgets' => ['1' => ['calm' => 2, 'tense' => 4]], 'costs' => ['1' => 2], 'multipliers' => ['2' => 150, '3' => 200]], 'threat');
        $rules = Declaration::fromSystem($system, 'threat');

        self::assertSame(6, $rules->assess([$this->character('a', '1'), $this->adversary('o', '1', 2)])->cost);
        self::assertSame(12, $rules->assess([$this->character('a', '1'), $this->adversary('o', '1', 3)])->cost);
        self::assertSame('tense', $rules->assess([$this->character('a', '1'), $this->adversary('o', '1', 2)])->difficulty);

        $this->expectException(UnknownSystem::class);
        $this->expectExceptionMessageIsOrContains('No rules of difficulty are declared for the game system own.');
        Declaration::fromSystem($system);
    }

    public function testTheTablesOfALevelMayComeInAnotherOrderThanTheBands(): void
    {
        $system = self::system('level', [self::band('low'), self::band('high')], [
            'costs' => ['0' => 10],
            'adjustments' => ['high' => 4, 'low' => 2],
            'budgets' => ['high' => 20, 'low' => 10],
            'party-size' => 2,
        ]);

        $assessment = Declaration::fromSystem($system)->assess([$this->character('a', '1'), $this->adversary('o', '1')]);
        self::assertSame(['low' => 8, 'high' => 16], array_column($assessment->toArray()['bands'], 'threshold', 'key'));
    }

    /**
     * @return iterable<string, array{GameSystem, string}>
     */
    public static function badNumbers(): iterable
    {
        $budget = ['budgets' => ['1' => ['low' => 1]], 'costs' => ['1' => 1]];
        $level = ['party-size' => 4, 'budgets' => ['low' => 1], 'adjustments' => ['low' => 1], 'costs' => ['0' => 1]];

        yield 'no method' => [self::system(null, [self::band('low')], $budget), 'The difficulty numbers encounter of own declare the method "budget" or "level".'];
        yield 'another method' => [self::system('check', [self::band('low')], $budget), 'The difficulty numbers encounter of own declare the method "budget" or "level".'];
        yield 'an unknown table of a budget' => [self::system('budget', [self::band('low')], $budget + ['party-size' => 4]), 'The difficulty numbers encounter of own have no table party-size.'];
        yield 'an unknown table of a level' => [self::system('level', [self::band('low')], $level + ['multipliers' => []]), 'The difficulty numbers encounter of own have no table multipliers.'];
        yield 'no costs' => [self::system('budget', [self::band('low')], ['budgets' => $budget['budgets']]), 'The difficulty numbers encounter of own declare their costs.'];
        yield 'no budgets' => [self::system('budget', [self::band('low')], ['costs' => $budget['costs']]), 'The difficulty numbers encounter of own declare their budgets.'];
        yield 'no party size' => [self::system('level', [self::band('low')], array_diff_key($level, ['party-size' => true])), 'The difficulty numbers encounter of own declare their party-size.'];
        yield 'no adjustments' => [self::system('level', [self::band('low')], array_diff_key($level, ['adjustments' => true])), 'The difficulty numbers encounter of own declare their adjustments.'];
        yield 'multipliers as a number' => [self::system('budget', [self::band('low')], $budget + ['multipliers' => 150]), 'The multipliers of rules of difficulty are a map of 20 entries at most.'];
        yield 'too many multipliers' => [self::system('budget', [self::band('low')], $budget + ['multipliers' => array_fill(2, 21, 150)]), 'The multipliers of rules of difficulty are a map of 20 entries at most.'];
        yield 'a band missing from a level' => [self::system('level', [self::band('low'), self::band('high')], ['budgets' => ['low' => 1, 'mid' => 2]] + $level), 'The difficulty numbers encounter give nothing to the band high.'];
        yield 'a band too many in a level' => [self::system('level', [self::band('low')], ['budgets' => ['low' => 1, 'high' => 2]] + $level), 'The tables of the difficulty numbers encounter give each of their bands, low, and no other.'];
        yield 'a level budget as a number' => [self::system('level', [self::band('low')], ['budgets' => 5] + $level), 'The budgets of rules of difficulty are a map of 12 entries at most.'];
        yield 'no band of a budget' => [self::system('budget', [], $budget), 'Rules of difficulty declare 1 to 12 bands.'];
    }

    #[DataProvider('badNumbers')]
    public function testNumbersThatAreNotRulesAreRefused(GameSystem $system, string $message): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains($message);

        Declaration::fromSystem($system);
    }

    public function testTheRulebooksHoldTheRulesOfEverySystemThatDeclaresThem(): void
    {
        $plain = new GameSystem('plain', Translations::of(['en' => 'Plain']), new Provenance('Plain', Licence::Owned));
        $rulebooks = Rulebooks::fromSystems(new Systems($this->srd52(), $this->pathfinder(), $plain));

        self::assertInstanceOf(BudgetRules::class, $rulebooks->for('srd-5.2'));
        self::assertInstanceOf(LevelRules::class, $rulebooks->for($this->pathfinder()));
        self::assertFalse($rulebooks->has('plain'));

        // The rules an application brings take the place of a system's, and give rules to a system without them.
        $own = new BudgetRules('srd-5.2', ['low'], ['1' => ['low' => 1]], ['1' => 1]);
        $plainRules = new BudgetRules('plain', ['low'], ['1' => ['low' => 1]], ['1' => 1]);
        $replaced = Rulebooks::fromSystems([$this->srd52(), $this->pathfinder(), $plain], Declaration::RECORD, $own, $plainRules, Fixtures::level());
        self::assertSame($own, $replaced->for('srd-5.2'));
        self::assertSame($plainRules, $replaced->for('plain'));
        self::assertTrue($replaced->has('level-game'));
        self::assertInstanceOf(LevelRules::class, $replaced->for('pf2e'));

        // Another key of difficulty numbers: no system here declares it.
        self::assertFalse(Rulebooks::fromSystems([$this->srd52()], 'threat')->has('srd-5.2'));
    }

    public function testAMultiplierLabelThatIsNotANumberIsRefused(): void
    {
        $system = self::system('budget', [self::band('calm')], ['budgets' => ['1' => ['calm' => 2]], 'costs' => ['1' => 2], 'multipliers' => ['x' => 150]]);

        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('A multiplier holds from 1 adversary or more.');

        Declaration::fromSystem($system);
    }

    public function testTheRulebooksRefuseTwoGivenRulesForOneSystem(): void
    {
        $this->expectException(InvalidEncounter::class);
        $this->expectExceptionMessageIsOrContains('Two rules of difficulty are declared for the game system budget-game.');

        Rulebooks::fromSystems([], Declaration::RECORD, Fixtures::budget(), Fixtures::budget());
    }
}
