<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\ArrayInput;
use Tilttek\WargameArmy\Detachment;
use Tilttek\WargameArmy\Exception\InvalidArmyList;
use Tilttek\WargameArmy\Tests\Unit\Fixture\Skirmish;
use Tilttek\WargameArmy\UnitSelection;

#[CoversClass(ArmyList::class)]
#[CoversClass(Detachment::class)]
#[CoversClass(UnitSelection::class)]
#[CoversClass(ArrayInput::class)]
final class ArmyListTest extends TestCase
{
    public function testAListIsBuiltForACatalogueAtItsVersion(): void
    {
        $list = ArmyList::for(Skirmish::catalogue(), 'list-1', 'Wardens', 500);

        self::assertSame('list-1', $list->id);
        self::assertSame('Wardens', $list->name);
        self::assertSame('ember-vale', $list->catalogue);
        self::assertSame('2026.1', $list->catalogueVersion);
        self::assertSame(500, $list->pointsLimit);
        self::assertSame([], $list->detachments);
    }

    public function testAListChangesByNewLists(): void
    {
        $scouts = new UnitSelection('scouts', 5);
        $list = new ArmyList('l', 'L', 'c', '1', 100)
            ->withDetachment(new Detachment('vanguard', 'First', [$scouts]))
            ->withDetachment(new Detachment('raid'));

        self::assertSame(['vanguard', 'raid'], array_map(static fn (Detachment $detachment): string => $detachment->type, $list->detachments));
        self::assertSame(['raid'], array_map(static fn (Detachment $detachment): string => $detachment->type, $list->withoutDetachment(0)->detachments));
        self::assertSame(['vanguard'], array_map(static fn (Detachment $detachment): string => $detachment->type, $list->withoutDetachment(1)->detachments));
        self::assertSame(['vanguard', 'other'], array_map(static fn (Detachment $detachment): string => $detachment->type, $list->withDetachmentAt(1, new Detachment('other'))->detachments));
        self::assertSame('M', $list->renamed('M')->name);
        self::assertSame(200, $list->withPointsLimit(200)->pointsLimit);
        self::assertSame('2', $list->withCatalogueVersion('2')->catalogueVersion);
        self::assertCount(2, $list->renamed('M')->detachments);
        self::assertCount(2, $list->withPointsLimit(200)->detachments);
        self::assertCount(2, $list->withCatalogueVersion('2')->detachments);
        self::assertSame('l', $list->withCatalogueVersion('2')->id);
        self::assertSame(100, $list->renamed('M')->pointsLimit);

        $detachment = $list->detachments[0];
        self::assertSame('First', $detachment->name);
        self::assertSame([$scouts, $scouts], $detachment->withUnit($scouts)->units);
        self::assertSame([], $detachment->withoutUnit(0)->units);
        self::assertSame('guards', $detachment->withUnitAt(0, new UnitSelection('guards'))->units[0]->unit);
        self::assertSame('First', $detachment->withUnit($scouts)->name);
        self::assertSame('vanguard', $detachment->withoutUnit(0)->type);
        self::assertSame(['b', 'c'], array_map(static fn (UnitSelection $unit): string => $unit->unit, new Detachment('d', null, [new UnitSelection('a'), new UnitSelection('b'), new UnitSelection('c')])->withoutUnit(0)->units));
        self::assertSame(['a', 'c'], array_map(static fn (UnitSelection $unit): string => $unit->unit, new Detachment('d', null, [new UnitSelection('a'), new UnitSelection('b'), new UnitSelection('c')])->withoutUnit(1)->units));
    }

    public function testAUnitAddedToSeveralUnitsKeepsThemAll(): void
    {
        $detachment = new Detachment('d', null, [new UnitSelection('a'), new UnitSelection('b')]);

        self::assertSame(['a', 'b', 'c'], array_map(static fn (UnitSelection $unit): string => $unit->unit, $detachment->withUnit(new UnitSelection('c'))->units));
    }

