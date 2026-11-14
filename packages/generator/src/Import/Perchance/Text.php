<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import\Perchance;

/**
 * Plain text of an entry; a character kept as written by a backslash stays a text of its own, never read as a weight
 * or a roll.
 *
 * @internal
 */
final readonly class Text
{
    public function __construct(public string $text, public bool $kept = false)
    {
    }
}
