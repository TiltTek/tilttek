<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Exception;

use DomainException;

/**
 * A choice the library does not allow: a skin the catalog does not offer.
 */
final class SkinRefused extends DomainException implements DiceSkinsException
{
    public static function unknownSkin(string $skin): self
    {
        return new self(\sprintf('The catalog offers no skin "%s".', $skin));
    }
}
