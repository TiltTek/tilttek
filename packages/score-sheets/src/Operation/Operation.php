<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets\Operation;

use Tilttek\ScoreSheets\Exception\InvalidEntry;
use Tilttek\ScoreSheets\Scoring\Step;

/**
 * How a computed category gets its value from the categories declared before it. An operation is data: `toArray()`
 * writes it, `Operations::fromArray()` reads it back.
 */
interface Operation
{
    /** The largest constant an operation holds, positive or negative. */
    public const int MAX_CONSTANT = 1_000_000;

    /**
     * The categories it reads.
     *
     * @return non-empty-list<string>
     */
    public function reads(): array;

    /**
     * The value of the category for every participant.
     *
     * @param array<int, array<string, int>> $table the values computed so far, by participant — their place on the
     *                                              sheet — then category
     *
     * @return array<int, Step> by participant, in the order of the table
     *
     * @throws InvalidEntry when a value is too large to compute
     */
    public function apply(string $category, array $table): array;

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array;
}
