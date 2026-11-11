<?php

declare(strict_types=1);

namespace Tilttek\Dice\Die;

use Tilttek\Dice\Custom\DieDefinition;
use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * A die the application declares, named between braces in an expression: `3d{chromatic}`. The expression only names
 * it; its faces are found when it is rolled.
 */
final readonly class CustomDie implements DieType
{
    public function __construct(
        public string $name,
    ) {
        if (1 !== preg_match(DieDefinition::NAME_PATTERN, $name)) {
            throw InvalidDefinition::because(\sprintf('A die name is 1 to 32 lower-case letters, digits, "_" or "-", starting with a letter; "%s" is not.', $name));
        }
    }
}
