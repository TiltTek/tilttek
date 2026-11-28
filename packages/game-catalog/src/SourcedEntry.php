<?php

declare(strict_types=1);

namespace Tilttek\GameCatalog;

use Tilttek\GameCatalog\Exception\InvalidEntry;
use Tilttek\GameCatalog\Internal\Identifier;

/**
 * An entry as a source sends it: its details, the identifiers it knows and its errata. The catalogue adds it the first
 * time and updates it after, keeping the corrections the administration accepted.
 */
final readonly class SourcedEntry
{
    public Origin $origin;

    /**
     * @param string           $id          the identifier the catalogue gives the entry when the source sends it first
     * @param list<ExternalId> $identifiers
     * @param list<Erratum>    $errata
     *
     * @throws InvalidEntry when an identifier is not one
     */
    public function __construct(
        public string $source,
        string $key,
        public string $id,
        public Details $details,
        public array $identifiers = [],
        public array $errata = [],
    ) {
        $this->origin = Origin::source($source, $key);
        Identifier::check($id, 'an entry');
    }
}
