<?php

declare(strict_types=1);

namespace Tilttek\Dice\Expression;

use Tilttek\Dice\Exception\InvalidDefinition;

/**
 * A token of the page's look given to the next dice, or to every die left when no count is given:
 * `look[2:accent,muted]`. The token names a style the page defines; it is never a colour.
 */
final readonly class Look
{
    public const string TOKEN_PATTERN = '/^[a-z][a-z0-9-]{0,31}$/D';

    public function __construct(
        public string $token,
        public ?int $count = null,
    ) {
        if (1 !== preg_match(self::TOKEN_PATTERN, $token)) {
            throw InvalidDefinition::because(\sprintf('A look token is 1 to 32 lower-case letters, digits or "-", starting with a letter; "%s" is not.', $token));
        }

        if (null !== $count && $count < 1) {
            throw InvalidDefinition::because(\sprintf('A look token is given to at least one die; %d is not.', $count));
        }
    }
}
