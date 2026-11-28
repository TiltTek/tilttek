<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Random\Engine\Mt19937;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Exception\DiceException;
use Tilttek\Dice\Limits as DiceLimits;
use Tilttek\Generator\Definition\Attribution;
use Tilttek\Generator\Exception\GeneratorException;
use Tilttek\Generator\Limits;
use Tilttek\RandomCharacter\CharacterRoller;
use Tilttek\RandomCharacter\CreationRules;
use Tilttek\RandomCharacter\Event\CharacterRolled;
use Tilttek\RandomCharacter\Exception\InvalidRules;
use Tilttek\RandomCharacter\Exception\InvalidSeed;
use Tilttek\RandomCharacter\Exception\RollFailed;
use Tilttek\RandomCharacter\Field;
use Tilttek\RandomCharacter\RandomCharacter;
use Tilttek\RandomCharacter\StatMethod;
use Tilttek\RandomCharacter\Stats;

#[CoversClass(CharacterRoller::class)]
#[CoversClass(CharacterRolled::class)]
#[CoversClass(RandomCharacter::class)]
#[CoversClass(InvalidSeed::class)]
#[CoversClass(InvalidRules::class)]
#[CoversClass(RollFailed::class)]
final class CharacterRollerTest extends TestCase
{
    private function roller(?Fixture $observer = null, ?DiceRoller $dice = null, ?Limits $limits = null): CharacterRoller
    {
        return new CharacterRoller(Fixture::systems(), Fixture::generator($limits ?? new Limits()), $dice ?? new DiceRoller(), new Randomizer(new Mt19937(7)), $observer);
    }

    private function hero(): CreationRules
    {
        return new CreationRules('hero', 'test', new Stats(StatMethod::Each, ['str', 'dex'], '3d6'), [
            Field::generated('kin', 'kin.mixed'),
            Field::generated('name', 'name.{kin}'),
            Field::generated('traits', 'trait', 3, true),
            Field::rolled('age', '2d10+14'),
            Field::rolled('scars', '1d4', 2),
        ]);
    }

    public function testRollsACharacter(): void
    {
        $observer = new Fixture();
        $character = $this->roller($observer)->roll($this->hero(), 'table 7');

        self::assertSame('hero', $character->rules);
        self::assertSame('test', $character->system);
        self::assertSame('table 7', $character->seed);
        self::assertSame(['str', 'dex'], array_keys($character->stats));
        self::assertSame(['kin', 'name', 'traits', 'age', 'scars'], array_keys($character->fields));

        foreach ($character->stats as $value) {
            self::assertGreaterThanOrEqual(3, $value);
            self::assertLessThanOrEqual(18, $value);
        }

        $kin = $character->field('kin');
        self::assertIsString($kin);
        self::assertContains($kin, ['elf', 'dwarf', 'gnome']);
        self::assertContains($character->field('name'), ['elf' => ['Aerin', 'Lithil', 'Sael'], 'dwarf' => ['Borin', 'Dagna'], 'gnome' => ['Pip', 'Fizz']][$kin]);
        $traits = $character->field('traits');
        self::assertIsArray($traits);
        $texts = array_filter($traits, \is_string(...));
        self::assertCount(3, array_unique($texts));
        self::assertSame([], array_diff($texts, ['brave', 'curious', 'gentle', 'wry']));
        $age = $character->field('age');
        self::assertIsInt($age);
        self::assertGreaterThanOrEqual(16, $age);
        self::assertLessThanOrEqual(34, $age);
        $scars = $character->field('scars');
        self::assertIsArray($scars);
        self::assertCount(2, $scars);
        self::assertContainsOnlyInt($scars);
        self::assertSame([], $character->edited);
        self::assertEquals([new CharacterRolled('hero', 'test', true)], $observer->events);
    }

