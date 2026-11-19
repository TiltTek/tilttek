<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use LogicException;
use Phil\Resource\ResourceReference;

/**
 * A roll dropped in a table's tower: its place in the order of the tower, the table, the identifier of the roll in the
 * journal of the dice, who rolled it — an opaque reference the application gives, or nobody — and when it was dropped.
 * The roll itself, its expression and its result, stays in the journal: the tower keeps only where it falls.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'tilttek_dice_tower_table', columns: ['tower_table', 'position'])]
#[ORM\Index(name: 'tilttek_dice_tower_roller', columns: ['roller'])]
final class TowerDrop
{
    public const string TABLE = 'tilttek_dice_tower_drop';

    private function __construct(
        #[ORM\Column(name: 'tower_table', length: 320)]
        private readonly string $table,
        #[ORM\Column(name: 'roll', length: 32)]
        private readonly string $roll,
        #[ORM\Column(name: 'roller', length: 320, nullable: true)]
        private readonly ?string $roller,
        #[ORM\Column(name: 'dropped_at')]
        private readonly DateTimeImmutable $droppedAt,
        #[ORM\Id]
        #[ORM\GeneratedValue(strategy: 'IDENTITY')]
        #[ORM\Column(name: 'position')]
        private ?int $position = null,
    ) {
    }

    /**
     * @param ?int $position the place in the tower, or null when the store gives it as it keeps the drop
     */
    public static function drop(ResourceReference $table, string $roll, ?ResourceReference $roller, DateTimeImmutable $at, ?int $position = null): self
    {
        return new self($table->toString(), $roll, $roller?->toString(), $at, $position);
    }

    /**
     * The place of the drop in the order of every tower: a later drop has a greater position, with gaps between the
     * drops of one table.
     *
     * @throws LogicException before the store has kept the drop
     */
    public function position(): int
    {
        return $this->position ?? throw new LogicException('The drop has no position until the tower log keeps it.');
    }

    public function table(): ResourceReference
    {
        return ResourceReference::fromString($this->table);
    }

    public function roll(): string
    {
        return $this->roll;
    }

    public function roller(): ?ResourceReference
    {
        return null === $this->roller ? null : ResourceReference::fromString($this->roller);
    }

    public function droppedAt(): DateTimeImmutable
    {
        return $this->droppedAt;
    }
}
