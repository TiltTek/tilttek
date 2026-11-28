<?php

declare(strict_types=1);

namespace Tilttek\RulesLookup\Exception;

use InvalidArgumentException;

/**
 * A search the library refuses: a text too long or with too many words, a locale, a filter or a page out of bounds.
 */
final class InvalidQuery extends InvalidArgumentException implements RulesLookupException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
