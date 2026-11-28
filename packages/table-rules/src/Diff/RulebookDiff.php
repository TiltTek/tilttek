<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Diff;

use Tilttek\TableRules\Rule;

/**
 * What changed between two versions of a table's rules: the rules added, removed, changed and moved, the variants
 * added and removed, and the game system before and after.
 */
final readonly class RulebookDiff
{
    /**
     * @param int              $from            the number of the earlier version compared
     * @param int              $to              the number of the later version compared
     * @param list<Rule>       $added           the rules only in the later version, in its order
     * @param list<Rule>       $removed         the rules only in the earlier version, in its order
     * @param list<RuleChange> $changed         the rules of both versions with another title, text or category, in the
     *                                          order of the later version
     * @param list<string>     $moved           the identifiers of the rules of both versions read in another order, in the
     *                                          order of the later version
     * @param list<string>     $variantsAdded   sorted
     * @param list<string>     $variantsRemoved sorted
     */
    public function __construct(
        public int $from,
        public int $to,
        public array $added = [],
        public array $removed = [],
        public array $changed = [],
        public array $moved = [],
        public array $variantsAdded = [],
        public array $variantsRemoved = [],
        public ?string $systemBefore = null,
        public ?string $systemAfter = null,
    ) {
    }

    /**
     * Whether the two versions hold the same rules in the same order, the same variants and the same system.
     */
    public function isEmpty(): bool
    {
        return [] === $this->added
            && [] === $this->removed
            && [] === $this->changed
            && [] === $this->moved
            && [] === $this->variantsAdded
            && [] === $this->variantsRemoved
            && $this->systemBefore === $this->systemAfter;
    }

    /**
     * @return array{from: int, to: int, added: list<array{id: string, title: string, text: string, category: ?string}>, removed: list<array{id: string, title: string, text: string, category: ?string}>, changed: list<array<string, mixed>>, moved: list<string>, variantsAdded: list<string>, variantsRemoved: list<string>, systemBefore: ?string, systemAfter: ?string}
     */
    public function toArray(): array
    {
        return [
            'from' => $this->from,
            'to' => $this->to,
            'added' => array_map(static fn (Rule $rule): array => $rule->toArray(), $this->added),
            'removed' => array_map(static fn (Rule $rule): array => $rule->toArray(), $this->removed),
            'changed' => array_map(static fn (RuleChange $change): array => $change->toArray(), $this->changed),
            'moved' => $this->moved,
            'variantsAdded' => $this->variantsAdded,
            'variantsRemoved' => $this->variantsRemoved,
            'systemBefore' => $this->systemBefore,
            'systemAfter' => $this->systemAfter,
        ];
    }
}
