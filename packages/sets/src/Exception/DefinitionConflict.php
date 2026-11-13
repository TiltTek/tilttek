<?php

declare(strict_types=1);

namespace Tilttek\Sets\Exception;

use RuntimeException;

/**
 * A definition that would rewrite a set behind its holders' backs: a version already defined with other content, or
 * a version older than the latest one.
 */
final class DefinitionConflict extends RuntimeException implements SetsException
{
    public static function rewritten(string $code, int $version): self
    {
        return new self(\sprintf('The set "%s" already has a version %d with other content: a changed set takes a new version.', $code, $version));
    }

    public static function older(string $code, int $version, int $latest): self
    {
        return new self(\sprintf('The set "%s" is at version %d; version %d is older.', $code, $latest, $version));
    }
}
