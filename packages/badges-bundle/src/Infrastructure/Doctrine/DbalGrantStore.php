<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Badges\Store\GrantStore;

/**
 * The consents in `tilttek_badges_grant`, through a DBAL connection: one row per member and application, inserted by a
 * statement that writes nothing when the row is already there.
 */
final readonly class DbalGrantStore implements GrantStore
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function grant(ResourceReference $member, string $client, DateTimeImmutable $at): void
    {
        $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (member, client, granted_at) VALUES (?, ?, ?)', BadgesTables::GRANTS), 'member, client'),
            [$member->toString(), $client, Rows::utc($at)],
            [ParameterType::STRING, ParameterType::STRING, Types::DATETIME_IMMUTABLE],
        );
    }

    #[Override]
    public function withdraw(ResourceReference $member, string $client): bool
    {
        return 0 < $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ? AND client = ?', BadgesTables::GRANTS), [$member->toString(), $client]);
    }

    #[Override]
    public function allows(ResourceReference $member, string $client): bool
    {
        return false !== $this->connection->fetchOne(\sprintf('SELECT client FROM %s WHERE member = ? AND client = ?', BadgesTables::GRANTS), [$member->toString(), $client]);
    }

    #[Override]
    public function clients(ResourceReference $member): array
    {
        $clients = [];

        foreach ($this->connection->fetchFirstColumn(\sprintf('SELECT client FROM %s WHERE member = ? ORDER BY client', BadgesTables::GRANTS), [$member->toString()]) as $client) {
            $clients[] = Rows::text($client);
        }

        return $clients;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', BadgesTables::GRANTS), [$member->toString()]));
    }
}
