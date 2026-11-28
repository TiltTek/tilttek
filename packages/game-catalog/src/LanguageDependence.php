<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

/**
 * How much of the game's text a player must read while playing, from none to a game that cannot be played by someone
 * who does not read its language.
 */
enum LanguageDependence: string
{
    /** No text to read while playing. */
    case None = 'none';
    /** Some text, learned once. */
    case Some = 'some';
    /** Text read regularly, a reference sheet is enough. */
    case Moderate = 'moderate';
    /** Much text: cards, rules read again and again. */
    case Extensive = 'extensive';
    /** Cannot be played without reading the language. */
    case Unplayable = 'unplayable';

    /**
     * The level, from 1 for `None` to 5 for `Unplayable`.
     */
    public function level(): int
    {
        return match ($this) {
            self::None => 1,
            self::Some => 2,
            self::Moderate => 3,
            self::Extensive => 4,
            self::Unplayable => 5,
        };
    }
}
