<?php

declare(strict_types=1);

namespace Tilttek\SessionPrep\Exception;

use RuntimeException;

/**
 * The preparation changed since it was read, or a new one has the identifier of a kept one: the change is not kept,
 * and is made again on what is read anew.
 */
final class PreparationConflict extends RuntimeException implements SessionPrepException
{
    public static function changed(string $id): self
    {
        return new self(\sprintf('The preparation %s changed since it was read.', $id));
    }
}
