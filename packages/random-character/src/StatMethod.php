<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter;

/**
 * How a system gives its characters their attribute values.
 */
enum StatMethod: string
{
    /** Each attribute rolls the roll in turn, in the order the rules list them: 3d6 in order. */
    case Each = 'each';

    /** The roll is made once per attribute, and the values are dealt to the attributes at random: 4d6, drop the lowest, arranged. */
    case RolledArray = 'rolled-array';

    /** A fixed array of values, dealt to the attributes at random: the standard array. */
    case Array = 'array';
}
