<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

use Override;

/**
 * A result negated.
 */
final readonly class NegationResult implements NodeResult
{
    public function __construct(
        public NodeResult $operand,
        private int $value,
    ) {
    }

    #[Override]
    public function value(): int
    {
        return $this->value;
    }

    #[Override]
    public function output(): int
    {
        return $this->value;
    }

    #[Override]
    public function sets(): array
    {
        return $this->operand->sets();
    }

    /**
     * @return array{type: 'negation', value: int, operand: array<string, mixed>}
     */
    #[Override]
    public function toArray(): array
    {
        return ['type' => 'negation', 'value' => $this->value, 'operand' => $this->operand->toArray()];
    }
}
