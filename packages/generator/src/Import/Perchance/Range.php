<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import\Perchance;

/**
 * `{1-6}`: a whole number in a range, both ends included.
 *
 * @internal
 */
final readonly class Range
{
    public function __construct(public int $min, public int $max)
    {
    }
}
