<?php

declare(strict_types=1);

namespace Tilttek\Generator\Exception;

use RuntimeException;
use Throwable;

/**
 * A template that failed while it rendered — an undefined variable or field, a dice expression that does not roll, an
 * access the sandbox refuses — with the template's name.
 */
final class GenerationFailed extends RuntimeException implements GeneratorException
{
    public static function in(string $template, Throwable $previous): self
    {
        return new self(\sprintf('The template "%s" failed: %s', $template, $previous->getMessage()), 0, $previous);
    }
}
