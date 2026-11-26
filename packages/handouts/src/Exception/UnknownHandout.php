<?php

declare(strict_types=1);

namespace Tilttek\Handouts\Exception;

use OutOfBoundsException;

/**
 * No handout holds this reference.
 */
final class UnknownHandout extends OutOfBoundsException implements HandoutException
{
    public static function named(string $reference): self
    {
        return new self(\sprintf('No handout is known as "%s".', $reference));
    }
}
