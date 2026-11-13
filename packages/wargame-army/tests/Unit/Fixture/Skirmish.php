<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Tests\Unit\Fixture;

use Tilttek\WargameArmy\Catalogue;

/**
 * An invented catalogue for the tests: no game, no publisher, no licence to hold.
 */
final class Skirmish
{
    /**
     * @return array<string, mixed>
     */
    public static function data(): array
    {
        return [
            'code' => 'ember-vale',
            'name' => 'Ember Vale Skirmish',
            'version' => '2026.1',
            'units' => [
                ['code' => 'captain', 'name' => 'Captain', 'categories' => ['hero', 'leader'], 'points' => 60, 'option_groups' => [
                    ['code' => 'weapon', 'name' => 'Weapon', 'min' => 1, 'max' => 1, 'options' => [
                        ['code' => 'sword', 'name' => 'Sword'],
                        ['code' => 'bow', 'name' => 'Bow', 'points' => 5],
                    ]],
                    ['code' => 'gear', 'name' => 'Gear', 'max' => 2, 'options' => [
                        ['code' => 'banner', 'name' => 'Banner', 'points' => 10],
                        ['code' => 'horse', 'name' => 'Horse', 'points' => 15],
                    ]],
                ]],
                ['code' => 'scouts', 'name' => 'Scouts', 'categories' => ['troops'], 'points' => 50, 'min_models' => 5, 'max_models' => 10, 'points_per_model' => 10, 'option_groups' => [
                    ['code' => 'kit', 'name' => 'Kit', 'options' => [
                        ['code' => 'long-rifle', 'name' => 'Long rifle', 'points' => 2, 'per_model' => true],
                    ]],
                ]],
                ['code' => 'guards', 'name' => 'Guards', 'categories' => ['troops', 'elite'], 'points' => 80, 'min_models' => 5],
                ['code' => 'ogre', 'name' => 'Ogre', 'categories' => ['monster'], 'points' => 120],
            ],
            'detachment_types' => [
                ['code' => 'vanguard', 'name' => 'Vanguard', 'max' => 1, 'slots' => [
                    ['category' => 'hero', 'min' => 1, 'max' => 1],
                    ['category' => 'troops', 'min' => 1, 'max' => 3],
                    ['category' => 'monster', 'max' => 1],
                ]],
                ['code' => 'raid', 'name' => 'Raid', 'slots' => [
                    ['category' => 'hero', 'max' => 1],
                    ['category' => 'troops', 'max' => 2],
                ]],
            ],
            'min_detachments' => 1,
            'max_detachments' => 2,
            'constraints' => [
                ['type' => 'count', 'category' => 'leader', 'min' => 1],
                ['type' => 'count', 'unit' => 'ogre', 'max' => 1],
                ['type' => 'points-share', 'category' => 'hero', 'max_percent' => 25],
            ],
        ];
    }

    public static function catalogue(): Catalogue
    {
        return Catalogue::fromArray(self::data());
    }
}
