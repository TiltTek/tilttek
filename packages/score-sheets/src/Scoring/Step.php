<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Scoring;

/**
 * How one category of one participant got its value: the operation, what it read — categories by their key, the
 * constants of the declaration by a name starting with `#` — and the value.
 */
final readonly class Step
{
    /**
     * @param string             $operation `entered`, `sum`, `product`, `majority` or `bonus`
     * @param array<string, int> $operands
     */
    public function __construct(
        public string $category,
        public string $operation,
        public array $operands,
        public int $value,
    ) {
    }

    /**
     * @return array{category: string, operation: string, operands: array<string, int>, value: int}
     */
    public function toArray(): array
    {
        return ['category' => $this->category, 'operation' => $this->operation, 'operands' => $this->operands, 'value' => $this->value];
    }
}
