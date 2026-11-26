<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

/**
 * Who goes first among participants of the same total: the most — or the fewest — of one category.
 */
final readonly class Tiebreaker
{
    public function __construct(
        public string $category,
        public Direction $prefer = Direction::Most,
    ) {
        Name::key($category, 'A category key');
    }

    /**
     * @return array{category: string, prefer: string}
     */
    public function toArray(): array
    {
        return ['category' => $this->category, 'prefer' => $this->prefer->value];
    }
}
