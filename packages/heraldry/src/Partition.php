<?php

declare(strict_types=1);

namespace Tilttek\Heraldry;

/**
 * How a field is divided between two tinctures. The first tincture takes the dexter chief — the top left as one looks
 * at the shield — and, quarterly, the first and fourth quarters; per saltire, the chief and the base.
 */
enum Partition: string
{
    case PerPale = 'per_pale';
    case PerFess = 'per_fess';
    case PerBend = 'per_bend';
    case PerBendSinister = 'per_bend_sinister';
    case Quarterly = 'quarterly';
    case PerSaltire = 'per_saltire';
}
