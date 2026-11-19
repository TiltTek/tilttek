<?php

declare(strict_types=1);

namespace Tilttek\AdventCalendarBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\AdventCalendar\Contract\OpeningStore;
use Tilttek\AdventCalendar\Opening;
use Tilttek\AdventCalendarBundle\Application\Contract\MemberOpenings;
use UnexpectedValueException;

/**
 * The openings in `tilttek_advent_calendar_opening`, through a DBAL connection. `add()` inserts the row and lets the
 * primary key refuse a second one: two requests that open the same box at once both insert, one wins, the other is told
 * the box was opened already. The insert runs in a transaction of its own — a savepoint inside the caller's —, so that
 * the refused insert never spoils a transaction PostgreSQL would otherwise abort. Moments are kept in UTC, to the
 * second. An opening is inserted undelivered — the column's default —, and `markDelivered()` turns it delivered in one
 * conditional update.
 */
final readonly class DbalOpeningStore implements OpeningStore, MemberOpenings
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function find(string $calendar, ResourceReference $member, int $day): ?Opening
    {
        return $this->where('calendar = ? AND member = ? AND day = ?', [$calendar, $member->toString(), $day], [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER])[0] ?? null;
    }

    #[Override]
    public function add(Opening $opening): bool
    {
        try {
            $this->connection->transactional(static fn (Connection $connection): int|string => $connection->insert(AdventCalendarTables::OPENINGS, [
                'calendar' => $opening->calendar,
                'member' => $opening->member->toString(),
                'day' => $opening->day,
                'opened_at' => $opening->openedAt->setTimezone(new DateTimeZone('UTC')),
            ], [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, Types::DATETIME_IMMUTABLE]));
        } catch (UniqueConstraintViolationException) {
            return false;
        }

        return true;
    }

    #[Override]
    public function undelivered(ResourceReference $member): array
    {
        return $this->where('member = ? AND delivered = ?', [$member->toString(), false], [ParameterType::STRING, ParameterType::BOOLEAN]);
    }

    #[Override]
    public function markDelivered(Opening $opening): void
    {
        $this->connection->executeStatement(
            \sprintf('UPDATE %s SET delivered = ? WHERE calendar = ? AND member = ? AND day = ? AND delivered = ?', AdventCalendarTables::OPENINGS),
            [true, $opening->calendar, $opening->member->toString(), $opening->day, false],
            [ParameterType::BOOLEAN, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::BOOLEAN],
        );
    }

    #[Override]
    public function openingsOf(string $calendar, ResourceReference $member): array
    {
        return $this->where('calendar = ? AND member = ?', [$calendar, $member->toString()]);
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return (int) $this->connection->delete(AdventCalendarTables::OPENINGS, ['member' => $member->toString()]);
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->where('member = ?', [$member->toString()]);
    }

    /**
     * @param list<string|int|bool> $parameters
     * @param list<ParameterType>   $types
     *
     * @return list<Opening> by calendar, then by day
     */
    private function where(string $condition, array $parameters, array $types = []): array
    {
        $rows = $this->connection->fetchAllAssociative(
            \sprintf('SELECT calendar, member, day, opened_at FROM %s WHERE %s ORDER BY calendar, day', AdventCalendarTables::OPENINGS, $condition),
            $parameters,
            $types,
        );

        return array_map(fn (array $row): Opening => new Opening(
            $this->text($row['calendar']),
            ResourceReference::fromString($this->text($row['member'])),
            $this->day($row['day']),
            new DateTimeImmutable($this->text($row['opened_at']), new DateTimeZone('UTC')),
        ), $rows);
    }

    /**
     * The day of a row, as the connection's platform converts a small integer: some drivers fetch numbers as text.
     */
    private function day(mixed $value): int
    {
        $day = $this->connection->convertToPHPValue($value, Types::SMALLINT);

        if (!\is_int($day)) {
            throw new UnexpectedValueException(\sprintf('The day of an opening holds %s.', get_debug_type($day)));
        }

        return $day;
    }

    private function text(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new UnexpectedValueException(\sprintf('A text column of the openings holds %s.', get_debug_type($value)));
        }

        return $value;
    }
}
