<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup\Exception;

use InvalidArgumentException;

/**
 * A rule, a rule's identifier or a set of rules the library refuses: a key, a version or a reference that does not hold
 * together, or two rules with one identifier.
 */
final class InvalidRule extends InvalidArgumentException implements RulesLookupException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
