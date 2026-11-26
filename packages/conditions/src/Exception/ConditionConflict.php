<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Exception;

use RuntimeException;

/**
 * The character's conditions changed since they were read: the change is not kept, and is made again on what is read anew.
 */
final class ConditionConflict extends RuntimeException implements ConditionsException
{
    public static function changed(string $character): self
    {
        return new self(\sprintf('The conditions of %s changed since they were read.', $character));
    }
}
