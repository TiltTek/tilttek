<?php

declare(strict_types=1);

namespace Tilttek\Downtime\Tests\Unit\Fixture;

use Tilttek\Downtime\Activity;
use Tilttek\Downtime\Band;
use Tilttek\Downtime\Cost;
use Tilttek\Downtime\Effect;
use Tilttek\Downtime\Outcome;
use Tilttek\Downtime\Resolution;
use Tilttek\Downtime\SystemRules;

/**
 * The activities of the tests: crafting rolled over five days, training fixed over three.
 */
final class Rules
{
    public const string SYSTEM = 'fantasy';

    private function __construct()
    {
    }

    public static function crafting(int $days = 5): Activity
    {
        return new Activity('crafting', 'Crafting', $days, [
            new Outcome('made', 'Made', [new Effect('items', 1)]),
            new Outcome('spoiled', 'Spoiled'),
            new Outcome('masterwork', 'Masterwork', [new Effect('items', 1), new Effect('renown', 1)]),
        ], Resolution::rolled('1d20+2', [new Band(1, 9, 'spoiled'), new Band(10, 19, 'made'), new Band(20, 30, 'masterwork')]), [Cost::perDay('gp', 5), new Cost('supplies', 1)]);
    }

    public static function training(int $days = 3): Activity
    {
        return new Activity('training', 'Training', $days, [new Outcome('learned', 'Learned', [new Effect('skills', 1)])], Resolution::fixed('learned'));
    }

    public static function system(): SystemRules
    {
        return new SystemRules(self::SYSTEM, [self::crafting(), self::training()]);
    }
}
