<?php

declare(strict_types=1);

namespace Tilttek\Collections;

use Override;

/**
 * A catalog fixed in code or read from a configuration.
 */
final readonly class FixedCatalog implements CatalogProvider
{
    public function __construct(
        private Catalog $catalog,
    ) {
    }

    /**
     * @param array<array-key, mixed> $definition
     */
    public static function fromArray(array $definition): self
    {
        return new self(CatalogReader::read($definition));
    }

    #[Override]
    public function catalog(): Catalog
    {
        return $this->catalog;
    }
}
