<?php

declare(strict_types=1);

namespace Tilttek\Referrals\Exception;

use Throwable;

/**
 * Implemented by every exception of the library, to catch them as one.
 */
interface ReferralsException extends Throwable
{
}
