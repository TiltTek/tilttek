<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

use Override;
use Tilttek\Dice\Custom\Face;
use Tilttek\Dice\Expression\Dice;
use Tilttek\Dice\Expression\Group;
use Tilttek\Dice\Expression\InstructionDice;
use Tilttek\Dice\Expression\PreviousDice;
use Tilttek\Dice\Expression\ValueList;
use Tilttek\Dice\Notation\NotationWriter;

/**
 * Every die a set of dice rolled — rerolled, exploded, kept or dropped — and its value: the sum of the dice that count,
 * the successes less the failures when the expression counts them, or the number of sets of occurrences or of groups,
 * which it lists by their values. The parts are what was rolled before the dice: their count and sides when computed,
 * the limit of their explosions, the expressions of a group. Custom dice whose faces carry no value are not numeric;
 * their faces are counted by symbol instead, and their output is the labels of the faces that count. The notices say
 * what the roll could not do exactly: groups found greedily, beyond the dice searched for the most groups.
 */
final readonly class DiceResult implements NodeResult
{
    /**
     * @param list<RolledDie>           $dice
     * @param list<list<int>>           $groups
     * @param array<string, NodeResult> $parts   by name: `count`, `sides`, `explosion-limit`, or `expression-1` and
     *                                           the next ones of a group
     * @param list<Notice>              $notices
     */
    public function __construct(
        public Dice|ValueList|PreviousDice|InstructionDice|Group $expression,
        public array $dice,
        private int $value,
        public bool $numeric = true,
        public array $groups = [],
        public array $parts = [],
        public array $notices = [],
    ) {
    }

    #[Override]
    public function value(): int
    {
        return $this->value;
    }

    /**
     * The value when the dice are numeric; the labels of the faces that count otherwise.
     *
     * @return int|list<string>
     */
    #[Override]
    public function output(): int|array
    {
        return $this->numeric ? $this->value : array_map(static fn (RolledDie $die): string => $die->face instanceof Face ? $die->face->label : \sprintf('%d', $die->value), $this->kept());
    }

    /**
     * @return list<self>
     */
    #[Override]
    public function sets(): array
    {
        $sets = [];

        foreach ($this->parts as $part) {
            $sets = [...$sets, ...$part->sets()];
        }

        return [...$sets, $this];
    }

    /**
     * The dice that count: kept, and not replaced by a reroll.
     *
     * @return list<RolledDie>
     */
    public function kept(): array
    {
        return array_values(array_filter($this->dice, static fn (RolledDie $die): bool => $die->counts()));
    }

    /**
     * How many of the kept dice fell on each face of a custom die, by face identifier, in the order the faces first
     * fell: two stars and a circle. Empty for numbered dice.
     *
     * @return array<string, int>
     */
    public function tally(): array
    {
        $tally = [];

        foreach ($this->kept() as $die) {
            if (null !== $die->face) {
                $tally[$die->face->id] = ($tally[$die->face->id] ?? 0) + 1;
            }
        }

        return $tally;
    }

    /**
     * @return array{type: 'dice', notation: string, value: int, numeric: bool, dice: list<array<string, mixed>>, groups: list<list<int>>, parts: array<string, array<string, mixed>>, notices: list<string>}
     */
    #[Override]
    public function toArray(): array
    {
        return [
            'type' => 'dice',
            'notation' => new NotationWriter()->writeNode($this->expression),
            'value' => $this->value,
            'numeric' => $this->numeric,
            'dice' => array_map(static fn (RolledDie $die): array => $die->toArray(), $this->dice),
            'groups' => $this->groups,
            'parts' => array_map(static fn (NodeResult $part): array => $part->toArray(), $this->parts),
            'notices' => array_map(static fn (Notice $notice): string => $notice->value, $this->notices),
        ];
    }
}
