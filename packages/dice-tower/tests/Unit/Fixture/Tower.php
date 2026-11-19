<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Tests\Unit\Fixture;

use Phil\Resource\ResourceReference;
use Random\Engine\Mt19937;
use Random\Randomizer;
use Symfony\Component\Clock\MockClock;
use Tilttek\Dice\Custom\CustomDice;
use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Custom\Shape;
use Tilttek\Dice\DiceRoller;
use Tilttek\DiceBundle\Application\DiceRolls;
use Tilttek\DiceBundle\Infrastructure\Memory\InMemoryRecordedRollRepository;
use Tilttek\DiceTower\Application\Contract\TowerPublisher;
use Tilttek\DiceTower\Application\DiceTower;
use Tilttek\DiceTower\Application\TowerRelays;
use Tilttek\DiceTower\Infrastructure\Memory\InMemoryTowerDropRepository;

/**
 * A tower on the journal and the log in memory, its dice drawn from a seeded engine, with a coin and the time stopped.
 */
final class Tower
{
    public InMemoryRecordedRollRepository $rolls;

    public InMemoryTowerDropRepository $drops;

    public MockClock $clock;

    public DiceRolls $dice;

    public DiceTower $tower;

    public function __construct(TowerPublisher $publisher = new RecordingPublisher(), TowerRelays $relays = new TowerRelays(), int $history = 3)
    {
        $this->rolls = new InMemoryRecordedRollRepository();
        $this->drops = new InMemoryTowerDropRepository();
        $this->clock = new MockClock('2026-11-12 20:00:00 UTC');
        $this->dice = new DiceRolls(new DiceRoller(customDice: self::customDice()), new Randomizer(new Mt19937(7)), $this->rolls, $this->clock);
        $this->tower = new DiceTower($this->drops, $this->rolls, $this->dice, $this->clock, $publisher, $relays, $history);
    }

    public static function customDice(): CustomDice
    {
        return new CustomDice(new DieDefinition('coin', new Face('heads', 'Heads', 1, shape: Shape::Circle), new Face('tails', 'Tails', 0, shape: Shape::Square)));
    }

    public static function table(string $id = '7'): ResourceReference
    {
        return ResourceReference::of('table', $id);
    }

    public static function member(string $id = '1'): ResourceReference
    {
        return ResourceReference::of('member', $id);
    }
}
