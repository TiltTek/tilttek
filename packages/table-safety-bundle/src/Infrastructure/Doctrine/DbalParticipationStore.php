<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\ConsentChecklist;
use Tilttek\TableSafety\Contract\ParticipationStore;
use Tilttek\TableSafety\LinesAndVeils;
use Tilttek\TableSafety\Participation;
use Tilttek\TableSafety\SessionZero\Answers;

/**
 * The participations in `tilttek_table_safety_participation`, through a DBAL connection: one row per table and
 * participant, the answers as JSON, with the moments of the first answer and of the last change. A table's participations are read in the order of their participants'
 * references, not of their answers.
 */
final readonly class DbalParticipationStore implements ParticipationStore
{
    private const string COLUMNS = 'table_ref, participant, lines_and_veils, consent, session_zero, first_answered_at, updated_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function find(ResourceReference $table, ResourceReference $participant): ?Participation
    {
        return $this->where('table_ref = ? AND participant = ?', [$table->toString(), $participant->toString()])[0] ?? null;
    }

    #[Override]
    public function save(Participation $participation): void
    {
        $this->connection->transactional(function (Connection $connection) use ($participation): void {
            $this->remove($participation->table, $participation->participant);
            $connection->insert(TableSafetyTables::PARTICIPATIONS, [
                'table_ref' => $participation->table->toString(),
                'participant' => $participation->participant->toString(),
                'lines_and_veils' => Rows::json($participation->linesAndVeils->toArray()),
                'consent' => Rows::json($participation->consent->toArray()),
                'session_zero' => Rows::json($participation->sessionZero->toArray()),
                'first_answered_at' => Rows::utc($participation->firstAnsweredAt),
                'updated_at' => Rows::utc($participation->updatedAt),
            ], [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, Types::DATETIME_IMMUTABLE, Types::DATETIME_IMMUTABLE]);
        });
    }

    #[Override]
    public function remove(ResourceReference $table, ResourceReference $participant): void
    {
        $this->connection->delete(TableSafetyTables::PARTICIPATIONS, ['table_ref' => $table->toString(), 'participant' => $participant->toString()]);
    }

    #[Override]
    public function ofTable(ResourceReference $table): array
    {
        return $this->where('table_ref = ?', [$table->toString()]);
    }

    #[Override]
    public function ofParticipant(ResourceReference $participant): array
    {
        return $this->where('participant = ?', [$participant->toString()]);
    }

    #[Override]
    public function eraseParticipant(ResourceReference $participant): int
    {
        return (int) $this->connection->delete(TableSafetyTables::PARTICIPATIONS, ['participant' => $participant->toString()]);
    }

    /**
     * @param list<string> $parameters
     *
     * @return list<Participation>
     */
    private function where(string $condition, array $parameters): array
    {
        $participations = [];

        foreach ($this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE %s ORDER BY participant, table_ref', self::COLUMNS, TableSafetyTables::PARTICIPATIONS, $condition), $parameters) as $row) {
            $participations[] = new Participation(
                ResourceReference::fromString(Rows::text($row['table_ref'])),
                ResourceReference::fromString(Rows::text($row['participant'])),
                LinesAndVeils::fromArray(Rows::decode($row['lines_and_veils'])),
                ConsentChecklist::fromArray(Rows::decode($row['consent'])),
                Answers::fromArray(Rows::decode($row['session_zero'])),
                Rows::date($row['updated_at']),
                Rows::date($row['first_answered_at']),
            );
        }

        return $participations;
    }
}
