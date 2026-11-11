<?php

declare(strict_types=1);

namespace Tilttek\Quests\Exception;

use OutOfBoundsException;

/**
 * A quest is asked for by a code no quest of the catalog has.
 */
final class UnknownQuest extends OutOfBoundsException implements QuestsException
{
    public static function named(string $code): self
    {
        return new self(\sprintf('No quest is named "%s".', $code));
    }
}
