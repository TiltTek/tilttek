<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Tests\Unit\Fixture;

use Phil\Resource\ResourceReference;
use Random\Randomizer;
use Symfony\Component\Clock\MockClock;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Custom\Shape;
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Notation\Notation;
use Tilttek\Dice\Notation\Notations;
use Tilttek\DiceBundle\Application\DiceRolls;
use Tilttek\DiceBundle\Application\DieAppearance;
use Tilttek\DiceBundle\Application\Port\DiceAppearance;
use Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository;
use Tilttek\DiceBundle\Infrastructure\Memory\InMemoryRecordedRollRepository;

/**
 * The dice the tests roll: a coin that counts, colours that do not, Finnish letters, and a member whose d6 are red.
 */
final class Dice
{
    public static function customDice(): CustomDice
    {
        return new CustomDice(
            new DieDefinition('coin', new Face('heads', 'Heads', 1, shape: Shape::Circle), new Face('tails', 'Tails', 0, shape: Shape::Square)),
            new DieDefinition('colours', new Face('red', 'Red', color: '#ff0000', shape: Shape::Circle), new Face('star', 'Star', image: 'star.svg'), new Face('blank', 'Blank', shape: Shape::Blank)),
        );
    }

    public static function roller(): DiceRoller
    {
        return new DiceRoller(customDice: self::customDice(), notations: new Notations(['fi' => new Notation('n')]));
    }

    public static function rolls(Randomizer $secure, ?RecordedRollRepository $rolls = null, ?DiceAppearance $appearance = null): DiceRolls
    {
        return $appearance instanceof DiceAppearance
            ? new DiceRolls(self::roller(), $secure, $rolls ?? new InMemoryRecordedRollRepository(), new MockClock('2026-11-07 03:00:00 UTC'), $appearance)
            : new DiceRolls(self::roller(), $secure, $rolls ?? new InMemoryRecordedRollRepository(), new MockClock('2026-11-07 03:00:00 UTC'));
    }

    public static function redDice(): DiceAppearance
    {
        return new class implements DiceAppearance {
            public function appearance(ResourceReference $member, int $sides): ?DieAppearance
            {
                return 'member:1' === $member->toString() && 6 === $sides ? new DieAppearance('Ember', '#aa0000', 'resin', 'ember.png') : null;
            }
        };
    }
}
