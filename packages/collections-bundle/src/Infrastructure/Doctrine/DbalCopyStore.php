<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\Copy;
use Tilttek\Collections\Source;
use Tilttek\Collections\Store\CopyStore;

/**
 * The copies in `tilttek_collections_copy`, through a DBAL connection: one row per identifier, inserted by a statement
 * that writes nothing when the row is already there; a copy changes hands by an update that names its owner, so that
 * two trades of the same copy cannot both pass.
 */
final readonly class DbalCopyStore implements CopyStore
{
    private const string COLUMNS = 'id, item, owner, source, copy_key, acquired_at, tradable, moved';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(Copy $copy): bool
    {
        return 0 < $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?, ?, ?)', CollectionsTables::COPIES, self::COLUMNS), 'id'),
            [$copy->id, $copy->item, $copy->owner->toString(), $copy->source->value, $copy->key, Rows::utc($copy->acquiredAt), $copy->tradable, $copy->moved],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, Types::DATETIME_IMMUTABLE, ParameterType::BOOLEAN, ParameterType::BOOLEAN],
        );
    }

    #[Override]
    public function find(string $id): ?Copy
    {
        return $this->read('WHERE id = ?', [$id])[0] ?? null;
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->read('WHERE owner = ?', [$member->toString()]);
    }

    #[Override]
    public function reassign(string $id, ResourceReference $from, ResourceReference $to): bool
    {
        return 0 < $this->connection->executeStatement(
            \sprintf('UPDATE %s SET owner = ?, moved = ? WHERE id = ? AND owner = ?', CollectionsTables::COPIES),
            [$to->toString(), true, $id, $from->toString()],
            [ParameterType::STRING, ParameterType::BOOLEAN, ParameterType::STRING, ParameterType::STRING],
        );
    }

    #[Override]
    public function takeBack(string $id, ResourceReference $member): bool
    {
        return 0 < $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE id = ? AND owner = ? AND moved = ?', CollectionsTables::COPIES),
            [$id, $member->toString(), false],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::BOOLEAN],
        );
    }

    #[Override]
    public function forget(Source $source, ?DateTimeImmutable $since): int
    {
        if (!$since instanceof DateTimeImmutable) {
            return Rows::integer($this->connection->executeStatement(
                \sprintf('DELETE FROM %s WHERE source = ? AND moved = ?', CollectionsTables::COPIES),
                [$source->value, false],
                [ParameterType::STRING, ParameterType::BOOLEAN],
            ));
        }

        return Rows::integer($this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE source = ? AND moved = ? AND acquired_at >= ?', CollectionsTables::COPIES),
            [$source->value, false, Rows::utc($since)],
            [ParameterType::STRING, ParameterType::BOOLEAN, Types::DATETIME_IMMUTABLE],
        ));
    }

    #[Override]
    public function eraseOwner(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE owner = ?', CollectionsTables::COPIES), [$member->toString()]));
    }

    /**
     * @param list<mixed> $parameters
     *
     * @return list<Copy>
     */
    private function read(string $where, array $parameters): array
    {
        return array_map(
            static fn (array $row): Copy => new Copy(
                Rows::text($row['id']),
                Rows::text($row['item']),
                Rows::reference($row['owner']),
                Source::from(Rows::text($row['source'])),
                Rows::text($row['copy_key']),
                Rows::date($row['acquired_at']),
                Rows::boolean($row['tradable']),
                Rows::boolean($row['moved']),
            ),
            $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s %s ORDER BY acquired_at, id', self::COLUMNS, CollectionsTables::COPIES, $where), $parameters),
        );
    }
}
