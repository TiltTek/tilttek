<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Exception;

use RuntimeException;

/**
 * The catalogue has no condition of the identifier, or the character has no instance of it.
 */
final class ConditionNotFound extends RuntimeException implements ConditionsException
{
    public static function definition(string $id): self
    {
        return new self(\sprintf('The catalogue has no condition %s.', $id));
    }

    public static function instance(string $character, string $id): self
    {
        return new self(\sprintf('The character %s has no condition instance %s.', $character, $id));
    }
}
