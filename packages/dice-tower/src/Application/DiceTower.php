<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Application;

use InvalidArgumentException;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use RoundingMode;
use Tilttek\Dice\Exception\CannotRoll;
use Tilttek\Dice\Exception\InvalidExpression;
use Tilttek\Dice\Exception\LimitExceeded;
use Tilttek\DiceBundle\Application\DiceRolls;
use Tilttek\DiceBundle\Application\Exception\UnknownRoll;
use Tilttek\DiceBundle\Domain\Model\RecordedRoll;
use Tilttek\DiceBundle\Domain\Repository\RecordedRollRepository;
use Tilttek\DiceTower\Application\Contract\TowerPublisher;
use Tilttek\DiceTower\Domain\Contract\TowerDropRepository;
use Tilttek\DiceTower\Domain\Model\TowerDrop;

/**
 * The shared dice tower of each table: every roll dropped in it is seen by everyone at the table, in order. The rolls
 * are those of the journal of the dice; the tower keeps where each fell, pushes each new entry to the table's viewers
 * and hands it to the relays. What a viewer missed is read again from the log, by position: nothing is kept between
 * two requests.
 */
final readonly class DiceTower
{
    public const int HISTORY = 20;

    public const int MAX_HISTORY = 200;

    public function __construct(
        private TowerDropRepository $drops,
        private RecordedRollRepository $rolls,
        private DiceRolls $dice,
        private ClockInterface $clock,
        private TowerPublisher $publisher = new NoTowerPublisher(),
        private TowerRelays $relays = new TowerRelays(),
        private int $history = self::HISTORY,
    ) {
        if ($history < 1 || $history > self::MAX_HISTORY) {
            throw new InvalidArgumentException(\sprintf('A tower shows 1 to %d entries at once; %d is out of range.', self::MAX_HISTORY, $history));
        }
    }

    /**
     * Rolls an expression in the journal, owned by the roller, and drops it in the table's tower.
     *
     * @throws InvalidExpression
     * @throws LimitExceeded
     * @throws CannotRoll
     */
    public function roll(ResourceReference $table, string $expression, ?ResourceReference $roller = null, ?string $locale = null, RoundingMode $rounding = RoundingMode::NegativeInfinity): TowerEntry
    {
        $roll = $this->dice->rollReplayable($expression, $roller, $locale, $rounding);

        return $this->place($table, $roll, $roller);
    }

    /**
     * Drops a roll of the journal in the table's tower.
     *
     * @throws UnknownRoll when the journal holds no roll with this identifier
     */
    public function drop(ResourceReference $table, string $roll, ?ResourceReference $roller = null): TowerEntry
    {
        $recorded = $this->rolls->get($roll) ?? throw UnknownRoll::withId($roll);

        return $this->place($table, $recorded, $roller);
    }

    /**
     * The entries of a table's tower, the earliest first: the last ones, or those after a position — what a viewer
     * missed —, as many as the history at most. A roll erased from the journal since is left out.
     *
     * @return list<TowerEntry>
     */
    public function entries(ResourceReference $table, ?int $after = null): array
    {
        $drops = null === $after ? $this->drops->latest($table, $this->history) : $this->drops->after($table, $after, $this->history);
        $entries = [];

        foreach ($drops as $drop) {
            $entry = $this->read($drop);

            if ($entry instanceof TowerEntry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    public function entry(ResourceReference $table, int $position): ?TowerEntry
    {
        $drop = $this->drops->at($table, $position);

        return $drop instanceof TowerDrop ? $this->read($drop) : null;
    }

    /**
     * Erases a person's drops from every tower, when the person is erased; their rolls are erased from the journal of
     * the dice by its own `eraseOwner()`.
     */
    public function eraseRoller(ResourceReference $roller): int
    {
        return $this->drops->eraseRoller($roller);
    }

    private function place(ResourceReference $table, RecordedRoll $roll, ?ResourceReference $roller): TowerEntry
    {
        $entry = TowerEntry::of($this->drops->append($table, $roll->id(), $roller, $this->clock->now()), $roll);
        $this->publisher->publish($entry);
        $this->relays->dispatch($entry);

        return $entry;
    }

    private function read(TowerDrop $drop): ?TowerEntry
    {
        $roll = $this->rolls->get($drop->roll());

        return $roll instanceof RecordedRoll ? TowerEntry::of($drop, $roll) : null;
    }
}
