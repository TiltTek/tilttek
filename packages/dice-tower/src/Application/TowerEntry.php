<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Application;

use DateTimeImmutable;
use DateTimeInterface;
use Tilttek\DiceBundle\Domain\Model\RecordedRoll;
use Tilttek\DiceTower\Domain\Model\TowerDrop;

/**
 * A roll as the tower shows it to everyone at the table: its position, the table, the identifier of the roll in the
 * journal, the reference of who rolled it, when, and the summary of its result. It carries identifiers and the result
 * only: the application turns a roller's reference into a name where it shows one.
 */
final readonly class TowerEntry
{
    /**
     * @param list<array{expression: string, total: ?int, output: int|string|list<int|string>, sets: list<array{notation: string, die: ?string, dice: list<array{value: int, face: ?string, kept: bool}>}>}> $results
     */
    public function __construct(
        public int $position,
        public string $table,
        public string $roll,
        public ?string $roller,
        public DateTimeImmutable $rolledAt,
        public array $results,
    ) {
    }

    public static function of(TowerDrop $drop, RecordedRoll $roll): self
    {
        return new self($drop->position(), $drop->table()->toString(), $roll->id(), $drop->roller()?->toString(), $roll->rolledAt(), RollSummary::of($roll->result()));
    }

    /**
     * The totals of the instructions, null for one that gave a text or a list.
     *
     * @return list<?int>
     */
    public function totals(): array
    {
        return array_map(static fn (array $result): ?int => $result['total'], $this->results);
    }

    /**
     * What a subscriber receives, as JSON.
     *
     * @return array{position: int, table: string, roll: string, roller: ?string, rolled_at: string, results: list<array{expression: string, total: ?int, output: int|string|list<int|string>, sets: list<array{notation: string, die: ?string, dice: list<array{value: int, face: ?string, kept: bool}>}>}>}
     */
    public function toArray(): array
    {
        return [
            'position' => $this->position,
            'table' => $this->table,
            'roll' => $this->roll,
            'roller' => $this->roller,
            'rolled_at' => $this->rolledAt->format(DateTimeInterface::ATOM),
            'results' => $this->results,
        ];
    }
}
