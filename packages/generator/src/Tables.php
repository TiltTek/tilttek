<?php

declare(strict_types=1);

namespace Tilttek\Generator;

use Tilttek\Generator\Exception\InvalidDefinition;

/**
 * The random tables the library ships, ready to draw or to copy and adapt: encounters, magic items, loot with its
 * unidentified items, shops with their merchant and stock, adventure hooks, rumours of a place, a solo play oracle and
 * fortunes. Each table is one YAML file of `tables/<locale>/`, whose lists and generators are named `table.<name>…` and
 * name nothing outside the file; the English and French versions of a table share their names, their state and their
 * fields, so that an application loads one language or the other.
 */
final class Tables
{
    /**
     * The tables, by the name of their file.
     */
    public const array NAMES = ['encounter', 'fortune', 'hook', 'loot', 'magic-item', 'oracle', 'rumour', 'shop'];

    /**
     * The languages the tables are written in.
     */
    public const array LOCALES = ['en', 'fr'];

    private function __construct()
    {
    }

    /**
     * The directory of the tables in one language, for a `FileLoader` or a list of paths.
     *
     * @throws InvalidDefinition when the tables are not written in this language
     */
    public static function directory(string $locale = 'en'): string
    {
        if (!\in_array($locale, self::LOCALES, true)) {
            throw InvalidDefinition::at('tables', \sprintf('the tables are not written in "%s"; they are in "%s"', $locale, implode('", "', self::LOCALES)));
        }

        return \dirname(__DIR__).'/tables/'.$locale;
    }

    /**
     * The file of one table in one language.
     *
     * @throws InvalidDefinition when no table has this name or the tables are not written in this language
     */
    public static function file(string $name, string $locale = 'en'): string
    {
        if (!\in_array($name, self::NAMES, true)) {
            throw InvalidDefinition::at('tables', \sprintf('no table is named "%s"; the tables are "%s"', $name, implode('", "', self::NAMES)));
        }

        return self::directory($locale).'/'.$name.'.yaml';
    }
}
