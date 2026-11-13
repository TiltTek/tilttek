<?php

declare(strict_types=1);

namespace Tilttek\Stickers;

use Phil\Resource\ResourceReference;
use Tilttek\Sets\Identifiers;
use Tilttek\Stickers\Exception\InvalidSticker;

/**
 * The shapes the library accepts, every fault an {@see InvalidSticker}: codes as `tilttek/sets` writes them, holders
 * never referenced by an e-mail address, and texts of a bounded length without control characters.
 *
 * @internal
 */
final class Guard
{
    private function __construct()
    {
    }

    /**
     * @throws InvalidSticker when the code does not have the shape of {@see Identifiers::CODE}
     */
    public static function code(string $code, string $what): string
    {
        if (1 !== preg_match(Identifiers::CODE, $code)) {
            throw InvalidSticker::because(\sprintf('The code of a %s is 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter or a digit; "%s" is not.', $what, substr($code, 0, 64)));
        }

        return $code;
    }

    /**
     * A holder or a surface is referenced by a stable identifier the application gives, never by an e-mail address.
     *
     * @throws InvalidSticker when the identifier holds an `@`, plain or percent-encoded
     */
    public static function reference(ResourceReference $reference, string $what): ResourceReference
    {
        if (str_contains($reference->id, '@') || false !== stripos($reference->id, '%40')) {
            throw InvalidSticker::because(\sprintf('The %s "%s:…" has an identifier with "@": it is referenced by a stable identifier, never by an e-mail address.', $what, $reference->key));
        }

        return $reference;
    }

    /**
     * A text trimmed, of 1 to `$max` characters of valid UTF-8 without control characters.
     *
     * @throws InvalidSticker when the text is empty, too long, not UTF-8 or holds a control character
     */
    public static function text(string $text, string $what, int $max): string
    {
        $text = trim($text);

        if ('' === $text || !mb_check_encoding($text, 'UTF-8') || mb_strlen($text, 'UTF-8') > $max || 1 === preg_match('/[\x00-\x1F\x7F]/', $text)) {
            throw InvalidSticker::because(\sprintf('The %s is 1 to %d characters of UTF-8, without control characters.', $what, $max));
        }

        return $text;
    }
}
