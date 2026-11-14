<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import\Perchance;

/**
 * `2d6+1`: a roll of dice.
 *
 * @internal
 */
final readonly class Dice
{
    public function __construct(public string $expression)
    {
    }
}
