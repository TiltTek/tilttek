<?php

declare(strict_types=1);

namespace Tilttek\Collections\Tests\Unit\Fixture;

use Override;
use Tilttek\Collections\Catalog;
use Tilttek\Collections\CatalogProvider;

/**
 * A catalog the test edits as it goes, as a site edits the one it keeps in a database.
 */
final class MutableCatalog implements CatalogProvider
{
    public function __construct(
        public Catalog $catalog,
    ) {
    }

    #[Override]
    public function catalog(): Catalog
    {
        return $this->catalog;
    }
}
