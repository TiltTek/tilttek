<?php

declare(strict_types=1);

namespace Tilttek\DiceProbability\Computation;

use RuntimeException;

/**
 * The exact computation would go past its budget: an estimate takes over.
 *
 * @internal
 */
final class TooLarge extends RuntimeException
{
}
