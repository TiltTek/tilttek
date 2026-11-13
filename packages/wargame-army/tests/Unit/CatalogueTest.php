<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\WargameArmy\ArrayInput;
use Tilttek\WargameArmy\Catalog\InMemoryCatalogueProvider;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Constraint\PointsShare;
use Tilttek\WargameArmy\Constraint\Selector;
use Tilttek\WargameArmy\Constraint\UnitCount;
use Tilttek\WargameArmy\DetachmentType;
use Tilttek\WargameArmy\Exception\InvalidCatalogue;
use Tilttek\WargameArmy\Option;
use Tilttek\WargameArmy\OptionGroup;
use Tilttek\WargameArmy\Slot;
use Tilttek\WargameArmy\Tests\Unit\Fixture\Skirmish;
use Tilttek\WargameArmy\UnitEntry;

#[CoversClass(Catalogue::class)]
#[CoversClass(UnitEntry::class)]
#[CoversClass(OptionGroup::class)]
#[CoversClass(Option::class)]
#[CoversClass(DetachmentType::class)]
#[CoversClass(Slot::class)]
#[CoversClass(ArrayInput::class)]
#[CoversClass(InMemoryCatalogueProvider::class)]
final class CatalogueTest extends TestCase
{
    public function testACatalogueReadsItsUnitsDetachmentsAndConstraints(): void
    {
        $catalogue = Skirmish::catalogue();

        self::assertSame('ember-vale', $catalogue->code);
        self::assertSame('Ember Vale Skirmish', $catalogue->name);
        self::assertSame('2026.1', $catalogue->version);
        self::assertSame(['captain', 'scouts', 'guards', 'ogre'], array_keys($catalogue->units));
        self::assertSame(['vanguard', 'raid'], array_keys($catalogue->detachmentTypes));
        self::assertSame(1, $catalogue->minDetachments);
        self::assertSame(2, $catalogue->maxDetachments);
        self::assertCount(3, $catalogue->constraints);
        self::assertSame('Scouts', $catalogue->unit('scouts')?->name);
        self::assertNull($catalogue->unit('dragon'));
        self::assertSame('Raid', $catalogue->detachmentType('raid')?->name);
        self::assertNull($catalogue->detachmentType('swamp'));

        $scouts = $catalogue->unit('scouts');
        self::assertNotNull($scouts);
        self::assertSame(['troops'], $scouts->categories);
        self::assertSame(50, $scouts->points);
        self::assertSame(5, $scouts->minModels);
        self::assertSame(10, $scouts->maxModels);
        self::assertSame(10, $scouts->pointsPerModel);
        self::assertTrue($scouts->option('long-rifle')?->perModel);
        self::assertNull($scouts->option('sword'));
        self::assertTrue($scouts->hasCategory('troops'));
        self::assertFalse($scouts->hasCategory('hero'));

        $captain = $catalogue->unit('captain');
        self::assertNotNull($captain);
        self::assertSame('Horse', $captain->option('horse')?->name);
        self::assertSame(1, $captain->maxModels);
        self::assertSame(1, $captain->optionGroups[0]->min);
        self::assertSame(2, $captain->optionGroups[1]->max);
        self::assertSame(0, $captain->optionGroups[1]->min);
    }

    public function testACatalogueWritesAndReadsBack(): void
    {
        $catalogue = Skirmish::catalogue();
        $json = $catalogue->toJson();

        self::assertTrue(Catalogue::fromJson($json)->equals($catalogue));
        self::assertStringContainsString('"constraints":[{"type":"count","category":"leader","scope":"army","min":1,"max":null},{"type":"count","unit":"ogre","scope":"army","min":0,"max":1},{"type":"points-share","category":"hero","min_percent":0,"max_percent":25}]', $json);
        self::assertStringContainsString('{"code":"long-rifle","name":"Long rifle","points":2,"max":1,"per_model":true}', $json);
        self::assertStringContainsString('{"code":"raid","name":"Raid","slots":[{"category":"hero","min":0,"max":1},{"category":"troops","min":0,"max":2}],"max":null}', $json);
        self::assertStringContainsString('"name":"Ember Vale Skirmish","version":"2026.1"', $json);
        self::assertFalse(Catalogue::fromArray(['version' => '2026.2'] + Skirmish::data())->equals($catalogue));
        self::assertStringContainsString('"name":"Vallée / Braise"', Catalogue::fromArray(['name' => 'Vallée / Braise'] + Skirmish::data())->toJson());
    }

