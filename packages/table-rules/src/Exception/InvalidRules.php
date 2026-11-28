<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Exception;

use InvalidArgumentException;

/**
 * A value of a rule, a version, a rulebook or a call is out of its shape or its bounds.
 */
final class InvalidRules extends InvalidArgumentException implements TableRulesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
