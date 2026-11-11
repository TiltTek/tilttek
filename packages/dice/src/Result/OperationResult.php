<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

use Override;
use Tilttek\Dice\Expression\Operator;

/**
 * Two results joined by an operator, and the value they give.
 */
final readonly class OperationResult implements NodeResult
{
    public function __construct(
        public Operator $operator,
        public NodeResult $left,
        public NodeResult $right,
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
        return [...$this->left->sets(), ...$this->right->sets()];
    }

    /**
     * @return array{type: 'operation', operator: string, value: int, left: array<string, mixed>, right: array<string, mixed>}
     */
    #[Override]
    public function toArray(): array
    {
        return ['type' => 'operation', 'operator' => $this->operator->value, 'value' => $this->value, 'left' => $this->left->toArray(), 'right' => $this->right->toArray()];
    }
}
