<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

/**
 * How one entry relates to another. The first six are the relationship types of the OpenTabletop data model;
 * `EditionOf` and `TranslationOf` are the library's own.
 */
enum LinkType: string
{
    /** Adds content to the other, and needs it to be played. */
    case Expands = 'expands';
    /** A new version of the other, with changed rules. */
    case Reimplements = 'reimplements';
    /** Holds the other in its box: a big box, a compilation. */
    case Contains = 'contains';
    /** Cannot be used without the other; stronger than `Expands`. */
    case Requires = 'requires';
    /** Suggests the other as a companion, without needing it. */
    case Recommends = 'recommends';
    /** Played together with the other; read in both directions. */
    case IntegratesWith = 'integrates_with';
    /** Another edition of the same game, with the same rules or nearly. */
    case EditionOf = 'edition_of';
    /** The same edition in another language. */
    case TranslationOf = 'translation_of';

    /**
     * Whether the link reads the same in both directions, so that one link is enough for both entries.
     */
    public function isSymmetric(): bool
    {
        return self::IntegratesWith === $this;
    }
}
