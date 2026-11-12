<?php

declare(strict_types=1);

namespace Tilttek\Generator\Exception;

use OutOfBoundsException;

/**
 * A filter on a tag that no entry of the list carries: a typing error rather than an empty draw.
 */
final class UnknownTag extends OutOfBoundsException implements GeneratorException
{
    public static function in(string $list, string $tag): self
    {
        return new self(\sprintf('No entry of the list "%s" has the tag "%s".', $list, $tag));
    }
}
