<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * Roll one more die for each die that matches — on the highest face when no validator is given — and again for each
 * new die that matches: as a die of its own (`!`), or added to the die that exploded (`!!`). A penetrating explosion
 * (`!p`, `!!p`) takes one from each die it adds, and still explodes on the face as it fell. A limit (`!(3)`,
 * `!(1d4)`) bounds the explosions of each die.
 */
final readonly class Explosion
{
    public function __construct(
        public ?Validator $on = null,
        public bool $compound = false,
        public bool $penetrating = false,
        public ?Node $limit = null,
    ) {
        if ($limit instanceof Constant && $limit->value < 1) {
            throw InvalidDefinition::because(\sprintf('An explosion is limited to at least one per die; %d is not.', $limit->value));
        }
    }

    public function triggers(int $face, int $highest): bool
    {
        return $this->on?->matches($face) ?? $face === $highest;
    }

    /**
     * Whether every face from the lowest to the highest would explode.
     */
    public function always(int $lowest, int $highest): bool
    {
        return $this->on?->matchesAll($lowest, $highest) ?? $lowest === $highest;
    }
}
