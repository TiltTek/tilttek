<?php

declare(strict_types=1);

namespace Tilttek\TableRules\Exception;

use DomainException;

/**
 * The change is well formed but not allowed on the rulebook in its state: a version identical to the current one, a
 * decision on a version that is no longer the current one, too many versions or players.
 */
final class RulesRefused extends DomainException implements TableRulesException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
