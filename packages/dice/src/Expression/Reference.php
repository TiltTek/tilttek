<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * The result of an instruction before, by its number from 1: `$1` is its total, its text or its list; `$1[0]` its
 * first item — of a list, or of the dice that count, by their value.
 */
final readonly class Reference implements Node
{
    public function __construct(
        public int $instruction,
        public ?int $index = null,
    ) {
        if ($instruction < 1) {
            throw InvalidDefinition::because(\sprintf('Instructions are numbered from 1; %d is not one.', $instruction));
        }

        if (null !== $index && $index < 0) {
            throw InvalidDefinition::because(\sprintf('Items are numbered from 0; %d is not one.', $index));
        }
    }
}
