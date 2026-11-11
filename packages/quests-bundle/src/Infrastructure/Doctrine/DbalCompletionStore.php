<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Completion;
use Tilttek\Quests\Store\CompletionStore;

/**
 * The journal of accomplished quests in `tilttek_quests_completion`, through a DBAL connection: one row per quest,
 * occurrence and subject, inserted by a statement that writes nothing when the row is already there.
 */
final readonly class DbalCompletionStore implements CompletionStore
{
    private const string COLUMNS = 'quest, occurrence, subject, completed_at, activity';

    private const string ORDER = 'ORDER BY completed_at, quest, occurrence';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(Completion $completion): bool
    {
        return 0 < $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?)', QuestsTables::COMPLETIONS, self::COLUMNS), 'quest, occurrence, subject'),
            [$completion->quest, $completion->occurrence, $completion->subject->toString(), Rows::utc($completion->completedAt), $completion->activity],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, Types::DATETIME_IMMUTABLE, ParameterType::STRING],
        );
    }

    #[Override]
    public function find(string $quest, string $occurrence, ResourceReference $subject): ?Completion
    {
        return $this->read('WHERE quest = ? AND occurrence = ? AND subject = ?', [$quest, $occurrence, $subject->toString()])[0] ?? null;
    }

    #[Override]
    public function latest(string $quest, ResourceReference $subject): ?Completion
    {
        $completions = $this->read('WHERE quest = ? AND subject = ?', [$quest, $subject->toString()]);

        return [] === $completions ? null : end($completions);
    }

    #[Override]
    public function ofSubject(ResourceReference $subject): array
    {
        return $this->read('WHERE subject = ?', [$subject->toString()]);
    }

    #[Override]
    public function forget(?DateTimeImmutable $since): array
    {
        if (!$since instanceof DateTimeImmutable) {
            $forgotten = $this->read('', []);
            $this->connection->executeStatement(\sprintf('DELETE FROM %s', QuestsTables::COMPLETIONS));

            return $forgotten;
        }

        $forgotten = $this->read('WHERE completed_at >= ?', [Rows::utc($since)], [Types::DATETIME_IMMUTABLE]);
        $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE completed_at >= ?', QuestsTables::COMPLETIONS), [Rows::utc($since)], [Types::DATETIME_IMMUTABLE]);

        return $forgotten;
    }

    #[Override]
    public function eraseSubject(ResourceReference $subject): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE subject = ?', QuestsTables::COMPLETIONS), [$subject->toString()]));
    }

    /**
     * @param list<mixed>                $parameters
     * @param list<ParameterType|string> $types
     *
     * @return list<Completion>
     */
    private function read(string $where, array $parameters, array $types = []): array
    {
        return array_map(
            static fn (array $row): Completion => new Completion(
                Rows::text($row['quest']),
                Rows::text($row['occurrence']),
                Rows::reference($row['subject']),
                Rows::date($row['completed_at']),
                Rows::text($row['activity']),
            ),
            $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s %s %s', self::COLUMNS, QuestsTables::COMPLETIONS, $where, self::ORDER), $parameters, $types),
        );
    }
}
