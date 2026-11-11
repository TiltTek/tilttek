<?php

declare(strict_types=1);

namespace Tilttek\Collections;

/**
 * Where the catalog comes from: fixed in code or a configuration, or kept in a database the site edits. Each operation
 * asks for it, so that a catalog edited in the database is read as it is now.
 */
interface CatalogProvider
{
    public function catalog(): Catalog;
}
