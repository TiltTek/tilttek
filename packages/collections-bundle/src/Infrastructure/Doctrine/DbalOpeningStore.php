<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\Pack\OpeningStore;
use Tilttek\Collections\Pack\PackOpening;

/**
 * The packs opened in `tilttek_collections_opening`, through a DBAL connection: one row per key, its items and copies
 * as JSON lists, inserted by a statement that writes nothing when the row is already there.
 */
final readonly class DbalOpeningStore implements OpeningStore
{
    private const string COLUMNS = 'opening_key, member, pack, seed, commitment, client_seed, items, copies, opened_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(PackOpening $opening): bool
    {
        return 0 < $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', CollectionsTables::OPENINGS, self::COLUMNS), 'opening_key'),
            [$opening->key, $opening->member->toString(), $opening->pack, $opening->seed, $opening->commitment, $opening->clientSeed, Rows::encode($opening->items), Rows::encode($opening->copies), Rows::utc($opening->openedAt)],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, Types::DATETIME_IMMUTABLE],
        );
    }

    #[Override]
    public function find(string $key): ?PackOpening
    {
        return $this->read('WHERE opening_key = ?', [$key])[0] ?? null;
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->read('WHERE member = ?', [$member->toString()]);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', CollectionsTables::OPENINGS), [$member->toString()]));
    }

    /**
     * @param list<mixed> $parameters
     *
     * @return list<PackOpening>
     */
    private function read(string $where, array $parameters): array
    {
        return array_map(
            static fn (array $row): PackOpening => new PackOpening(
                Rows::text($row['opening_key']),
                Rows::reference($row['member']),
                Rows::text($row['pack']),
                Rows::text($row['seed']),
                Rows::text($row['commitment']),
                Rows::text($row['client_seed']),
                Rows::texts($row['items']),
                Rows::texts($row['copies']),
                Rows::date($row['opened_at']),
            ),
            $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s %s ORDER BY opened_at, opening_key', self::COLUMNS, CollectionsTables::OPENINGS, $where), $parameters),
        );
    }
}
