<?php

declare(strict_types=1);

namespace Tilttek\Collections\Exception;

use DomainException;

/**
 * What was asked cannot be: a key that already gave something else, a copy that no longer belongs to the member who
 * gives it, a copy that is not a die.
 */
final class CollectionRefused extends DomainException implements CollectionsException
{
    public static function because(string $reason): self
    {
        return new self($reason);
    }
}
