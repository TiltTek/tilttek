<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Platforms\AbstractMySQLPlatform;
use Doctrine\DBAL\Platforms\AbstractPlatform;
use Doctrine\DBAL\Platforms\PostgreSQLPlatform;
use Doctrine\DBAL\Platforms\SQLitePlatform;
use Doctrine\DBAL\Query\QueryBuilder;
use Doctrine\DBAL\Types\Types;
use LogicException;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Appended;
use Tilttek\Activity\Context;
use Tilttek\Activity\Entry;
use Tilttek\Activity\EntryKind;
use Tilttek\Activity\Erasure;
use Tilttek\Activity\Exception\IdempotencyConflict;
use Tilttek\Activity\Filter;
use Tilttek\Activity\IdempotencyKey;
use Tilttek\Activity\Log\ActivityLog;
use Tilttek\Activity\Log\Reading;
use Tilttek\Activity\Totals;
use Tilttek\Activity\Verb;
use UnexpectedValueException;

/**
 * The journal in `tilttek_activity_entry`, through a DBAL connection.
 *
 * An entry is inserted by a statement that writes nothing when its key and kind are already there — recorded earlier,
 * or by another process at the same moment —, without aborting the surrounding transaction: `ON CONFLICT DO NOTHING`
 * on PostgreSQL, `INSERT IGNORE` on MySQL and MariaDB, `INSERT OR IGNORE` on SQLite; elsewhere, a plain insert whose
 * unique violation counts as "already there". The entry is then read back, so the caller gets the row the database
 * kept, and whether this call wrote it. Dates are written in UTC, to the second.
 */
