<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog\Tests\Unit\Fixture;

use Override;
use Tilttek\GameCatalog\Contract\BarcodeResolver;
use Tilttek\GameCatalog\ExternalId;
use Tilttek\GameCatalog\Gtin;

/**
 * A resolver that knows the barcodes it is given, and counts the times it is asked.
 */
final class MappedResolver implements BarcodeResolver
{
    public int $asked = 0;

    /**
     * @param array<string, ExternalId> $identifiers by the 14 digits of the barcode
     */
    public function __construct(private readonly array $identifiers)
    {
    }

    #[Override]
    public function resolve(Gtin $barcode): ?ExternalId
    {
        ++$this->asked;

        return $this->identifiers[$barcode->value] ?? null;
    }
}
