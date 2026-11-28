<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Internal;

use Tilttek\ReferenceTables\Exception\InvalidTable;

/**
 * The checks the values of a table share: keys and short texts.
 *
 * @internal
 */
final class Check
{
    /** The longest short text: a category, a page, a system key. */
    public const int MAX_LABEL = 200;

    private const string KEY = '/^[a-z0-9](?:[a-z0-9_-]|\.(?=[a-z0-9])){0,95}$/D';

    /**
     * A key: lowercase ASCII letters, digits, `_`, `-` and single dots between them, 96 characters at most.
     *
     * @throws InvalidTable
     */
    public static function key(string $key, string $what): string
    {
        if (1 !== preg_match(self::KEY, $key)) {
            throw InvalidTable::because(\sprintf('The key of %s "%s" must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them.', $what, $key));
        }

        return $key;
    }

    /**
     * A label of one line: UTF-8, no control character, 1 to `MAX_LABEL` characters.
     *
     * @throws InvalidTable
     */
    public static function label(string $text, string $what): string
    {
        if ('' === trim($text) || !mb_check_encoding($text, 'UTF-8') || 1 === preg_match('/[\x00-\x1F\x7F]/', $text) || mb_strlen($text) > self::MAX_LABEL) {
            throw InvalidTable::because(\sprintf('%s must be one line of 1 to %d characters.', $what, self::MAX_LABEL));
        }

        return $text;
    }
}
