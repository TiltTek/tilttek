<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * Whether a reroll repeats until the die no longer matches, or happens once.
 */
enum Repetition: string
{
    case Repeated = 'repeated';
    case Once = 'once';
}
