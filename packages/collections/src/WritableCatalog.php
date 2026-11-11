<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Tilttek\Collections\Exception\InvalidCollection;
use Tilttek\Collections\Exception\NotFound;

/**
 * A catalog the site edits, one definition at a time, in the plain values `CatalogReader` reads. The catalog that
 * results is checked whole before anything is written: an item whose rarity is removed, or a set left empty, is
 * refused.
 */
interface WritableCatalog extends CatalogProvider
{
    /**
     * Adds or replaces a definition.
     *
     * @throws InvalidCollection when the definition, or the catalog it makes, is refused
     * @throws NotFound          when the definition names a rarity or a set the catalog does not hold
     */
    public function define(DefinitionKind $kind, string $code, mixed $definition): void;

    /**
     * Removes a definition; returns whether there was one.
     *
     * @throws InvalidCollection when the catalog left is refused
     * @throws NotFound          when a definition left names the one removed
     */
    public function remove(DefinitionKind $kind, string $code): bool;
}
