<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Effect;

use Tilttek\Conditions\Modifier;

/**
 * A modifier in force on a character: the condition it comes from, the instance — null for a condition brought by
 * another — and its amount for the condition's value.
 */
final readonly class AppliedModifier
{
    public function __construct(
        public Modifier $modifier,
        public string $condition,
        public ?string $instance,
        public int $amount,
    ) {
    }
}
