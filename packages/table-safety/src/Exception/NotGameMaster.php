<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Exception;

use DomainException;
use Tilttek\TableSafety\Signal\SignalKind;

/**
 * A signal only the game master of the table raises, such as asking for a check-in, raised by someone else.
 */
final class NotGameMaster extends DomainException implements TableSafetyException
{
    public static function for(SignalKind $kind): self
    {
        return new self(\sprintf('Only the game master raises the signal "%s".', $kind->value));
    }
}
