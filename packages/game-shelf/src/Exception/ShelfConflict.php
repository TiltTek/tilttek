<?php

declare(strict_types=1);

namespace Tilttek\GameShelf\Exception;

use RuntimeException;

/**
 * The shelf changed since it was read: the change is not kept, and is made again on what is read anew.
 */
final class ShelfConflict extends RuntimeException implements ShelfException
{
    public static function changed(string $member): self
    {
        return new self(\sprintf('The shelf of the member %s changed since it was read.', $member));
    }
}
