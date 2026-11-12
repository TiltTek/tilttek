<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Application\Exception;

use RuntimeException;

/**
 * A draw the API refuses: a token that is missing or unknown, or a generator that is not open to the caller. Whether a
 * token was missing tells the controller to answer 401 rather than 403.
 */
final class AccessDenied extends RuntimeException
{
    private function __construct(string $message, public readonly bool $tokenMissing)
    {
        parent::__construct($message);
    }

    public static function tokenRequired(string $generator): self
    {
        return new self(\sprintf('The generator "%s" is drawn with a token only.', $generator), true);
    }

    public static function unknownToken(): self
    {
        return new self('The token is not known.', true);
    }

    public static function notOpen(string $generator): self
    {
        return new self(\sprintf('The generator "%s" is not open to this client.', $generator), false);
    }
}
