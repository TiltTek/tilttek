<?php

declare(strict_types=1);

namespace Tilttek\MathTrade\Internal;

use Tilttek\MathTrade\Exception\InvalidTradeList;

/**
 * The rule every identifier of a trade list follows — participant, offer, game —: 1 to 100 bytes of UTF-8 without a
 * control character.
 *
 * @internal
 */
final class Identifier
{
    public const int MAX_BYTES = 100;

    /**
     * Whether the text is 1 to $maxBytes bytes of UTF-8 without a control character.
     */
    public static function isValid(string $value, int $maxBytes = self::MAX_BYTES): bool
    {
        return \strlen($value) <= $maxBytes && 1 === preg_match('/\A\P{Cc}+\z/u', $value);
    }

    /**
     * @throws InvalidTradeList when the identifier breaks the rule
     */
    public static function check(string $value, string $what): void
    {
        if (!self::isValid($value)) {
            throw InvalidTradeList::because(\sprintf('The identifier of %s is 1 to %d bytes of UTF-8 without a control character.', $what, self::MAX_BYTES));
        }
    }
}
