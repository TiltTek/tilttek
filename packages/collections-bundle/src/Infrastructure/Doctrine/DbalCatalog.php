<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use JsonException;
use Override;
use Tilttek\Collections\Catalog;
use Tilttek\Collections\CatalogReader;
use Tilttek\Collections\DefinitionKind;
use Tilttek\Collections\Exception\InvalidCollection;
use Tilttek\Collections\WritableCatalog;

/**
 * The catalog in `tilttek_collections_definition`, through a DBAL connection, for a site whose collection is edited
 * rather than configured: each rarity, item, set and pack is kept as JSON, as `CatalogReader` reads it, in the order
 * it was first defined — the order of the rarities is that of the catalog —, and read again with every question, so
 * that a definition saved is seen at once.
 */
final readonly class DbalCatalog implements WritableCatalog
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function catalog(): Catalog
    {
        return CatalogReader::read($this->definitions());
    }

    #[Override]
    public function define(DefinitionKind $kind, string $code, mixed $definition): void
    {
        $definitions = $this->definitions();
        $definitions[$kind->key()][$code] = $definition;
        CatalogReader::read($definitions);
        $json = json_encode($definition, \JSON_THROW_ON_ERROR);

        $this->connection->transactional(static function (Connection $connection) use ($kind, $code, $json): void {
            $position = $connection->fetchOne(\sprintf('SELECT position FROM %s WHERE kind = ? AND code = ?', CollectionsTables::DEFINITIONS), [$kind->value, $code]);

            if (false === $position) {
                $position = $connection->fetchOne(\sprintf('SELECT COALESCE(MAX(position), 0) + 1 FROM %s WHERE kind = ?', CollectionsTables::DEFINITIONS), [$kind->value]);
            }

            $connection->delete(CollectionsTables::DEFINITIONS, ['kind' => $kind->value, 'code' => $code]);
            $connection->insert(CollectionsTables::DEFINITIONS, ['kind' => $kind->value, 'code' => $code, 'position' => Rows::integer($position), 'definition' => $json]);
        });
    }

    #[Override]
    public function remove(DefinitionKind $kind, string $code): bool
    {
        $definitions = $this->definitions();

        if (!isset($definitions[$kind->key()][$code])) {
            return false;
        }

        unset($definitions[$kind->key()][$code]);
        CatalogReader::read($definitions);
        $this->connection->delete(CollectionsTables::DEFINITIONS, ['kind' => $kind->value, 'code' => $code]);

        return true;
    }

    /**
     * @return array<string, array<string, mixed>> the definitions by kind, then by code
     */
    private function definitions(): array
    {
        $definitions = [];

        foreach ($this->connection->fetchAllAssociative(\sprintf('SELECT kind, code, definition FROM %s ORDER BY kind, position, code', CollectionsTables::DEFINITIONS)) as $row) {
            $kind = DefinitionKind::from(Rows::text($row['kind']));
            $code = Rows::text($row['code']);

            try {
                $definitions[$kind->key()][$code] = json_decode(Rows::text($row['definition']), true, 64, \JSON_THROW_ON_ERROR);
            } catch (JsonException $jsonException) {
                throw InvalidCollection::because(\sprintf('The definition of the %s "%s" is not JSON: %s.', $kind->value, $code, $jsonException->getMessage()));
            }
        }

        return $definitions;
    }
}
