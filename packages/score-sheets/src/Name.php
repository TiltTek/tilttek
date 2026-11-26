<?php

declare(strict_types=1);

namespace Tilttek\ScoreSheets;

use Tilttek\ScoreSheets\Exception\InvalidEntry;
use Tilttek\ScoreSheets\Exception\InvalidSheet;

/**
 * The patterns of the keys and identifiers of the library: 1 to 64 ASCII letters, digits, dots, underscores and
 * hyphens, starting with a letter for the keys of a declaration — games, categories —, with a letter or a digit for
 * the identifiers of a match — participants, teams, matches. They are opaque, never a person's data.
 *
 * @internal
 */
final class Name
{
    public const string KEY_PATTERN = '/^[A-Za-z][A-Za-z0-9._-]{0,63}$/D';

    public const string ID_PATTERN = '/^[A-Za-z0-9][A-Za-z0-9._-]{0,63}$/D';

    public static function key(string $key, string $what): string
    {
        return 1 === preg_match(self::KEY_PATTERN, $key) ? $key : throw InvalidSheet::because(\sprintf('%s is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter.', $what));
    }

    public static function id(string $id, string $what): string
    {
        return 1 === preg_match(self::ID_PATTERN, $id) ? $id : throw InvalidEntry::because(\sprintf('%s is 1 to 64 ASCII letters, digits, dots, underscores and hyphens, starting with a letter or a digit.', $what));
    }
}
