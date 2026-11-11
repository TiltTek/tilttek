<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

/**
 * How a value is compared to a threshold, in the plain sense of each sign: `>5` is strictly greater than 5, `!=5` is
 * anything but 5.
 */
enum Comparator: string
{
    case Equal = '=';
    case NotEqual = '!=';
    case Greater = '>';
    case GreaterOrEqual = '>=';
    case Less = '<';
    case LessOrEqual = '<=';
}
