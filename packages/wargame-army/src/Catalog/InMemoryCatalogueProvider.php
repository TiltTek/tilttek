<?php

declare(strict_types=1);

namespace Tilttek\WargameArmy\Catalog;

use Override;
use Tilttek\WargameArmy\Catalogue;
use Tilttek\WargameArmy\Contract\CatalogueProvider;

/**
 * Catalogues held in memory: catalogues read from the application's configuration or files, tests and examples.
 */
final readonly class InMemoryCatalogueProvider implements CatalogueProvider
{
    /** @var array<string, Catalogue> */
    private array $catalogues;

    public function __construct(Catalogue ...$catalogues)
    {
        $byCode = [];

        foreach ($catalogues as $catalogue) {
            $byCode[$catalogue->code] = $catalogue;
        }

        $this->catalogues = $byCode;
    }

    #[Override]
    public function find(string $code): ?Catalogue
    {
        return $this->catalogues[$code] ?? null;
    }
}
