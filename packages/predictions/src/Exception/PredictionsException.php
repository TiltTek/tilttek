<?php

declare(strict_types=1);

namespace Tilttek\Predictions\Exception;

use Throwable;

/**
 * Implemented by every exception of the library, to catch them as one.
 */
interface PredictionsException extends Throwable
{
}
