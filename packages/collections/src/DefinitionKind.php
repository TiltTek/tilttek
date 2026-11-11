<?php

declare(strict_types=1);

namespace Tilttek\Collections;

/**
 * The four kinds of definitions a catalog holds, as a store that edits them names them.
 */
enum DefinitionKind: string
{
    case Rarity = 'rarity';
    case Item = 'item';
    case Set = 'set';
    case Pack = 'pack';

    /**
     * The key of the catalog's definition that holds this kind, for `CatalogReader`.
     */
    public function key(): string
    {
        return match ($this) {
            self::Rarity => 'rarities',
            self::Item => 'items',
            self::Set => 'sets',
            self::Pack => 'packs',
        };
    }
}
