<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The dice that count in instructions before, by their numbers from 1, judged again as one set without being rolled
 * again: `@1` takes those of the first instruction; `b[1,2]` binds those of two, which keep their own results;
 * `m[1,2]` merges them, and the instructions merged give their dice away — their own result is no longer a total. The
 * set takes the modifiers that apply to dice once rolled.
 */
final readonly class InstructionDice implements Node
{
    /**
     * @var non-empty-list<int>
     */
    public array $instructions;

    /**
     * @param list<int> $instructions
     */
    public function __construct(
        array $instructions,
        public Modifiers $modifiers = new Modifiers(),
        public bool $merge = false,
    ) {
        if ([] === $instructions) {
            throw InvalidDefinition::because('Dice are taken from one instruction or more.');
        }

        foreach ($instructions as $instruction) {
            if ($instruction < 1) {
                throw InvalidDefinition::because(\sprintf('Instructions are numbered from 1; %d is not one.', $instruction));
            }
        }

        if (\count(array_unique($instructions)) !== \count($instructions)) {
            throw InvalidDefinition::because('An instruction gives its dice once.');
        }

        if ($modifiers->rolls()) {
            throw InvalidDefinition::because('The dice of instructions before are not rolled again: they take no reroll and no explosion.');
        }

        $this->instructions = $instructions;
    }
}
