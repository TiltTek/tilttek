<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Psr\Clock\ClockInterface;
use Tilttek\Activity\Projection\Checkpoints;
use UnexpectedValueException;

/**
 * The checkpoints in `tilttek_activity_checkpoint`, one row per projection: a save updates the row, or inserts it the
 * first time; an insert that lost a race to another process updates the row that process wrote.
 */
final readonly class DbalCheckpoints implements Checkpoints
{
    public function __construct(
        private Connection $connection,
        private ClockInterface $clock,
    ) {
    }

    #[Override]
    public function position(string $projection): int
    {
        $position = $this->connection->fetchOne(\sprintf('SELECT position FROM %s WHERE projection = ?', ActivityTables::CHECKPOINTS), [$projection]);

        return match (true) {
            false === $position => 0,
            \is_int($position) => $position,
            \is_string($position) && 1 === preg_match('/^\d+$/D', $position) => (int) $position,
            default => throw new UnexpectedValueException(\sprintf('The checkpoint of "%s" holds %s.', $projection, get_debug_type($position))),
        };
    }

    #[Override]
    public function save(string $projection, int $position): void
    {
        $now = $this->clock->now();

        if (!$this->update($projection, $position, $now)) {
            try {
                $this->connection->executeStatement(
                    \sprintf('INSERT INTO %s (projection, position, updated_at) VALUES (?, ?, ?)', ActivityTables::CHECKPOINTS),
                    [$projection, $position, $now],
                    [ParameterType::STRING, ParameterType::INTEGER, Types::DATETIME_IMMUTABLE],
                );
            } catch (UniqueConstraintViolationException) {
                $this->update($projection, $position, $now);
            }
        }
    }

    /**
     * Whether a row was there to update. MySQL counts the rows it changed rather than those it found, which only
     * sends a save of the same position, in the same second, to the insert and back here.
     */
    private function update(string $projection, int $position, DateTimeImmutable $now): bool
    {
        return 0 < $this->connection->executeStatement(
            \sprintf('UPDATE %s SET position = ?, updated_at = ? WHERE projection = ?', ActivityTables::CHECKPOINTS),
            [$position, $now, $projection],
            [ParameterType::INTEGER, Types::DATETIME_IMMUTABLE, ParameterType::STRING],
        );
    }
}
