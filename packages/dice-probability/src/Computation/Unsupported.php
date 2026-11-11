<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Computation;

use RuntimeException;

/**
 * The expression holds a construct the exact computation does not follow — unique dice, dice all the same that
 * explode, occurrences, groups, the dice before, a die whose faces it cannot list —: an estimate takes over.
 *
 * @internal
 */
final class Unsupported extends RuntimeException
{
}
