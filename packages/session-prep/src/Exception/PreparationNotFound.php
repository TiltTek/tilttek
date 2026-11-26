<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Exception;

use RuntimeException;

/**
 * No preparation has the identifier.
 */
final class PreparationNotFound extends RuntimeException implements SessionPrepException
{
    public static function withId(string $id): self
    {
        return new self(\sprintf('No preparation has the identifier %s.', $id));
    }
}
