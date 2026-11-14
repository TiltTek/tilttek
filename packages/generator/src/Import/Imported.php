<?php

declare(strict_types=1);

namespace Tilttek\Generator\Import;

use Tilttek\Generator\Definition\Catalog;
use Tilttek\Generator\Exception\InvalidDefinition;
use Tilttek\Generator\Loading\Reader;

/**
 * What an import made: a document of lists and generators, as `Reader` reads it and as a YAML or JSON file would hold
 * it, and the name of the generator to draw from first.
 */
final readonly class Imported
{
    /**
     * @param array<string, array<array-key, mixed>> $document `lists` and `generators`, by name
     */
    public function __construct(public array $document, public string $start)
    {
    }

    /**
     * The document read into a catalog.
     *
     * @throws InvalidDefinition
     */
    public function catalog(string $source = 'import'): Catalog
    {
        return new Reader()->catalog([$source => $this->document]);
    }
}
