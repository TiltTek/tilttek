<?php

declare(strict_types=1);

namespace Tilttek\GameNight\Exception;

use RuntimeException;

/**
 * The night changed, or was added, since it was read: the change is not kept, and is made again on what is read anew.
 */
final class NightConflict extends RuntimeException implements GameNightException
{
    public static function changed(string $id): self
    {
        return new self(\sprintf('The game night %s changed since it was read.', $id));
    }
}
