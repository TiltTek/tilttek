<?php

declare(strict_types=1);

namespace Tilttek\RandomCharacter\Exception;

use InvalidArgumentException;

/**
 * Creation rules that cannot be built, or that do not fit the system and the generators they name: the message says
 * what is wrong, every problem on a line of its own.
 */
final class InvalidRules extends InvalidArgumentException implements RandomCharacterException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    /**
     * @param non-empty-list<string> $problems
     */
    public static function problems(string $rules, array $problems): self
    {
        return new self(\sprintf("The creation rules \"%s\" cannot be rolled:\n- %s", $rules, implode("\n- ", $problems)));
    }
}
