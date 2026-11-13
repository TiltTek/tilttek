<?php

declare(strict_types=1);

namespace Tilttek\SecretRolls\Exception;

use InvalidArgumentException;

/**
 * A value the library refuses: a scene, a roller, a context or an expression it cannot take, or an opening, a journal
 * entry or a journal that does not read.
 */
final class InvalidSecretRoll extends InvalidArgumentException implements SecretRollsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