final readonly class DbalActivityLog implements ActivityLog
{
    private const string INSERTED = 'kind, idempotency_key, member, verb, object, quantity, context, occurred_at, recorded_at';

    private const string COLUMNS = 'position, '.self::INSERTED;

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function append(Activity $activity, DateTimeImmutable $at): Appended
    {
        $new = $this->insert(EntryKind::Recorded, $activity, $at);
        $entry = $this->written($activity->key, EntryKind::Recorded);

        if (!$entry->activity->sameGestureAs($activity)) {
            throw IdempotencyConflict::on($activity->key);
        }

        return new Appended($entry, $new);
    }

    #[Override]
    public function cancel(IdempotencyKey $key, DateTimeImmutable $at): ?Appended
    {
        $recorded = $this->entry($key, EntryKind::Recorded);

        if (!$recorded instanceof Entry) {
            return null;
        }

        $new = $this->insert(EntryKind::Cancelled, $recorded->activity, $at);

        return new Appended($this->written($key, EntryKind::Cancelled), $new);
    }

    #[Override]
    public function find(IdempotencyKey $key): ?Entry
    {
        return $this->entry($key, EntryKind::Recorded);
    }

    #[Override]
    public function isCancelled(IdempotencyKey $key): bool
    {
        return $this->entry($key, EntryKind::Cancelled) instanceof Entry;
    }

    #[Override]
    public function read(int $after, int $limit, Filter $filter): array
    {
        Reading::check($after, $limit);

        $query = $this->connection->createQueryBuilder()
            ->select(self::COLUMNS)
            ->from(ActivityTables::ENTRIES)
            ->orderBy('position')
            ->setMaxResults($limit);
        $query->where('position > '.$query->createNamedParameter($after, ParameterType::INTEGER));
        $this->filter($query, $filter);
        $entries = [];

        foreach ($query->fetchAllAssociative() as $row) {
            $entries[] = $this->hydrate($row);
        }

        return $entries;
    }

    #[Override]
    public function lastPosition(): int
    {
        return $this->integer($this->connection->fetchOne(\sprintf('SELECT COALESCE(MAX(position), 0) FROM %s', ActivityTables::ENTRIES)));
    }

    #[Override]
    public function totals(Filter $filter): Totals
    {
        $query = $this->connection->createQueryBuilder()
            ->select('COUNT(*) AS activities', 'COALESCE(SUM(e.quantity), 0) AS quantity')
            ->from(ActivityTables::ENTRIES, 'e')
            ->where(\sprintf("e.kind = '%s'", EntryKind::Recorded->value))
            ->andWhere(\sprintf("NOT EXISTS (SELECT 1 FROM %s c WHERE c.idempotency_key = e.idempotency_key AND c.kind = '%s')", ActivityTables::ENTRIES, EntryKind::Cancelled->value));
        $this->filter($query, $filter);

        foreach ($query->fetchAllNumeric() as [$activities, $quantity]) {
            return new Totals($this->integer($activities), $this->integer($quantity));
        }

        return new Totals();
    }

    #[Override]
    public function eraseMember(ResourceReference $member): Erasure
    {
        return $this->connection->transactional(fn (Connection $connection): Erasure => new Erasure(
            $this->integer($connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', ActivityTables::ENTRIES), [$member->toString()])),
            $this->integer($connection->executeStatement(\sprintf('UPDATE %s SET object = NULL WHERE object = ?', ActivityTables::ENTRIES), [$member->toString()])),
        ));
    }

    /**
     * The insertion of an entry that writes nothing when its key and kind are already there, on this platform; a plain
     * insertion where the platform has no such statement.
     */
    public static function insertion(AbstractPlatform $platform): string
    {
        $into = \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)', ActivityTables::ENTRIES, self::INSERTED);

        return match (true) {
            $platform instanceof PostgreSQLPlatform => 'INSERT '.$into.' ON CONFLICT (idempotency_key, kind) DO NOTHING',
            $platform instanceof AbstractMySQLPlatform => 'INSERT IGNORE '.$into,
            $platform instanceof SQLitePlatform => 'INSERT OR IGNORE '.$into,
            default => 'INSERT '.$into,
        };
    }

    private function insert(EntryKind $kind, Activity $activity, DateTimeImmutable $at): bool
    {
        $values = [
            $kind->value,
            $activity->key->value,
            $activity->member->toString(),
            $activity->verb->value,
            $activity->object?->toString(),
            $activity->quantity,
            $activity->context->values,
            $this->utc($activity->occurredAt),
            $this->utc($at),
        ];
        $types = [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, Types::JSON, Types::DATETIME_IMMUTABLE, Types::DATETIME_IMMUTABLE];

        try {
            return 0 < $this->connection->executeStatement(self::insertion($this->connection->getDatabasePlatform()), $values, $types);
        } catch (UniqueConstraintViolationException) {
            return false;
        }
    }

    private function entry(IdempotencyKey $key, EntryKind $kind): ?Entry
    {
        $row = $this->connection->fetchAssociative(
            \sprintf('SELECT %s FROM %s WHERE idempotency_key = ? AND kind = ?', self::COLUMNS, ActivityTables::ENTRIES),
            [$key->value, $kind->value],
        );

        return false === $row ? null : $this->hydrate($row);
    }

    /**
     * The entry that is there after an insert: written by this process, or by another one.
     */
    private function written(IdempotencyKey $key, EntryKind $kind): Entry
    {
        $entry = $this->entry($key, $kind);

        if (!$entry instanceof Entry) {
            throw new LogicException(\sprintf('The %s entry of the key "%s" is neither written nor found.', $kind->value, $key->value));
        }

        return $entry;
    }

    /**
     * Adds the filter's conditions to a query on the entries; the columns are left unqualified, which reads them from
     * the one table of the query's own `FROM`. A prefix pattern compares the start of the verb, a portable `SUBSTRING`
     * rather than a `LIKE` whose `_` would match any character: `*`, an empty prefix, matches every verb.
     */
    private function filter(QueryBuilder $query, Filter $filter): void
    {
        if ($filter->member instanceof ResourceReference) {
            $query->andWhere('member = '.$query->createNamedParameter($filter->member->toString()));
        }

        if ($filter->object instanceof ResourceReference) {
            $query->andWhere('object = '.$query->createNamedParameter($filter->object->toString()));
        }

        if ($filter->since instanceof DateTimeImmutable) {
            $query->andWhere('occurred_at >= '.$query->createNamedParameter($this->utc($filter->since), Types::DATETIME_IMMUTABLE));
        }

        if ($filter->until instanceof DateTimeImmutable) {
            $query->andWhere('occurred_at < '.$query->createNamedParameter($this->utc($filter->until), Types::DATETIME_IMMUTABLE));
        }

        $verbs = [];
        $platform = $this->connection->getDatabasePlatform();

        foreach ($filter->verbs->patterns as $pattern) {
            $prefix = $pattern->prefix();
            $verbs[] = null === $prefix
                ? 'verb = '.$query->createNamedParameter($pattern->value)
                : $platform->getSubstringExpression('verb', '1', (string) \strlen($prefix)).' = '.$query->createNamedParameter($prefix);
        }

        if ([] !== $verbs) {
            $query->andWhere('('.implode(' OR ', $verbs).')');
        }
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Entry
    {
        return new Entry(
            $this->integer($row['position']),
            EntryKind::from($this->text($row['kind'])),
            new Activity(
                IdempotencyKey::fromString($this->text($row['idempotency_key'])),
                ResourceReference::fromString($this->text($row['member'])),
                Verb::fromString($this->text($row['verb'])),
                $this->date($row['occurred_at']),
                null === $row['object'] ? null : ResourceReference::fromString($this->text($row['object'])),
                $this->integer($row['quantity']),
                $this->context($row['context']),
            ),
            $this->date($row['recorded_at']),
        );
    }

    private function context(mixed $value): Context
    {
        $decoded = json_decode($this->text($value), true, flags: \JSON_THROW_ON_ERROR);

        if (!\is_array($decoded)) {
            throw new UnexpectedValueException('A context column holds JSON that is not an object.');
        }

        $values = [];

        foreach ($decoded as $name => $item) {
            if (!\is_string($name) || !(\is_bool($item) || \is_int($item) || \is_string($item))) {
                throw new UnexpectedValueException('A context column holds a value that is not an integer, a boolean or a string.');
            }

            $values[$name] = $item;
        }

        return new Context($values);
    }

    private function utc(DateTimeImmutable $date): DateTimeImmutable
    {
        return $date->setTimezone(new DateTimeZone('UTC'));
    }

    private function date(mixed $value): DateTimeImmutable
    {
        return new DateTimeImmutable($this->text($value), new DateTimeZone('UTC'));
    }

    private function text(mixed $value): string
    {
        if (!\is_string($value)) {
            throw new UnexpectedValueException(\sprintf('A text column holds %s.', get_debug_type($value)));
        }

        return $value;
    }

    private function integer(mixed $value): int
    {
        if (\is_int($value)) {
            return $value;
        }

        if (\is_string($value) && 1 === preg_match('/^-?\d+$/D', $value)) {
            return (int) $value;
        }

        throw new UnexpectedValueException(\sprintf('An integer column holds %s.', get_debug_type($value)));
    }
}