    public function testTheSameSeedGivesTheSameCharacter(): void
    {
        $first = $this->roller()->roll($this->hero(), 'table 7');

        self::assertSame($first->toArray(), $this->roller()->roll($this->hero(), 'table 7')->toArray());
        self::assertSame($first->toArray(), new CharacterRoller(Fixture::systems(), Fixture::generator())->roll($this->hero(), 'table 7')->toArray());
    }

    public function testOtherSeedsGiveOtherCharacters(): void
    {
        $characters = [];

        foreach (range(1, 20) as $seed) {
            $characters[] = json_encode($this->roller()->roll($this->hero(), 'seed '.$seed)->toArray(), \JSON_THROW_ON_ERROR);
        }

        self::assertGreaterThan(15, \count(array_unique($characters)));
    }

    public function testTheValuesOfASeedAreTheSameForEver(): void
    {
        $rules = new CreationRules('hero', 'test', null, [
            Field::rolled('age', '1d1000', 3),
            Field::generated('traits', 'trait', 2, true),
            Field::generated('more', 'trait', 2, true),
            Field::generated('one', 'trait'),
            Field::generated('kin', 'kin.mixed'),
        ]);
        $expected = [
            'golden' => ['age' => [955, 886, 343], 'traits' => ['brave', 'wry'], 'more' => ['brave', 'gentle'], 'one' => 'brave', 'kin' => 'elf'],
            'seed two' => ['age' => [19, 731, 403], 'traits' => ['wry', 'curious'], 'more' => ['wry', 'brave'], 'one' => 'wry', 'kin' => 'elf'],
            'x' => ['age' => [923, 636, 854], 'traits' => ['curious', 'wry'], 'more' => ['brave', 'wry'], 'one' => 'gentle', 'kin' => 'dwarf'],
        ];

        foreach ($expected as $seed => $fields) {
            self::assertSame($fields, $this->roller()->roll($rules, $seed)->fields);
        }
    }

    public function testEachPartHasASeedOfItsOwn(): void
    {
        $short = new CreationRules('hero', 'test', new Stats(StatMethod::Each, ['str', 'dex'], '3d6'), [Field::rolled('age', '2d10+14')]);
        $long = new CreationRules('hero', 'test', new Stats(StatMethod::Each, ['str', 'dex'], '3d6'), [Field::generated('kin', 'kin.mixed'), Field::rolled('age', '2d10+14')]);
        $before = $this->roller()->roll($short, 'stable');
        $after = $this->roller()->roll($long, 'stable');

        self::assertSame($before->stats, $after->stats);
        self::assertSame($before->field('age'), $after->field('age'));
    }

    public function testTheStatsAndTheFieldsDrawDifferently(): void
    {
        $rules = new CreationRules('hero', 'test', new Stats(StatMethod::Each, ['luck'], '1d1000'), [Field::rolled('a', '1d1000'), Field::rolled('b', '1d1000')]);
        $character = $this->roller()->roll($rules, 'apart');

        self::assertNotSame($character->stat('luck'), $character->field('a'));
        self::assertNotSame($character->stat('luck'), $character->field('b'));
        self::assertNotSame($character->field('a'), $character->field('b'));
    }

