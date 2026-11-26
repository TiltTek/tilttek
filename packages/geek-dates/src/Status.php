<?php

declare(strict_types=1);

namespace Tilttek\GeekDates;

/**
 * Whether an entry's rule was checked against its source, or still waits for it.
 */
enum Status: string
{
    case Verified = 'verified';
    case ToVerify = 'to-verify';
}
