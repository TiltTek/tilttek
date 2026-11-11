<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability;

/**
 * Why a result is estimated rather than exact: the computation would have gone past its work limit, or the expression
 * holds a construct the exact computation does not follow.
 */
enum EstimateReason: string
{
    case TooLarge = 'too-large';
    case Unsupported = 'unsupported';
}
