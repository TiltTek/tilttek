<?php

declare(strict_types=1);

namespace Tilttek\AccessibleDice\Application;

/**
 * How a screen reader announces a result: when it has finished what it is saying, or at once.
 */
enum Politeness: string
{
    case Polite = 'polite';
    case Assertive = 'assertive';
}
