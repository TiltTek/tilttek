<?php

declare(strict_types=1);

namespace Tilttek\PlayLog\Exception;

use RuntimeException;

/**
 * The play changed since it was read, or a new play has the identifier of a kept one: the change is not kept, and is
 * made again on what is read anew.
 */
final class PlayConflict extends RuntimeException implements PlayLogException
{
    public static function changed(string $id): self
    {
        return new self(\sprintf('The play %s changed since it was read.', $id));
    }
}