    public function testTheDefaultsOfAMinimalCatalogue(): void
    {
        $catalogue = Catalogue::fromJson('{"code":"c","name":"C","version":"1","units":[{"code":"u","name":"U"}],"detachment_types":[{"code":"d","name":"D","slots":[{"category":"any"}]}]}');
        $unit = $catalogue->unit('u');
        $slot = $catalogue->detachmentType('d')?->slots[0];

        self::assertSame(1, $catalogue->minDetachments);
        self::assertSame(1, $catalogue->maxDetachments);
        self::assertSame([], $catalogue->constraints);
        self::assertNotNull($unit);
        self::assertSame([], $unit->categories);
        self::assertSame(0, $unit->points);
        self::assertSame(1, $unit->minModels);
        self::assertSame(1, $unit->maxModels);
        self::assertSame(0, $unit->pointsPerModel);
        self::assertSame([], $unit->optionGroups);
        self::assertNotNull($slot);
        self::assertSame(0, $slot->min);
        self::assertNull($slot->max);
        self::assertNull($catalogue->detachmentType('d')?->max);
        self::assertSame(['code' => 'o', 'name' => 'O', 'points' => 0, 'max' => 1, 'per_model' => false], Option::fromArray(['code' => 'o', 'name' => 'O'])->toArray());
        self::assertSame(1, OptionGroup::fromArray(['code' => 'g', 'name' => 'G', 'options' => [['code' => 'o', 'name' => 'O']]])->max);
    }

    public function testTheDefaultsOfTheBuiltValues(): void
    {
        $option = new Option('o', 'O');
        $catalogue = new Catalogue('c', 'C', '1', [new UnitEntry('u', 'U')], [new DetachmentType('d', 'D', [new Slot('any')])]);
        $unit = $catalogue->unit('u');

        self::assertSame(1, $catalogue->minDetachments);
        self::assertSame(1, $catalogue->maxDetachments);
        self::assertSame(0, $option->points);
        self::assertSame(1, $option->max);
        self::assertSame(0, new OptionGroup('g', 'G', [$option])->min);
        self::assertSame(1, new OptionGroup('g', 'G', [$option])->max);
        self::assertSame(0, new Slot('any')->min);
        self::assertNotNull($unit);
        self::assertSame(0, $unit->points);
        self::assertSame(0, $unit->pointsPerModel);
        self::assertSame(1, $catalogue->toArray()['min_detachments']);
        self::assertSame('c', $catalogue->toArray()['code']);
    }

