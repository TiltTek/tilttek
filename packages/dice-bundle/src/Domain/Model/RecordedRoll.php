<?php

declare(strict_types=1);

namespace Tilttek\DiceBundle\Domain\Model;

use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Phil\Resource\ResourceReference;
use RoundingMode;
use Tilttek\Dice\Fairness\Seed;
use Tilttek\Dice\Replay\ReplayableRoll;

/**
 * A replayable roll in the journal: its identifier, its expression in the canonical form, its seed, how its divisions
 * rounded, its result as the library writes it, who rolled it and where — a table, a game —, each an opaque reference
 * the application gives, or none, and when.
 */
#[ORM\Entity]
#[ORM\Table(name: self::TABLE)]
#[ORM\Index(name: 'tilttek_dice_roll_owner', columns: ['owner'])]
#[ORM\Index(name: 'tilttek_dice_roll_context', columns: ['context', 'rolled_at'])]
final class RecordedRoll
{
    public const string TABLE = 'tilttek_dice_roll';

    /**
     * @param list<array<string, mixed>> $result
     */
    private function __construct(
        #[ORM\Id]
        #[ORM\Column(name: 'id', length: 32)]
        private readonly string $id,
        #[ORM\Column(name: 'expression', type: 'text')]
        private readonly string $expression,
        #[ORM\Column(name: 'seed', length: 64)]
        private readonly string $seed,
        #[ORM\Column(name: 'result', type: 'json')]
        private readonly array $result,
        #[ORM\Column(name: 'owner', length: 320, nullable: true)]
        private readonly ?string $owner,
        #[ORM\Column(name: 'rolled_at')]
        private readonly DateTimeImmutable $rolledAt,
        #[ORM\Column(name: 'rounding', length: 32, options: ['default' => 'NegativeInfinity'])]
        private readonly string $rounding = 'NegativeInfinity',
        #[ORM\Column(name: 'context', length: 320, nullable: true)]
        private readonly ?string $context = null,
    ) {
    }

    /**
     * @param ?ResourceReference $context where the roll was made — a table, a game —, or nowhere in particular
     */
    public static function record(ReplayableRoll $roll, ?ResourceReference $owner, DateTimeImmutable $at, ?ResourceReference $context = null): self
    {
        return new self($roll->id, $roll->expression(), $roll->seed->hex(), $roll->result->toArray(), $owner?->toString(), $at, $roll->result->rounding->name, $context?->toString());
    }

    public function id(): string
    {
        return $this->id;
    }

    public function expression(): string
    {
        return $this->expression;
    }

    public function seed(): Seed
    {
        return Seed::fromHex($this->seed);
    }

    /**
     * How the roll's divisions rounded, which its replay rounds the same way; down when the row names no mode PHP
     * knows.
     */
    public function rounding(): RoundingMode
    {
        foreach (RoundingMode::cases() as $mode) {
            if ($mode->name === $this->rounding) {
                return $mode;
            }
        }

        return RoundingMode::NegativeInfinity;
    }

    /**
     * The result as it was recorded, in the form of `PoolResult::toArray()`.
     *
     * @return list<array<string, mixed>>
     */
    public function result(): array
    {
        return $this->result;
    }

    public function owner(): ?ResourceReference
    {
        return null === $this->owner ? null : ResourceReference::fromString($this->owner);
    }

    /**
     * Where the roll was made — a table, a game —, or null.
     */
    public function context(): ?ResourceReference
    {
        return null === $this->context ? null : ResourceReference::fromString($this->context);
    }

    public function rolledAt(): DateTimeImmutable
    {
        return $this->rolledAt;
    }
}
