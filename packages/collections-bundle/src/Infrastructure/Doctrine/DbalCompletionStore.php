<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\SetCompletion;
use Tilttek\Collections\Store\CompletionStore;

/**
 * The sets completed in `tilttek_collections_completion`, through a DBAL connection: one row per set and member,
 * inserted by a statement that writes nothing when the row is already there.
 */
final readonly class DbalCompletionStore implements CompletionStore
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(SetCompletion $completion): bool
    {
        return 0 < $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (set_code, member, completed_at) VALUES (?, ?, ?)', CollectionsTables::COMPLETIONS), 'set_code, member'),
            [$completion->set, $completion->member->toString(), Rows::utc($completion->completedAt)],
            [ParameterType::STRING, ParameterType::STRING, Types::DATETIME_IMMUTABLE],
        );
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return array_map(
            static fn (array $row): SetCompletion => new SetCompletion(Rows::text($row['set_code']), Rows::reference($row['member']), Rows::date($row['completed_at'])),
            $this->connection->fetchAllAssociative(\sprintf('SELECT set_code, member, completed_at FROM %s WHERE member = ? ORDER BY completed_at, set_code', CollectionsTables::COMPLETIONS), [$member->toString()]),
        );
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', CollectionsTables::COMPLETIONS), [$member->toString()]));
    }
}