    public function testTheBoundsAreAccepted(): void
    {
        $option = new Option('o', 'O', 100_000, 1_000, true);
        self::assertSame(100_000, $option->points);
        self::assertSame(1_000, $option->max);
        self::assertSame(0, new Option('o', 'O', 0)->points);

        $group = new OptionGroup('g', 'G', array_map(static fn (int $i): Option => new Option('o'.$i, 'O'), range(1, 100)), 1_000, 1_000);
        self::assertCount(100, $group->options);
        self::assertSame(1_000, $group->min);
        self::assertSame(3, new OptionGroup('g', 'G', [$option], 3, 3)->max);

        $unit = new UnitEntry('u', 'U', array_map(static fn (int $i): string => 'c'.$i, range(1, 20)), 100_000, 1_000, 1_000, 100_000, array_map(static fn (int $i): OptionGroup => new OptionGroup('g'.$i, 'G', [new Option('o'.$i, 'O')]), range(1, 50)));
        self::assertCount(20, $unit->categories);
        self::assertCount(50, $unit->optionGroups);
        self::assertSame(1_000, $unit->maxModels);
        self::assertSame(3, new UnitEntry('u', 'U', minModels: 3)->maxModels);

        self::assertSame(200, new Slot('c', 200, 200)->max);
        self::assertSame(1, new Slot('c', 0, 1)->max);
        self::assertSame(20, new DetachmentType('d', 'D', array_map(static fn (int $i): Slot => new Slot('c'.$i), range(1, 50)), 20)->max);
        self::assertCount(50, new DetachmentType('d', 'D', array_map(static fn (int $i): Slot => new Slot('c'.$i), range(1, 50)))->slots);

        $units = array_map(static fn (int $i): UnitEntry => new UnitEntry('u'.$i, 'U'), range(1, 2_000));
        $types = array_map(static fn (int $i): DetachmentType => new DetachmentType('d'.$i, 'D', [new Slot('c')]), range(1, 50));
        $constraints = array_map(static fn (int $i): UnitCount => new UnitCount(Selector::category('c'), max: $i), range(1, 200));
        $catalogue = new Catalogue('c', 'C', '1', $units, $types, 20, 20, $constraints);
        self::assertCount(2_000, $catalogue->units);
        self::assertSame(20, $catalogue->minDetachments);
        self::assertSame(1, new Catalogue('c', 'C', '1', [new UnitEntry('u', 'U')], [new DetachmentType('d', 'D', [new Slot('c')])], 0, 1)->maxDetachments);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusedCatalogues(): iterable
    {
        $unit = new UnitEntry('u', 'U');
        $type = new DetachmentType('d', 'D', [new Slot('c')]);
        $option = new Option('o', 'O');

        yield 'negative option points' => [static fn (): Option => new Option('o', 'O', -1), 'The points of the option "o" is from 0 to 100000; -1 is not.'];
        yield 'option points too high' => [static fn (): Option => new Option('o', 'O', 100_001), 'The points of the option "o" is from 0 to 100000; 100001 is not.'];
        yield 'option taken never' => [static fn (): Option => new Option('o', 'O', 0, 0), 'The most times the option "o" is taken is from 1 to 1000; 0 is not.'];
        yield 'option taken too often' => [static fn (): Option => new Option('o', 'O', 0, 1_001), 'The most times the option "o" is taken is from 1 to 1000; 1001 is not.'];
        yield 'group without options' => [static fn (): OptionGroup => new OptionGroup('g', 'G', []), 'The group "g" has 1 to 100 options.'];
        yield 'group of too many options' => [static fn (): OptionGroup => new OptionGroup('g', 'G', array_map(static fn (int $i): Option => new Option('o'.$i, 'O'), range(0, 100))), 'The group "g" has 1 to 100 options.'];
        yield 'group of an option twice' => [static fn (): OptionGroup => new OptionGroup('g', 'G', [$option, $option]), 'The group "g" has two options "o".'];
        yield 'group of a negative minimum' => [static fn (): OptionGroup => new OptionGroup('g', 'G', [$option], -1), 'The fewest selections in the group "g" is from 0 to 1000; -1 is not.'];
        yield 'group of a minimum too high' => [static fn (): OptionGroup => new OptionGroup('g', 'G', [$option], 1_001, 1_001), 'The fewest selections in the group "g" is from 0 to 1000; 1001 is not.'];
        yield 'group of a maximum below its minimum' => [static fn (): OptionGroup => new OptionGroup('g', 'G', [$option], 2, 1), 'The most selections in the group "g" is from 2 to 1000; 1 is not.'];
        yield 'group of no selection' => [static fn (): OptionGroup => new OptionGroup('g', 'G', [$option], 0, 0), 'The most selections in the group "g" is from 1 to 1000; 0 is not.'];
        yield 'group of a maximum too high' => [static fn (): OptionGroup => new OptionGroup('g', 'G', [$option], 0, 1_001), 'The most selections in the group "g" is from 1 to 1000; 1001 is not.'];
        yield 'unit of negative points' => [static fn (): UnitEntry => new UnitEntry('u', 'U', points: -1), 'The points of the unit "u" is from 0 to 100000; -1 is not.'];
        yield 'unit of points too high' => [static fn (): UnitEntry => new UnitEntry('u', 'U', points: 100_001), 'The points of the unit "u" is from 0 to 100000; 100001 is not.'];
        yield 'unit of no model' => [static fn (): UnitEntry => new UnitEntry('u', 'U', minModels: 0), 'The fewest models of the unit "u" is from 1 to 1000; 0 is not.'];
        yield 'unit of too many models' => [static fn (): UnitEntry => new UnitEntry('u', 'U', minModels: 1_001), 'The fewest models of the unit "u" is from 1 to 1000; 1001 is not.'];
        yield 'unit of a maximum below its minimum' => [static fn (): UnitEntry => new UnitEntry('u', 'U', minModels: 5, maxModels: 4), 'The most models of the unit "u" is from 5 to 1000; 4 is not.'];
        yield 'unit of a maximum too high' => [static fn (): UnitEntry => new UnitEntry('u', 'U', maxModels: 1_001), 'The most models of the unit "u" is from 1 to 1000; 1001 is not.'];
        yield 'unit of negative model points' => [static fn (): UnitEntry => new UnitEntry('u', 'U', pointsPerModel: -1), 'The points of each model of the unit "u" is from 0 to 100000; -1 is not.'];
        yield 'unit of model points too high' => [static fn (): UnitEntry => new UnitEntry('u', 'U', pointsPerModel: 100_001), 'The points of each model of the unit "u" is from 0 to 100000; 100001 is not.'];
        yield 'unit of too many categories' => [static fn (): UnitEntry => new UnitEntry('u', 'U', array_map(static fn (int $i): string => 'c'.$i, range(0, 20))), 'The unit "u" has at most 20 categories.'];
        yield 'unit of a category twice' => [static fn (): UnitEntry => new UnitEntry('u', 'U', ['a', 'b', 'b']), 'The unit "u" has the category "b" twice.'];
        yield 'unit of a category twice apart' => [static fn (): UnitEntry => new UnitEntry('u', 'U', ['a', 'b', 'a']), 'The unit "u" has the category "a" twice.'];
        yield 'unit of a refused category' => [static fn (): UnitEntry => new UnitEntry('u', 'U', ['A']), 'The code of a category is'];
        yield 'unit of too many groups' => [static fn (): UnitEntry => new UnitEntry('u', 'U', optionGroups: array_map(static fn (int $i): OptionGroup => new OptionGroup('g'.$i, 'G', [new Option('o'.$i, 'O')]), range(0, 50))), 'The unit "u" has at most 50 option groups.'];
        yield 'unit of an option in two groups' => [static fn (): UnitEntry => new UnitEntry('u', 'U', optionGroups: [new OptionGroup('g', 'G', [$option]), new OptionGroup('h', 'H', [$option])]), 'The unit "u" has two options "o".'];
        yield 'slot of a negative minimum' => [static fn (): Slot => new Slot('c', -1), 'The fewest units of the slot "c" is from 0 to 200; -1 is not.'];
        yield 'slot of a minimum too high' => [static fn (): Slot => new Slot('c', 201), 'The fewest units of the slot "c" is from 0 to 200; 201 is not.'];
        yield 'slot of no unit' => [static fn (): Slot => new Slot('c', 0, 0), 'The most units of the slot "c" is from 1 to 200; 0 is not.'];
        yield 'slot of a maximum below its minimum' => [static fn (): Slot => new Slot('c', 3, 2), 'The most units of the slot "c" is from 3 to 200; 2 is not.'];
        yield 'slot of a maximum too high' => [static fn (): Slot => new Slot('c', 0, 201), 'The most units of the slot "c" is from 1 to 200; 201 is not.'];
        yield 'detachment type without slots' => [static fn (): DetachmentType => new DetachmentType('d', 'D', []), 'The detachment type "d" has 1 to 50 slots.'];
        yield 'detachment type of too many slots' => [static fn (): DetachmentType => new DetachmentType('d', 'D', array_map(static fn (int $i): Slot => new Slot('c'.$i), range(0, 50))), 'The detachment type "d" has 1 to 50 slots.'];
        yield 'detachment type of a slot twice' => [static fn (): DetachmentType => new DetachmentType('d', 'D', [new Slot('c'), new Slot('c', 1)]), 'The detachment type "d" has two slots "c".'];
        yield 'detachment type of no detachment' => [static fn (): DetachmentType => new DetachmentType('d', 'D', [new Slot('c')], 0), 'The most detachments "d" is from 1 to 20; 0 is not.'];
        yield 'detachment type of too many' => [static fn (): DetachmentType => new DetachmentType('d', 'D', [new Slot('c')], 21), 'The most detachments "d" is from 1 to 20; 21 is not.'];
        yield 'catalogue without units' => [static fn (): Catalogue => new Catalogue('c', 'C', '1', [], [$type]), 'A catalogue has 1 to 2000 units.'];
        yield 'catalogue of too many units' => [static fn (): Catalogue => new Catalogue('c', 'C', '1', array_map(static fn (int $i): UnitEntry => new UnitEntry('u'.$i, 'U'), range(0, 2_000)), [$type]), 'A catalogue has 1 to 2000 units.'];
        yield 'catalogue of a unit twice' => [static fn (): Catalogue => new Catalogue('c', 'C', '1', [$unit, $unit], [$type]), 'A catalogue has two units "u".'];
        yield 'catalogue without detachment types' => [static fn (): Catalogue => new Catalogue('c', 'C', '1', [$unit], []), 'A catalogue has 1 to 50 detachment types.'];
        yield 'catalogue of a detachment type twice' => [static fn (): Catalogue => new Catalogue('c', 'C', '1', [$unit], [$type, $type]), 'A catalogue has two detachment types "d".'];
        yield 'catalogue of a negative minimum' => [static fn (): Catalogue => new Catalogue('c', 'C', '1', [$unit], [$type], -1), 'The fewest detachments of an army is from 0 to 20; -1 is not.'];
        yield 'catalogue of a minimum too high' => [static fn (): Catalogue => new Catalogue('c', 'C', '1', [$unit], [$type], 21, 21), 'The fewest detachments of an army is from 0 to 20; 21 is not.'];
        yield 'catalogue of a maximum below its minimum' => [static fn (): Catalogue => new Catalogue('c', 'C', '1', [$unit], [$type], 3, 2), 'The most detachments of an army is from 3 to 20; 2 is not.'];
        yield 'catalogue of no detachment' => [static fn (): Catalogue => new Catalogue('c', 'C', '1', [$unit], [$type], 0, 0), 'The most detachments of an army is from 1 to 20; 0 is not.'];
        yield 'catalogue of too many detachments' => [static fn (): Catalogue => new Catalogue('c', 'C', '1', [$unit], [$type], 1, 21), 'The most detachments of an army is from 1 to 20; 21 is not.'];
        yield 'catalogue of too many constraints' => [static fn (): Catalogue => new Catalogue('c', 'C', '1', [$unit], [$type], constraints: array_fill(0, 201, new UnitCount(Selector::unit('u'), max: 1))), 'A catalogue has at most 200 constraints.'];
        yield 'constraint of an unknown unit' => [static fn (): Catalogue => new Catalogue('c', 'C', '1', [$unit], [$type], constraints: [new PointsShare(Selector::unit('x'), maxPercent: 50)]), 'A constraint of the catalogue "c" names the unit "x", which the catalogue does not hold.'];
        yield 'catalogue of a refused version' => [static fn (): Catalogue => new Catalogue('c', 'C', ' 1', [$unit], [$type]), 'The version of a catalogue is'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refusedCatalogues')]
    public function testRefusedCataloguesSayWhy(callable $build, string $message): void
    {
        $this->expectException(InvalidCatalogue::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    /**
     * @return iterable<string, array{array<string, mixed>, string}>
     */
    public static function refusedData(): iterable
    {
        $data = Skirmish::data();

        yield 'an unknown key' => [['colour' => 'red'] + $data, 'A catalogue has no key "colour".'];
        yield 'no units' => [array_diff_key($data, ['units' => true]), 'A catalogue needs its "units".'];
        yield 'units not a list' => [['units' => ['a' => 1]] + $data, 'The "units" of a catalogue is a list.'];
        yield 'a unit not an object' => [['units' => [1]] + $data, 'A unit is an object of keys.'];
        yield 'a version not a string' => [['version' => 2026] + $data, 'The "version" of a catalogue is a string.'];
        yield 'a minimum not an integer' => [['min_detachments' => '1'] + $data, 'The "min_detachments" of a catalogue is an integer.'];
        yield 'a constraint of no type' => [['constraints' => [['category' => 'hero', 'max' => 1]]] + $data, 'A constraint is an object whose "type" is "count" or "points-share".'];
        yield 'a constraint not an object' => [['constraints' => ['count']] + $data, 'A constraint is an object whose "type" is "count" or "points-share".'];
        yield 'a count of an unknown key' => [['constraints' => [['type' => 'count', 'category' => 'hero', 'max' => 1, 'per' => 'turn']]] + $data, 'A count has no key "per".'];
        yield 'a count of an unknown scope' => [['constraints' => [['type' => 'count', 'category' => 'hero', 'max' => 1, 'scope' => 'turn']]] + $data, 'The "scope" of a count is "army" or "detachment".'];
        yield 'a count of a scope not a string' => [['constraints' => [['type' => 'count', 'category' => 'hero', 'max' => 1, 'scope' => 1]]] + $data, 'The "scope" of a count is a string.'];
        yield 'a count of a unit and a category' => [['constraints' => [['type' => 'count', 'unit' => 'ogre', 'category' => 'hero', 'max' => 1]]] + $data, 'A constraint counts either a "unit" or a "category", given as a string.'];
        yield 'a count of neither' => [['constraints' => [['type' => 'count', 'max' => 1]]] + $data, 'A constraint counts either a "unit" or a "category", given as a string.'];
        yield 'a count of a unit not a string' => [['constraints' => [['type' => 'count', 'unit' => 1, 'max' => 1]]] + $data, 'A constraint counts either a "unit" or a "category", given as a string.'];
        yield 'a count of a category not a string' => [['constraints' => [['type' => 'count', 'category' => true, 'max' => 1]]] + $data, 'A constraint counts either a "unit" or a "category", given as a string.'];
        yield 'a share of an unknown key' => [['constraints' => [['type' => 'points-share', 'category' => 'hero', 'max_percent' => 1, 'scope' => 'army']]] + $data, 'A points share has no key "scope".'];
        yield 'an option per model not a boolean' => [['units' => [['code' => 'u', 'name' => 'U', 'option_groups' => [['code' => 'g', 'name' => 'G', 'options' => [['code' => 'o', 'name' => 'O', 'per_model' => 1]]]]]]] + $data, 'The "per_model" of an option is true or false.'];
        yield 'a detachment type without slots' => [['detachment_types' => [['code' => 'd', 'name' => 'D']]] + $data, 'A detachment type needs its "slots".'];
        yield 'a slot maximum not an integer' => [['detachment_types' => [['code' => 'd', 'name' => 'D', 'slots' => [['category' => 'c', 'max' => '1']]]]] + $data, 'The "max" of a slot is an integer or null.'];
        yield 'categories not strings' => [['units' => [['code' => 'u', 'name' => 'U', 'categories' => [1]]]] + $data, 'The "categories" of a unit are strings.'];
    }

    /**
     * @param array<string, mixed> $data
     */
    #[DataProvider('refusedData')]
    public function testDataThatDoesNotReadIsRefused(array $data, string $message): void
    {
        $this->expectException(InvalidCatalogue::class);
        $this->expectExceptionMessageIsOrContains($message);

        Catalogue::fromArray($data);
    }

    public function testJsonThatDoesNotReadIsRefused(): void
    {
        foreach (['{' => 'A catalogue in JSON does not read: Syntax error', '[]' => 'A catalogue needs its "code".', str_repeat('[', 16).str_repeat(']', 16) => 'A catalogue in JSON does not read: Maximum stack depth exceeded', str_repeat('[', 15).str_repeat(']', 15) => 'A catalogue is an object of keys.', '"'.str_repeat('a', 4_194_303).'"' => 'A catalogue in JSON has at most 4194304 bytes.', '"'.str_repeat('a', 4_194_302).'"' => 'A catalogue is an object of keys.'] as $json => $message) {
            try {
                Catalogue::fromJson($json);
                self::fail('The JSON was read.');
            } catch (InvalidCatalogue $exception) {
                self::assertStringStartsWith($message, $exception->getMessage());
            }
        }
    }

    public function testTheProviderFindsWhatItHolds(): void
    {
        $catalogue = Skirmish::catalogue();
        $provider = new InMemoryCatalogueProvider($catalogue);

        self::assertSame($catalogue, $provider->find('ember-vale'));
        self::assertNull($provider->find('other'));
    }

    public function testADetachmentTypeGivesTheSlotOfAUnitsFirstCategoryItTakes(): void
    {
        $type = new DetachmentType('d', 'D', [new Slot('troops'), new Slot('elite')]);

        self::assertSame('elite', $type->slotFor(new UnitEntry('u', 'U', ['elite', 'troops']))?->category);
        self::assertSame('troops', $type->slotFor(new UnitEntry('u', 'U', ['hero', 'troops']))?->category);
        self::assertNull($type->slotFor(new UnitEntry('u', 'U', ['hero'])));
        self::assertNull($type->slotFor(new UnitEntry('u', 'U')));
    }

    public function testAnOptionCostsForTheUnitOrForEachModel(): void
    {
        self::assertSame(15, new Option('o', 'O', 5, 3)->cost(3, 10));
        self::assertSame(150, new Option('o', 'O', 5, 3, true)->cost(3, 10));
        self::assertSame(0, new Option('o', 'O', 0, 3, true)->cost(3, 10));
    }
}
