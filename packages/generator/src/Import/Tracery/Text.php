<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import\Tracery;

/**
 * Plain text of a rule, its escapes resolved.
 *
 * @internal
 */
final readonly class Text
{
    public function __construct(public string $text)
    {
    }
}
