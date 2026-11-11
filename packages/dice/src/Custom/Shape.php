<?php

declare(strict_types=1);

namespace Tilttek\Dice\Custom;

/**
 * The shape engraved on a face, which tells it apart without its colour, as a die read by touch would.
 */
enum Shape: string
{
    case Circle = 'circle';
    case Square = 'square';
    case Triangle = 'triangle';
    case Diamond = 'diamond';
    case Pentagon = 'pentagon';
    case Hexagon = 'hexagon';
    case Star = 'star';
    case Cross = 'cross';
    case Heart = 'heart';
    case Crescent = 'crescent';
    case Blank = 'blank';
}
