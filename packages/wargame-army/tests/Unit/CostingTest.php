<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Tests\Unit;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Constraint\Selector;
use Tilttek\WargameArmy\Costing;
use Tilttek\WargameArmy\Detachment;
use Tilttek\WargameArmy\DetachmentType;
use Tilttek\WargameArmy\Option;
use Tilttek\WargameArmy\OptionGroup;
use Tilttek\WargameArmy\Slot;
use Tilttek\WargameArmy\Tests\Unit\Fixture\Lists;
use Tilttek\WargameArmy\Tests\Unit\Fixture\Skirmish;
use Tilttek\WargameArmy\UnitEntry;
use Tilttek\WargameArmy\UnitSelection;

#[CoversClass(Costing::class)]
final class CostingTest extends TestCase
{
    public function testTheCostsOfAListByUnitDetachmentAndTotal(): void
    {
        $costing = Costing::of(Lists::valid(), Skirmish::catalogue());

        // Captain 60 + bow 5 + banner 10; scouts 50 + 1 model × 10 + rifles 2 × 6; guards 80; ogre 120; guards 80.
        self::assertSame([[75, 72, 80, 120], [80]], $costing->units);
        self::assertSame([347, 80], $costing->detachments);
        self::assertSame(427, $costing->total);
        self::assertSame(72, $costing->unit(0, 1));
        self::assertSame(80, $costing->unit(1, 0));
        self::assertSame(0, $costing->unit(1, 1));
        self::assertSame(0, $costing->unit(2, 0));
        self::assertSame(80, $costing->detachment(1));
        self::assertSame(0, $costing->detachment(2));
    }

    public function testWhatTheCatalogueDoesNotHoldCostsNothing(): void
    {
        $costing = Costing::of(Lists::broken(), Skirmish::catalogue());

        // Captain 60 + banners 30 + horse 15, axe unknown; two ogres; dragon unknown.
        self::assertSame([105, 120, 120, 0], $costing->units[0]);
        self::assertSame([154], $costing->units[1]);
        self::assertSame([80], $costing->units[2]);
        self::assertSame(579, $costing->total);
    }

    public function testAnUnknownUnitDoesNotStopTheUnitsAfterIt(): void
    {
        $catalogue = Skirmish::catalogue();
        $list = new ArmyList('l', 'L', 'ember-vale', '2026.1', 500, [new Detachment('vanguard', null, [new UnitSelection('dragon'), new UnitSelection('ogre'), new UnitSelection('ogre')])]);
        $costing = Costing::of($list, $catalogue);

        self::assertSame([[0, 120, 120]], $costing->units);
        self::assertSame(240, $costing->total);
        self::assertSame(2, $costing->count(Selector::unit('ogre'), $catalogue, 0));
        self::assertSame(2, $costing->count(Selector::category('monster'), $catalogue));
        self::assertSame(240, $costing->points(Selector::unit('ogre'), $catalogue));
    }

    public function testAUnitCostsItsModelsAndOptions(): void
    {
        $unit = new UnitEntry('u', 'U', [], 100, 3, 10, 7, [new OptionGroup('g', 'G', [new Option('a', 'A', 4, 5), new Option('b', 'B', 3, 5, true)], 0, 10)]);

        self::assertSame(100, Costing::unitCost($unit, new UnitSelection('u', 3)));
        self::assertSame(114, Costing::unitCost($unit, new UnitSelection('u', 5)));
        self::assertSame(100, Costing::unitCost($unit, new UnitSelection('u', 1)));
        self::assertSame(100 + 14 + 8 + 30, Costing::unitCost($unit, new UnitSelection('u', 5, ['a' => 2, 'b' => 2])));
        self::assertSame(100, Costing::unitCost($unit, new UnitSelection('u', 3, ['zz' => 4])));
    }

    public function testTheUnitsASelectorTakesAreCountedAndCosted(): void
    {
        $catalogue = Skirmish::catalogue();
        $costing = Costing::of(Lists::valid(), $catalogue);

        self::assertSame(2, $costing->count(Selector::unit('guards'), $catalogue));
        self::assertSame(1, $costing->count(Selector::unit('guards'), $catalogue, 0));
        self::assertSame(1, $costing->count(Selector::unit('guards'), $catalogue, 1));
        self::assertSame(0, $costing->count(Selector::unit('guards'), $catalogue, 2));
        self::assertSame(0, $costing->count(Selector::unit('dragon'), $catalogue));
        self::assertSame(3, $costing->count(Selector::category('troops'), $catalogue));
        self::assertSame(2, $costing->count(Selector::category('troops'), $catalogue, 0));
        self::assertSame(0, $costing->count(Selector::category('hero'), $catalogue, 1));
        self::assertSame(160, $costing->points(Selector::unit('guards'), $catalogue));
        self::assertSame(232, $costing->points(Selector::category('troops'), $catalogue));
        self::assertSame(0, $costing->points(Selector::category('nothing'), $catalogue));
        self::assertSame(0, Costing::of(Lists::broken(), $catalogue)->count(Selector::unit('dragon'), $catalogue));
        self::assertSame(240, Costing::of(Lists::broken(), $catalogue)->points(Selector::category('monster'), $catalogue));

        $other = new Catalogue('c', 'C', '1', [new UnitEntry('ogre', 'Ogre', ['monster'])], [new DetachmentType('d', 'D', [new Slot('monster')])]);
        self::assertSame(1, $costing->count(Selector::category('monster'), $other));
        self::assertSame(0, $costing->count(Selector::category('troops'), $other));
    }
}
