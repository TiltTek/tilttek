<?php

declare(strict_types=1);

namespace Tilttek\Conditions\View;

use DateTimeImmutable;
use DateTimeInterface;

/**
 * A character's conditions at a moment, to show the table: the applications in the order applied, then the conditions
 * they bring, the next moment one ends in real time — when an application calls `Conditions::expire()` — and the
 * version read.
 */
final readonly class ConditionsView
{
    /**
     * @param list<ConditionState> $conditions
     */
    public function __construct(
        public string $character,
        public array $conditions,
        public ?DateTimeImmutable $nextExpiry,
        public int $version,
    ) {
    }

    /**
     * Whether the character has the condition, applied or brought by another.
     */
    public function has(string $condition): bool
    {
        return array_any($this->conditions, static fn (ConditionState $state): bool => $state->condition === $condition);
    }

    /**
     * @return array{character: string, conditions: list<array{condition: string, instance: ?string, value: ?int, source: ?string, remaining: ?array{kind: string, amount: int, boundary: string, anchor: string}, expiresAt: ?string, impliedBy: list<string>}>, nextExpiry: ?string, version: int}
     */
    public function toArray(): array
    {
        return [
            'character' => $this->character,
            'conditions' => array_map(static fn (ConditionState $state): array => $state->toArray(), $this->conditions),
            'nextExpiry' => $this->nextExpiry?->format(DateTimeInterface::RFC3339_EXTENDED),
            'version' => $this->version,
        ];
    }
}
