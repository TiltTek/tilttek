<?php

declare(strict_types=1);

namespace Tilttek\Generator\Exception;

use RuntimeException;

/**
 * A draw stopped by one of the limits: depth of calls, number of picks, time per result, size of a request.
 */
final class LimitExceeded extends RuntimeException implements GeneratorException
{
    public static function of(string $limit, int $value): self
    {
        return new self(\sprintf('The limit of %d %s is exceeded.', $value, $limit));
    }
}
