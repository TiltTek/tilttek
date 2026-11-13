<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Contract;

use Tilttek\WargameArmy\Constraint\Selector;

/**
 * A constraint a catalogue declares in its data, written back to the array it was read from.
 */
interface DeclaredConstraint extends Constraint
{
    /**
     * The units the constraint counts.
     */
    public Selector $selector { get; }

    /**
     * @return array<string, int|string|null>
     */
    public function toArray(): array;
}
