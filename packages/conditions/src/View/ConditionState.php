<?php

declare(strict_types=1);

namespace Tilttek\Conditions\View;

use DateTimeImmutable;
use DateTimeInterface;
use Tilttek\Conditions\Duration;

/**
 * One condition on a character, to show the table: an application, or a condition brought by others — then without
 * instance, value, source or duration, and with the conditions that bring it.
 */
final readonly class ConditionState
{
    /**
     * @param list<string> $impliedBy the applied conditions that bring it, empty for an application
     */
    public function __construct(
        public string $condition,
        public ?string $instance,
        public ?int $value,
        public ?string $source,
        public ?Duration $remaining,
        public ?DateTimeImmutable $expiresAt,
        public array $impliedBy = [],
    ) {
    }

    /**
     * @return array{condition: string, instance: ?string, value: ?int, source: ?string, remaining: ?array{kind: string, amount: int, boundary: string, anchor: string}, expiresAt: ?string, impliedBy: list<string>}
     */
    public function toArray(): array
    {
        return [
            'condition' => $this->condition,
            'instance' => $this->instance,
            'value' => $this->value,
            'source' => $this->source,
            'remaining' => $this->remaining?->toArray(),
            'expiresAt' => $this->expiresAt?->format(DateTimeInterface::RFC3339_EXTENDED),
            'impliedBy' => $this->impliedBy,
        ];
    }
}
