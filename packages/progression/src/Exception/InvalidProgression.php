<?php

declare(strict_types=1);

namespace Tilttek\Progression\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a name, a curve, a rule, a cap, a season, a milestone, a grace or a timezone that does
 * not hold together.
 */
final class InvalidProgression extends InvalidArgumentException implements ProgressionException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }

    /**
     * The refusal of a name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.
     */
    public static function name(string $what, string $name): self
    {
        return new self(\sprintf('"%s" is not a %s name: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.', $name, $what));
    }
}