    public function testASeedIsDrawnWhenNoneIsGiven(): void
    {
        $observer = new Fixture();
        $roller = $this->roller($observer);
        $character = $roller->roll($this->hero());

        self::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $character->seed);
        self::assertSame($character->toArray(), $roller->roll($this->hero(), $character->seed)->toArray());
        self::assertNotSame($character->seed, $roller->roll($this->hero())->seed);
        self::assertSame(new Randomizer(new Mt19937(7))->getBytesFromString('0123456789abcdef', 32), $character->seed);
        self::assertFalse($observer->events[0]->seeded);
        self::assertTrue($observer->events[1]->seeded);
        self::assertSame('hero', $observer->events[0]->rules);
        self::assertSame('test', $observer->events[0]->system);
    }

    public function testTheLongestSeedIsTaken(): void
    {
        $seed = str_repeat('é', CharacterRoller::SEED_LENGTH);

        self::assertSame($seed, $this->roller()->roll($this->hero(), $seed)->seed);
    }

    public function testRefusesAnEmptySeed(): void
    {
        $this->expectExceptionObject(InvalidSeed::because('A seed is not empty.'));

        $this->roller()->roll($this->hero(), '');
    }

    public function testRefusesASeedNotInUtf8(): void
    {
        $this->expectExceptionObject(InvalidSeed::because('A seed is written in UTF-8.'));

        $this->roller()->roll($this->hero(), "\xFF");
    }

    public function testRefusesASeedTooLong(): void
    {
        $this->expectExceptionObject(InvalidSeed::because('A seed is at most 256 characters long.'));

        $this->roller()->roll($this->hero(), str_repeat('a', 257));
    }

    public function testARolledArrayDealsItsValues(): void
    {
        $rules = new CreationRules('hero', 'test', new Stats(StatMethod::RolledArray, ['str', 'dex', 'luck'], '4d6kh3'));
        $character = $this->roller()->roll($rules, 'array');

        self::assertSame(['str', 'dex', 'luck'], array_keys($character->stats));

        $orders = [];

        foreach (range(1, 30) as $seed) {
            $stats = new CharacterRoller(Fixture::systems())->roll(new CreationRules('order', 'test', new Stats(StatMethod::RolledArray, ['str', 'dex'], '1d6+10')), 's'.$seed)->stats;
            self::assertCount(2, $stats);

            foreach ($stats as $value) {
                self::assertGreaterThanOrEqual(11, $value);
                self::assertLessThanOrEqual(16, $value);
            }

            $orders[] = json_encode($stats, \JSON_THROW_ON_ERROR);
        }

        self::assertGreaterThan(5, \count(array_unique($orders)));
    }

    public function testAFixedArrayIsDealtAtRandom(): void
    {
        $rules = new CreationRules('hero', 'test', new Stats(StatMethod::Array, ['str', 'dex', 'luck'], values: [15, 10, 3]));
        $deals = [];

        foreach (range(1, 40) as $seed) {
            $stats = $this->roller()->roll($rules, 's'.$seed)->stats;
            self::assertSame(['str', 'dex', 'luck'], array_keys($stats));
            $sorted = array_values($stats);
            sort($sorted);
            self::assertSame([3, 10, 15], $sorted);
            $deals[implode(',', $stats)] = true;
        }

        self::assertCount(6, $deals);
    }

    public function testEachRollsInTheOrderOfTheAttributes(): void
    {
        $each = $this->roller()->roll(new CreationRules('hero', 'test', new Stats(StatMethod::Each, ['str', 'dex', 'luck'], '4d6kh3')), 'order');
        $randomizer = new Randomizer(new Xoshiro256StarStar(hash('sha256', "order\0stats", true)));
        $dice = new DiceRoller();
        $pool = $dice->parse('4d6kh3');
        $expected = [];

        foreach (['str', 'dex', 'luck'] as $attribute) {
            $expected[$attribute] = $dice->roll($pool, $randomizer)->totals()[0];
        }

        self::assertSame($expected, $each->stats);
    }

    public function testAFieldReadsAValueOfAStructuredResult(): void
    {
        $rules = new CreationRules('hero', 'test', fields: [Field::generated('kin', 'kin.object'), Field::generated('name', 'name.{kin.key}')]);
        $character = $this->roller()->roll($rules, 'object');

        self::assertSame(['key' => 'elf', 'label' => 'Elf'], $character->field('kin'));
        self::assertContains($character->field('name'), ['Aerin', 'Lithil', 'Sael']);
    }

    public function testAFieldMayNameItsGeneratorByANumber(): void
    {
        $rules = new CreationRules('hero', 'test', fields: [Field::rolled('n', '3'), Field::generated('name', 'name.{n}')]);

        self::assertSame('Three', $this->roller()->roll($rules, 'n')->field('name'));
    }

    public function testTheAttributionsAreGivenOnce(): void
    {
        $rules = new CreationRules('hero', 'test', fields: [
            Field::generated('kin', 'kin'),
            Field::generated('name', 'name.{kin}'),
            Field::generated('again', 'kin'),
            Field::generated('traits', 'trait', 2),
            Field::generated('single', 'single'),
        ]);
        $character = $this->roller()->roll($rules, 'credits');

        self::assertEquals([new Attribution('Kin book', 'owned'), new Attribution('Trait book', 'owned')], $character->attributions);
        self::assertSame([0, 1], array_keys($character->attributions));
    }

    public function testRulesWithoutStatsHaveNoStats(): void
    {
        self::assertSame([], $this->roller()->roll(new CreationRules('hero', 'test', fields: [Field::rolled('age', '1d6')]), 'a')->stats);
    }

    public function testRulesWithoutGeneratedFieldsNeedNoGenerator(): void
    {
        $roller = new CharacterRoller(Fixture::systems());
        $rules = new CreationRules('hero', 'test', new Stats(StatMethod::Each, ['str'], '3d6'), [Field::rolled('age', '1d6')]);

        self::assertSame([], $roller->check($rules));
        self::assertSame(['str'], array_keys($roller->roll($rules, 'a')->stats));
    }

    public function testChecksTheRules(): void
    {
        $roller = $this->roller();

        self::assertSame([], $roller->check($this->hero()));
        self::assertSame(['The system "other" is unknown.'], $roller->check(new CreationRules('hero', 'other', new Stats(StatMethod::Each, ['nope'], '3d6'))));
        self::assertSame([
            'The value 2 of the array is outside the bounds of the attribute "str".',
            'The value 19 of the array is outside the bounds of the attribute "str".',
            'The attribute "con" is not one of the system "test".',
            'The value 2 of the array is outside the bounds of the attribute "dex".',
            'The value 19 of the array is outside the bounds of the attribute "dex".',
            'The field "x" draws from the generator "missing", which is unknown.',
            'The field "z" draws from the generator "gone", which is unknown.',
        ], $roller->check(new CreationRules('hero', 'test', new Stats(StatMethod::Array, ['str', 'con', 'dex', 'luck'], values: [2, 19, 3, 18]), [
            Field::generated('x', 'missing'),
            Field::generated('y', 'name.{x}'),
            Field::rolled('w', '1d6'),
            Field::generated('z', 'gone'),
        ])));
        self::assertSame([
            'The field "x" draws from a generator, and the roller has none.',
            'The field "z" draws from a generator, and the roller has none.',
        ], new CharacterRoller(Fixture::systems())->check(new CreationRules('hero', 'test', fields: [Field::generated('x', 'kin'), Field::generated('y', 'name.{x}'), Field::generated('z', 'kin')])));
    }

    public function testRefusesToRollRulesWithProblems(): void
    {
        $observer = new Fixture();

        try {
            $this->roller($observer)->roll(new CreationRules('hero', 'test', new Stats(StatMethod::Each, ['con', 'wis'], '3d6')), 'a');
            self::fail('The rules are refused.');
        } catch (InvalidRules $invalidRules) {
            self::assertSame("The creation rules \"hero\" cannot be rolled:\n- The attribute \"con\" is not one of the system \"test\".\n- The attribute \"wis\" is not one of the system \"test\".", $invalidRules->getMessage());
        }

        self::assertSame([], $observer->events);
    }

    public function testRefusesAStatOutsideItsBounds(): void
    {
        $this->expectExceptionObject(RollFailed::because('The stats gave 19 to the attribute "str", outside its bounds of 3 to 18.'));

        $this->roller()->roll(new CreationRules('hero', 'test', new Stats(StatMethod::Each, ['str'], '19')), 'a');
    }

    public function testRefusesAStatBelowItsBounds(): void
    {
        $this->expectExceptionObject(RollFailed::because('The stats gave 2 to the attribute "dex", outside its bounds of 3 to 18.'));

        $this->roller()->roll(new CreationRules('hero', 'test', new Stats(StatMethod::Each, ['luck', 'dex'], '2')), 'a');
    }

    public function testAStatWithoutBoundsTakesAnyValue(): void
    {
        self::assertSame(['luck' => -50], $this->roller()->roll(new CreationRules('hero', 'test', new Stats(StatMethod::Each, ['luck'], '-50')), 'a')->stats);
    }

    public function testRefusesAGeneratorNamedByAPlaceholderThatIsUnknown(): void
    {
        $this->expectExceptionObject(RollFailed::because('The field "name" draws from the generator "name.only", which is unknown.'));

        $this->roller()->roll(new CreationRules('hero', 'test', fields: [Field::generated('kin', 'single'), Field::generated('name', 'name.{kin}'), Field::generated('x', 'kin')]), 'a');
    }

    public function testRefusesAGeneratorNamedByAPlaceholderWhenTheRollerHasNoGenerator(): void
    {
        $this->expectExceptionObject(RollFailed::because('The roller has no generator.'));

        new CharacterRoller(Fixture::systems())->roll(new CreationRules('hero', 'test', fields: [Field::rolled('n', '3'), Field::generated('name', 'name.{n}')]), 'a');
    }

    public function testRefusesTooFewUniqueResults(): void
    {
        $this->expectExceptionObject(RollFailed::because('The generator "single" gave 1 of the 2 different results the field "x" asks for.'));

        $this->roller()->roll(new CreationRules('hero', 'test', fields: [Field::generated('x', 'single', 2, true)]), 'a');
    }

    public function testRepeatedResultsAreTakenWhenTheyNeedNotDiffer(): void
    {
        self::assertSame(['only', 'only'], $this->roller()->roll(new CreationRules('hero', 'test', fields: [Field::generated('x', 'single', 2)]), 'a')->field('x'));
    }

    public function testRefusesAFailingGenerator(): void
    {
        try {
            $this->roller(limits: new Limits(results: 1))->roll(new CreationRules('hero', 'test', fields: [Field::generated('x', 'trait', 2)]), 'a');
            self::fail('The roll fails.');
        } catch (RollFailed $rollFailed) {
            self::assertStringStartsWith('The generator "trait" of the field "x" failed: ', $rollFailed->getMessage());
            self::assertInstanceOf(GeneratorException::class, $rollFailed->getPrevious());
        }
    }

    public function testRefusesDiceBeyondTheirLimits(): void
    {
        try {
            $this->roller(dice: new DiceRoller(new DiceLimits(dice: 2)))->roll(new CreationRules('hero', 'test', fields: [Field::rolled('age', '3d6')]), 'a');
            self::fail('The roll fails.');
        } catch (RollFailed $rollFailed) {
            self::assertStringStartsWith('The roll of the field "age" failed: ', $rollFailed->getMessage());
            self::assertInstanceOf(DiceException::class, $rollFailed->getPrevious());
        }
    }

    public function testRefusesStatDiceBeyondTheirLimits(): void
    {
        $this->expectException(RollFailed::class);
        $this->expectExceptionMessageMatches('/^The roll of the stats failed: /');

        $this->roller(dice: new DiceRoller(new DiceLimits(dice: 2)))->roll(new CreationRules('hero', 'test', new Stats(StatMethod::RolledArray, ['str'], '3d6')), 'a');
    }

    public function testRefusesARollWithoutANumber(): void
    {
        $this->expectExceptionObject(RollFailed::because('The roll of the field "word" gives no number.'));

        $this->roller()->roll(new CreationRules('hero', 'test', fields: [Field::rolled('word', '"hi"')]), 'a');
    }

    public function testRefusesAListOfRollsWithoutANumber(): void
    {
        $this->expectExceptionObject(RollFailed::because('The roll of the field "word" gives no number.'));

        $this->roller()->roll(new CreationRules('hero', 'test', fields: [Field::rolled('word', '"hi"', 2)]), 'a');
    }
}
