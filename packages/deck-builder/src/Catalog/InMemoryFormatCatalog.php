<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder\Catalog;

use Override;
use Tilttek\DeckBuilder\Contract\FormatCatalog;
use Tilttek\DeckBuilder\Format;

/**
 * Formats held in memory: formats declared in the application's configuration, tests and examples.
 */
final readonly class InMemoryFormatCatalog implements FormatCatalog
{
    /** @var array<string, Format> */
    private array $formats;

    public function __construct(Format ...$formats)
    {
        $byCode = [];

        foreach ($formats as $format) {
            $byCode[$format->code] = $format;
        }

        $this->formats = $byCode;
    }

    #[Override]
    public function find(string $code): ?Format
    {
        return $this->formats[$code] ?? null;
    }
}
