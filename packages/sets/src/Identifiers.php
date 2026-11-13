<?php

declare(strict_types=1);

namespace Tilttek\Sets;

use Phil\Resource\ResourceReference;
use Tilttek\Sets\Exception\InvalidSet;

/**
 * The shapes the library accepts: the code of a set, a tier, a reward kind or a reason, the stable identifier of a
 * member of a set, and the reference of a holder — never an e-mail address.
 */
final class Identifiers
{
    /**
     * A code: 1 to 64 lower-case letters, digits, `_`, `.` or `-`, starting with a letter or a digit.
     */
    public const string CODE = '/^[a-z0-9][a-z0-9_.-]{0,63}$/D';

    /**
     * The stable identifier of a member of a set: 1 to 255 ASCII letters, digits, `_`, `.`, `:`, `/` or `-`, starting
     * with a letter or a digit.
     */
    public const string MEMBER = '/^[A-Za-z0-9][A-Za-z0-9_.:\/-]{0,254}$/D';

    private function __construct()
    {
    }

    /**
     * @throws InvalidSet when the code does not have the shape of {@see self::CODE}
     */
    public static function code(string $code, string $what): string
    {
        if (1 !== preg_match(self::CODE, $code)) {
            throw InvalidSet::because(\sprintf('The code of a %s is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "%s" is not.', $what, self::excerpt($code)));
        }

        return $code;
    }

    /**
     * @throws InvalidSet when the identifier does not have the shape of {@see self::MEMBER}
     */
    public static function member(string $member): string
    {
        if (1 !== preg_match(self::MEMBER, $member)) {
            throw InvalidSet::because(\sprintf('A member of a set is 1 to 255 letters, digits, "_", ".", ":", "/" or "-", starting with a letter or a digit; "%s" is not.', self::excerpt($member)));
        }

        return $member;
    }

    /**
     * A holder is referenced by a stable identifier the application gives: an identifier with `@` is refused, so that
     * an e-mail address never becomes the key of what a holder owns or completed.
     *
     * @throws InvalidSet when the identifier holds an `@`, plain or percent-encoded
     */
    public static function holder(ResourceReference $holder): ResourceReference
    {
        if (str_contains($holder->id, '@') || false !== stripos($holder->id, '%40')) {
            throw InvalidSet::because(\sprintf('The holder "%s:…" has an identifier with "@": a holder is referenced by a stable identifier, never by an e-mail address.', $holder->key));
        }

        return $holder;
    }

    /**
     * The start of a refused value, short enough for a message.
     */
    private static function excerpt(string $value): string
    {
        return substr($value, 0, 64);
    }
}
