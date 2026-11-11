<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Enrolment;
use Tilttek\Quests\Store\EnrolmentStore;

/**
 * The enrolments in `tilttek_quests_enrolment`, through a DBAL connection: the member's row for the quest replaced in
 * one transaction.
 */
final readonly class DbalEnrolmentStore implements EnrolmentStore
{
    private const string COLUMNS = 'quest, member, accepted_at, team, abandoned_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function save(Enrolment $enrolment): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($enrolment): void {
            $connection->delete(QuestsTables::ENROLMENTS, ['quest' => $enrolment->quest, 'member' => $enrolment->member->toString()]);
            $connection->insert(QuestsTables::ENROLMENTS, [
                'quest' => $enrolment->quest,
                'member' => $enrolment->member->toString(),
                'accepted_at' => Rows::utc($enrolment->acceptedAt),
                'team' => $enrolment->team?->toString(),
                'abandoned_at' => $enrolment->abandonedAt instanceof DateTimeImmutable ? Rows::utc($enrolment->abandonedAt) : null,
            ], [ParameterType::STRING, ParameterType::STRING, Types::DATETIME_IMMUTABLE, ParameterType::STRING, Types::DATETIME_IMMUTABLE]);
        });
    }

    #[Override]
    public function find(string $quest, ResourceReference $member): ?Enrolment
    {
        return $this->read('WHERE quest = ? AND member = ?', [$quest, $member->toString()])[0] ?? null;
    }

    #[Override]
    public function ofQuest(string $quest): array
    {
        return $this->read('WHERE quest = ? ORDER BY member', [$quest]);
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->read('WHERE member = ? ORDER BY quest', [$member->toString()]);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', QuestsTables::ENROLMENTS), [$member->toString()]));
    }

    /**
     * @param list<string> $parameters
     *
     * @return list<Enrolment>
     */
    private function read(string $where, array $parameters): array
    {
        return array_map(
            static fn (array $row): Enrolment => new Enrolment(
                Rows::text($row['quest']),
                Rows::reference($row['member']),
                Rows::date($row['accepted_at']),
                Rows::optionalReference($row['team']),
                Rows::optionalDate($row['abandoned_at']),
            ),
            $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s %s', self::COLUMNS, QuestsTables::ENROLMENTS, $where), $parameters),
        );
    }
}
