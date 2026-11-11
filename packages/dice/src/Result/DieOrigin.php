<?php

declare(strict_types=1);

namespace Tilttek\Dice\Result;

/**
 * Why a die was rolled: as one of the dice the expression asks for, to replace a die rerolled, or because a die
 * exploded.
 */
enum DieOrigin: string
{
    case Initial = 'initial';
    case Reroll = 'reroll';
    case Explosion = 'explosion';
}
