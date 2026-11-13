<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Tests\Unit\Fixture;

use Tilttek\WargameArmy\ArmyList;
use Tilttek\WargameArmy\Detachment;
use Tilttek\WargameArmy\UnitSelection;

/**
 * Army lists built with the invented catalogue of {@see Skirmish}.
 */
final class Lists
{
    /**
     * A list that keeps every rule of the catalogue: 427 points of 500.
     */
    public static function valid(): ArmyList
    {
        return ArmyList::for(Skirmish::catalogue(), 'list-1', 'Ember Wardens', 500)
            ->withDetachment(new Detachment('vanguard', 'Wardens', [
                new UnitSelection('captain', 1, ['bow' => 1, 'banner' => 1]),
                new UnitSelection('scouts', 6, ['long-rifle' => 1]),
                new UnitSelection('guards', 5),
                new UnitSelection('ogre'),
            ]))
            ->withDetachment(new Detachment('raid', null, [new UnitSelection('guards', 5)]));
    }

    /**
     * A list that breaks a rule of each kind.
     */
    public static function broken(): ArmyList
    {
        return new ArmyList('list-2', 'Bad <list>', 'ember-vale', '2025.9', 300, [
            new Detachment('vanguard', null, [
                new UnitSelection('captain', 2, ['banner' => 3, 'horse' => 1, 'axe' => 1]),
                new UnitSelection('ogre'),
                new UnitSelection('ogre'),
                new UnitSelection('dragon'),
            ]),
            new Detachment('vanguard', 'Second', [new UnitSelection('scouts', 11, ['long-rifle' => 2])]),
            new Detachment('swamp', null, [new UnitSelection('guards', 4)]),
        ]);
    }
}
