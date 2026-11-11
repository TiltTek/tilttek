<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

use RoundingMode;
use Tilttek\Dice\Expression\Pool;

/**
 * A pool rolled: each instruction with its own result, and how its divisions rounded.
 */
final readonly class PoolResult
{
    /**
     * @param list<ExpressionResult> $results
     */
    public function __construct(
        public Pool $pool,
        public array $results,
        public RoundingMode $rounding = RoundingMode::NegativeInfinity,
    ) {
    }

    /**
     * @return list<?int>
     */
    public function totals(): array
    {
        return array_map(static fn (ExpressionResult $result): ?int => $result->total(), $this->results);
    }

    /**
     * What each instruction gave: a number, a text or a list.
     *
     * @return list<int|string|list<int|string>>
     */
    public function outputs(): array
    {
        return array_map(static fn (ExpressionResult $result): int|string|array => $result->output(), $this->results);
    }

    /**
     * The whole roll as plain arrays — what a journal keeps and what a verification compares.
     *
     * @return list<array{expression: string, total: ?int, output: int|string|list<int|string>, merged_into: ?int, detail: array<string, mixed>}>
     */
    public function toArray(): array
    {
        return array_map(static fn (ExpressionResult $result): array => $result->toArray(), $this->results);
    }

    public function equals(self $other): bool
    {
        return $this->toArray() === $other->toArray();
    }
}
