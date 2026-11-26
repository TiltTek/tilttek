<?php

declare(strict_types=1);

namespace Tilttek\Conditions;

use Tilttek\Conditions\Exception\InvalidCondition;

/**
 * How the value of a condition falls on its own: by an amount at each start or end of the affected character's turns.
 * The condition ends when its value reaches zero.
 */
final readonly class Decay
{
    /**
     * @throws InvalidCondition when the amount is out of 1 to the highest value of a condition
     */
    public function __construct(public int $amount = 1, public Boundary $boundary = Boundary::End)
    {
        if ($amount < 1 || $amount > ConditionDefinition::MAX_VALUE) {
            throw InvalidCondition::because(\sprintf('A decay takes 1 to %d from the value.', ConditionDefinition::MAX_VALUE));
        }
    }
}
