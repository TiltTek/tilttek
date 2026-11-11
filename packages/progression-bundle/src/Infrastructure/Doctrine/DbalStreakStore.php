<?php

declare(strict_types=1);

namespace Tilttek\ProgressionBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Progression\Store\StreakStore;
use Tilttek\Progression\Streak\StreakGesture;
use Tilttek\Progression\Streak\StreakState;

/**
 * The streaks in three tables, through a DBAL connection: the gestures, one row per streak and activity; the frozen
 * units; and the state of each member's streak, written over in a transaction.
 */
final readonly class DbalStreakStore implements StreakStore
{
    private const string GESTURE_COLUMNS = 'streak, member, activity, unit, occurred_at';

    private const string STATE_COLUMNS = 'streak, member, current_run, best_run, run_start, last_active, timezone';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(StreakGesture $gesture): bool
    {
        return 0 < $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?)', ProgressionTables::GESTURES, self::GESTURE_COLUMNS), 'streak, activity'),
            [$gesture->streak, $gesture->member->toString(), $gesture->activity, $gesture->unit, Rows::utc($gesture->occurredAt)],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, Types::DATETIME_IMMUTABLE],
        );
    }

    #[Override]
    public function remove(string $activity): array
    {
        return $this->connection->transactional(static function (Connection $connection) use ($activity): array {
            $removed = array_map(
                static fn (array $row): StreakGesture => new StreakGesture(
                    Rows::text($row['streak']),
                    ResourceReference::fromString(Rows::text($row['member'])),
                    Rows::text($row['activity']),
                    Rows::integer($row['unit']),
                    Rows::date($row['occurred_at']),
                ),
                $connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE activity = ? ORDER BY streak', self::GESTURE_COLUMNS, ProgressionTables::GESTURES), [$activity]),
            );
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE activity = ?', ProgressionTables::GESTURES), [$activity]);

            return $removed;
        });
    }

    #[Override]
    public function units(string $streak, ResourceReference $member): array
    {
        return array_map(static fn (array $row): int => Rows::integer($row['unit']), $this->connection->fetchAllAssociative(\sprintf('SELECT DISTINCT unit FROM %s WHERE member = ? AND streak = ? ORDER BY unit', ProgressionTables::GESTURES), [$member->toString(), $streak]));
    }

    #[Override]
    public function freeze(string $streak, ResourceReference $member, int $unit): bool
    {
        return 0 < $this->connection->executeStatement(
            Rows::ignoring($this->connection->getDatabasePlatform(), \sprintf('INTO %s (streak, member, unit) VALUES (?, ?, ?)', ProgressionTables::FREEZES), 'member, streak, unit'),
            [$streak, $member->toString(), $unit],
            [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER],
        );
    }

    #[Override]
    public function thaw(string $streak, ResourceReference $member, int $unit): bool
    {
        return 0 < $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ? AND streak = ? AND unit = ?', ProgressionTables::FREEZES), [$member->toString(), $streak, $unit], [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER]);
    }

    #[Override]
    public function frozen(string $streak, ResourceReference $member): array
    {
        return array_map(static fn (array $row): int => Rows::integer($row['unit']), $this->connection->fetchAllAssociative(\sprintf('SELECT unit FROM %s WHERE member = ? AND streak = ? ORDER BY unit', ProgressionTables::FREEZES), [$member->toString(), $streak]));
    }

    #[Override]
    public function state(string $streak, ResourceReference $member): ?StreakState
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE member = ? AND streak = ?', self::STATE_COLUMNS, ProgressionTables::STATES), [$member->toString(), $streak]);

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function save(StreakState $state): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($state): void {
            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ? AND streak = ?', ProgressionTables::STATES), [$state->member->toString(), $state->streak]);
            $connection->executeStatement(
                \sprintf('INSERT INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?, ?)', ProgressionTables::STATES, self::STATE_COLUMNS),
                [$state->streak, $state->member->toString(), $state->current, $state->best, $state->start, $state->lastActive, $state->timezone],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING],
            );
        });
    }

    #[Override]
    public function discard(string $streak, ResourceReference $member): void
    {
        $this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ? AND streak = ?', ProgressionTables::STATES), [$member->toString(), $streak]);
    }

    #[Override]
    public function alive(string $streak, int $before): array
    {
        return array_map(
            $this->hydrate(...),
            $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s WHERE streak = ? AND current_run > 0 AND last_active < ? ORDER BY member', self::STATE_COLUMNS, ProgressionTables::STATES), [$streak, $before], [ParameterType::STRING, ParameterType::INTEGER]),
        );
    }

    #[Override]
    public function forget(?DateTimeImmutable $since): int
    {
        if (!$since instanceof DateTimeImmutable) {
            return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s', ProgressionTables::GESTURES)));
        }

        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE occurred_at >= ?', ProgressionTables::GESTURES), [Rows::utc($since)], [Types::DATETIME_IMMUTABLE]));
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return $this->connection->transactional(static function (Connection $connection) use ($member): int {
            $erased = 0;

            foreach ([ProgressionTables::GESTURES, ProgressionTables::FREEZES, ProgressionTables::STATES] as $table) {
                $erased += Rows::integer($connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', $table), [$member->toString()]));
            }

            return $erased;
        });
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): StreakState
    {
        return new StreakState(
            Rows::text($row['streak']),
            ResourceReference::fromString(Rows::text($row['member'])),
            Rows::integer($row['current_run']),
            Rows::integer($row['best_run']),
            null === $row['run_start'] ? null : Rows::integer($row['run_start']),
            Rows::integer($row['last_active']),
            Rows::text($row['timezone']),
        );
    }
}
