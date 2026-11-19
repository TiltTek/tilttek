<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\TableSafety\Contract\FeedbackStore;
use Tilttek\TableSafety\Feedback\Feedback;
use Tilttek\TableSafety\Feedback\StarsAndWishes;

/**
 * The stars and wishes in `tilttek_table_safety_feedback`, through a DBAL connection: one row per table, session and
 * participant. A session's feedback is read in the order of the participants' references, not of their answers.
 */
final readonly class DbalFeedbackStore implements FeedbackStore
{
    private const string COLUMNS = 'table_ref, session, participant, feedback, given_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function save(Feedback $feedback): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($feedback): void {
            $connection->delete(TableSafetyTables::FEEDBACK, ['table_ref' => $feedback->table->toString(), 'session' => $feedback->session, 'participant' => $feedback->participant->toString()]);
            $connection->insert(TableSafetyTables::FEEDBACK, [
                'table_ref' => $feedback->table->toString(),
                'session' => $feedback->session,
                'participant' => $feedback->participant->toString(),
                'feedback' => Rows::json($feedback->starsAndWishes->toArray()),
                'given_at' => Rows::utc($feedback->givenAt),
            ], [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, Types::DATETIME_IMMUTABLE]);
        });
    }

    #[Override]
    public function ofSession(ResourceReference $table, string $session): array
    {
        return $this->where('table_ref = ? AND session = ?', [$table->toString(), $session]);
    }

    #[Override]
    public function ofParticipant(ResourceReference $participant): array
    {
        return $this->where('participant = ?', [$participant->toString()]);
    }

    #[Override]
    public function eraseParticipant(ResourceReference $participant): int
    {
        return (int) $this->connection->delete(TableSafetyTables::FEEDBACK, ['participant' => $participant->toString()]);
    }

    /**
     * @param list<string> $parameters
     *
     * @return list<Feedback>
     */
    private function where(string $condition, array $parameters): array
    {
        $feedback = [];

        foreach ($this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE %s ORDER BY participant, table_ref, session', self::COLUMNS, TableSafetyTables::FEEDBACK, $condition), $parameters) as $row) {
            $feedback[] = new Feedback(
                ResourceReference::fromString(Rows::text($row['table_ref'])),
                Rows::text($row['session']),
                ResourceReference::fromString(Rows::text($row['participant'])),
                StarsAndWishes::fromArray(Rows::decode($row['feedback'])),
                Rows::date($row['given_at']),
            );
        }

        return $feedback;
    }
}
