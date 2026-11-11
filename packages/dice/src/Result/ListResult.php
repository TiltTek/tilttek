<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

use Override;
use Tilttek\Dice\Exception\CannotRoll;

/**
 * A list of numbers and texts: the items a text list drew — by their index in the list, and the number rolled for
 * each —, the results of `repeat()`, or the branches of a switch that held. Joined, its output is one text, the items
 * written together without a separator.
 */
final readonly class ListResult implements NodeResult
{
    /**
     * @param list<int|string> $items
     * @param list<NodeResult> $parts the results the items come from
     * @param list<int>        $draws the index, in the text list, of each item drawn
     * @param list<int>        $rolls the number rolled for each item drawn: from 1 to the total weight, or on the
     *                                ranges
     * @param ?NodeResult      $count the count, when it was rolled
     */
    public function __construct(
        public array $items,
        public bool $joined = false,
        public array $parts = [],
        public array $draws = [],
        public array $rolls = [],
        public ?NodeResult $count = null,
    ) {
    }

    #[Override]
    public function value(): int
    {
        throw CannotRoll::notANumber('a list');
    }

    /**
     * @return string|list<int|string>
     */
    #[Override]
    public function output(): string|array
    {
        return $this->joined ? implode('', $this->items) : $this->items;
    }

    #[Override]
    public function sets(): array
    {
        $sets = $this->count?->sets() ?? [];

        foreach ($this->parts as $part) {
            $sets = [...$sets, ...$part->sets()];
        }

        return $sets;
    }

    /**
     * @return array{type: 'list', items: list<int|string>, joined: bool, draws: list<int>, rolls: list<int>, count: ?array<string, mixed>, parts: list<array<string, mixed>>}
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'type' => 'list',
            'items' => $this->items,
            'joined' => $this->joined,
            'draws' => $this->draws,
            'rolls' => $this->rolls,
            'count' => $this->count?->toArray(),
            'parts' => array_map(static fn (NodeResult $part): array => $part->toArray(), $this->parts),
        ];
    }
}
