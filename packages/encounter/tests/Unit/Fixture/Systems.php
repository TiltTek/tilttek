<?php

declare(strict_types=1);

namespace Tilttek\Encounter\Tests\Unit\Fixture;

use Tilttek\Encounter\Difficulty\BudgetRules;
use Tilttek\Encounter\Difficulty\LevelRules;
use Tilttek\Encounter\Difficulty\Multiplier;

/**
 * Two invented game systems: one rated by budget, one by level.
 */
final class Systems
{
    private function __construct()
    {
    }

    public static function budget(): BudgetRules
    {
        return new BudgetRules(
            'budget-game',
            ['low', 'moderate', 'high'],
            ['1' => ['low' => 50, 'moderate' => 75, 'high' => 100], '2' => ['low' => 100, 'moderate' => 150, 'high' => 200]],
            ['1/4' => 10, '1/2' => 25, '1' => 50, '2' => 100],
            [new Multiplier(2, 150), new Multiplier(4, 200)],
        );
    }

    public static function level(): LevelRules
    {
        return new LevelRules(
            'level-game',
            ['trivial' => 40, 'moderate' => 80, 'severe' => 120],
            4,
            ['trivial' => 10, 'moderate' => 20, 'severe' => 30],
            [-2 => 20, -1 => 30, 0 => 40, 1 => 60, 2 => 80],
        );
    }
}