    public function testAUnitSelectionChangesByNewSelections(): void
    {
        $unit = new UnitSelection('scouts', 5, ['kit' => 1]);

        self::assertSame(6, $unit->withModels(6)->models);
        self::assertSame(['kit' => 1], $unit->withModels(6)->options);
        self::assertSame(['kit' => 1, 'rifle' => 2], $unit->withOption('rifle', 2)->options);
        self::assertSame(['kit' => 1, 'rifle' => 1], $unit->withOption('rifle')->options);
        self::assertSame(['kit' => 3], $unit->withOption('kit', 3)->options);
        self::assertSame([], $unit->withOption('kit', 0)->options);
        self::assertSame(5, $unit->withOption('kit', 0)->models);
        self::assertSame('scouts', $unit->withOption('kit', 0)->unit);
        self::assertSame(1, new UnitSelection('u')->models);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refused(): iterable
    {
        $list = new ArmyList('l', 'L', 'c', '1', 100, [new Detachment('d')]);
        $detachment = new Detachment('d', null, [new UnitSelection('u')]);

        yield 'no points' => [static fn (): ArmyList => new ArmyList('l', 'L', 'c', '1', 0), 'The points limit of an army list is from 1 to 1000000; 0 is not.'];
        yield 'too many points' => [static fn (): ArmyList => new ArmyList('l', 'L', 'c', '1', 1_000_001), 'The points limit of an army list is from 1 to 1000000; 1000001 is not.'];
        yield 'too many detachments' => [static fn (): ArmyList => new ArmyList('l', 'L', 'c', '1', 1, array_fill(0, 21, new Detachment('d'))), 'An army list has at most 20 detachments.'];
        yield 'refused name' => [static fn (): ArmyList => new ArmyList('l', '', 'c', '1', 1), 'The name of an army list is text'];
        yield 'refused catalogue' => [static fn (): ArmyList => new ArmyList('l', 'L', 'C', '1', 1), 'The code of a catalogue is'];
        yield 'refused version' => [static fn (): ArmyList => new ArmyList('l', 'L', 'c', '', 1), 'The version of a catalogue is'];
        yield 'refused identifier' => [static fn (): ArmyList => new ArmyList('', 'L', 'c', '1', 1), 'The identifier of an army list is'];
        yield 'no detachment there' => [static fn (): ArmyList => $list->withoutDetachment(1), 'The army list has no detachment at position 1.'];
        yield 'no detachment to replace' => [static fn (): ArmyList => $list->withDetachmentAt(-1, new Detachment('d')), 'The army list has no detachment at position -1.'];
        yield 'too many units' => [static fn (): Detachment => new Detachment('d', null, array_fill(0, 201, new UnitSelection('u'))), 'A detachment has at most 200 units.'];
        yield 'refused detachment name' => [static fn (): Detachment => new Detachment('d', ' x'), 'The name of a detachment is text'];
        yield 'refused detachment type' => [static fn (): Detachment => new Detachment('D'), 'The code of a detachment type is'];
        yield 'no unit there' => [static fn (): Detachment => $detachment->withoutUnit(1), 'The detachment has no unit at position 1.'];
        yield 'no unit to replace' => [static fn (): Detachment => $detachment->withUnitAt(2, new UnitSelection('u')), 'The detachment has no unit at position 2.'];
        yield 'no model' => [static fn (): UnitSelection => new UnitSelection('u', 0), 'The models of a unit is from 1 to 1000; 0 is not.'];
        yield 'too many models' => [static fn (): UnitSelection => new UnitSelection('u', 1_001), 'The models of a unit is from 1 to 1000; 1001 is not.'];
        yield 'an option taken no time' => [static fn (): UnitSelection => new UnitSelection('u', 1, ['o' => 0]), 'The count of the option "o" is from 1 to 1000; 0 is not.'];
        yield 'an option taken too often' => [static fn (): UnitSelection => new UnitSelection('u', 1, ['o' => 1_001]), 'The count of the option "o" is from 1 to 1000; 1001 is not.'];
        yield 'too many options' => [static fn (): UnitSelection => new UnitSelection('u', 1, array_fill_keys(array_map(static fn (int $i): string => 'o'.$i, range(0, 100)), 1)), 'A unit takes at most 100 distinct options.'];
        yield 'refused option' => [static fn (): UnitSelection => new UnitSelection('u', 1, ['O' => 1]), 'The code of an option is'];
        yield 'refused unit' => [static fn (): UnitSelection => new UnitSelection('U'), 'The code of a unit is'];
    }

    /**
     * @param callable(): mixed $build
     */
    #[DataProvider('refused')]
    public function testRefusedValuesSayWhy(callable $build, string $message): void
    {
        $this->expectException(InvalidArmyList::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testTheBoundsAreAccepted(): void
    {
        $list = new ArmyList('l', 'L', 'c', '1', 1_000_000, array_fill(0, 20, new Detachment('d', null, array_fill(0, 200, new UnitSelection('u', 1_000, array_fill_keys(array_map(static fn (int $i): string => 'o'.$i, range(1, 100)), 1_000))))));

        self::assertCount(20, $list->detachments);
        self::assertSame(1, new ArmyList('l', 'L', 'c', '1', 1)->pointsLimit);
    }

    public function testAListWritesAndReadsBack(): void
    {
        $list = new ArmyList('l/1', 'Été', 'c', '1.0', 750, [
            new Detachment('vanguard', 'First', [new UnitSelection('scouts', 6, ['0' => 1, 'kit' => 2])]),
            new Detachment('raid'),
        ]);
        $json = $list->toJson();

        self::assertSame('{"id":"l/1","name":"Été","catalogue":"c","catalogue_version":"1.0","points_limit":750,"detachments":[{"type":"vanguard","name":"First","units":[{"unit":"scouts","models":6,"options":{"0":1,"kit":2}}]},{"type":"raid","name":null,"units":[]}]}', $json);
        self::assertTrue(ArmyList::fromJson($json)->equals($list));
        self::assertTrue(ArmyList::fromArray($list->toArray())->equals($list));
        self::assertFalse($list->renamed('Other')->equals($list));
        self::assertTrue(ArmyList::fromJson('{"id":"l","name":"L","catalogue":"c","catalogue_version":"1","points_limit":5,"detachments":[{"type":"d","units":[{"unit":"u","options":[3]}]}]}')->equals(new ArmyList('l', 'L', 'c', '1', 5, [new Detachment('d', null, [new UnitSelection('u', 1, ['0' => 3])])])));
        self::assertTrue(ArmyList::fromJson('{"id":"l","name":"L","catalogue":"c","catalogue_version":"1","points_limit":5}')->equals(new ArmyList('l', 'L', 'c', '1', 5)));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function refusedJson(): iterable
    {
        yield 'not JSON' => ['{', 'An army list in JSON does not read: Syntax error'];
        yield 'a list' => ['[1]', 'An army list is an object of keys.'];
        yield 'nested as deep as allowed' => [str_repeat('[', 15).str_repeat(']', 15), 'An army list is an object of keys.'];
        yield 'nested too deep' => [str_repeat('[', 16).str_repeat(']', 16), 'An army list in JSON does not read: Maximum stack depth exceeded'];
        yield 'a long unknown key' => ['{"id":"l","name":"L","catalogue":"c","catalogue_version":"1","points_limit":5,"'.str_repeat('k', 70).'":1}', 'An army list has no key "'.str_repeat('k', 64).'".'];
        yield 'too long' => ['"'.str_repeat('a', 4_194_303).'"', 'An army list in JSON has at most 4194304 bytes.'];
        yield 'an unknown key' => ['{"id":"l","name":"L","catalogue":"c","catalogue_version":"1","points_limit":5,"owner":"x"}', 'An army list has no key "owner".'];
        yield 'no limit' => ['{"id":"l","name":"L","catalogue":"c","catalogue_version":"1"}', 'An army list needs its "points_limit".'];
        yield 'a limit not an integer' => ['{"id":"l","name":"L","catalogue":"c","catalogue_version":"1","points_limit":"5"}', 'The "points_limit" of an army list is an integer.'];
        yield 'a detachment name not a string' => ['{"id":"l","name":"L","catalogue":"c","catalogue_version":"1","points_limit":5,"detachments":[{"type":"d","name":1}]}', 'The "name" of a detachment is a string or null.'];
        yield 'too many detachments' => ['{"id":"l","name":"L","catalogue":"c","catalogue_version":"1","points_limit":5,"detachments":['.implode(',', array_fill(0, 21, '{"type":"d"}')).']}', 'An army list has at most 20 "detachments".'];
        yield 'options not integers' => ['{"id":"l","name":"L","catalogue":"c","catalogue_version":"1","points_limit":5,"detachments":[{"type":"d","units":[{"unit":"u","options":{"o":"1"}}]}]}', 'The "options" of a unit selection is an object of integers.'];
        yield 'options a string' => ['{"id":"l","name":"L","catalogue":"c","catalogue_version":"1","points_limit":5,"detachments":[{"type":"d","units":[{"unit":"u","options":"o"}]}]}', 'The "options" of a unit selection is an object of integers.'];
        yield 'too many options' => ['{"id":"l","name":"L","catalogue":"c","catalogue_version":"1","points_limit":5,"detachments":[{"type":"d","units":[{"unit":"u","options":['.implode(',', array_fill(0, 101, '1')).']}]}]}', 'A unit selection has at most 100 "options".'];
    }

    #[DataProvider('refusedJson')]
    public function testJsonThatDoesNotReadIsRefused(string $json, string $message): void
    {
        $this->expectException(InvalidArmyList::class);
        $this->expectExceptionMessageIsOrContains($message);

        ArmyList::fromJson($json);
    }
}
