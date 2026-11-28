<?php

declare(strict_types=1);

namespace Tilttek\ReferenceTables\Internal;

/**
 * A text folded for a search: lowercase, and the accented Latin letters written without their accent, so that
 * « Épée » finds « epee ».
 *
 * @internal
 */
final class Fold
{
    private const array LETTERS = [
        'à' => 'a', 'á' => 'a', 'â' => 'a', 'ã' => 'a', 'ä' => 'a', 'å' => 'a', 'æ' => 'ae',
        'ç' => 'c', 'è' => 'e', 'é' => 'e', 'ê' => 'e', 'ë' => 'e',
        'ì' => 'i', 'í' => 'i', 'î' => 'i', 'ï' => 'i', 'ñ' => 'n',
        'ò' => 'o', 'ó' => 'o', 'ô' => 'o', 'õ' => 'o', 'ö' => 'o', 'ø' => 'o', 'œ' => 'oe',
        'ù' => 'u', 'ú' => 'u', 'û' => 'u', 'ü' => 'u', 'ý' => 'y', 'ÿ' => 'y', 'ß' => 'ss',
    ];

    public static function text(string $text): string
    {
        return strtr(mb_strtolower($text, 'UTF-8'), self::LETTERS);
    }
}
