<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use LogicException;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\Reservation\Reservation;
use Tilttek\Exchange\Reservation\ReservationStatus;
use Tilttek\Exchange\Reservation\ReservationStore;

/**
 * The reservations in `tilttek_exchange_reservation`, through a DBAL connection.
 *
 * A reservation of a reward with a stock or a limit runs in a transaction that first updates the reward's row of
 * `tilttek_exchange_stock_lock`, which holds the row's write lock — or SQLite's lock of the whole database — until the
 * transaction ends: two reservations of the same reward count the units held one after the other. A reservation changes
 * status by an update that names the status it leaves — `paying` once, `reserved` once —, so that only one process
 * moves it.
 */
final readonly class DbalReservationStore implements ReservationStore
{
    private const string COLUMNS = 'reservation_key, member, reward, quantity, amount, currency, status, reserved_at, expires_at, settled_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function add(Reservation $reservation, ?int $stock, ?int $perMember): bool
    {
        return $this->connection->transactional(function (Connection $connection) use ($reservation, $stock, $perMember): bool {
            if ($this->find($reservation->key) instanceof Reservation) {
                return false;
            }

            if (null !== $stock || null !== $perMember) {
                $this->lock($connection, $reservation->reward);
            }

            if (null !== $stock && $this->held($reservation->reward) + $reservation->quantity > $stock) {
                throw ExchangeRefused::because(Refusal::OutOfStock, \sprintf('The reward "%s" has not %d units left.', $reservation->reward, $reservation->quantity));
            }

            if (null !== $perMember && $this->held($reservation->reward, $reservation->member) + $reservation->quantity > $perMember) {
                throw ExchangeRefused::because(Refusal::MemberLimitReached, \sprintf('A member holds at most %d units of "%s".', $perMember, $reservation->reward));
            }

            return 1 === $connection->executeStatement(
                Rows::ignoring($connection->getDatabasePlatform(), \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', ExchangeTables::RESERVATIONS, self::COLUMNS), 'reservation_key'),
                [
                    $reservation->key->value,
                    $reservation->member->toString(),
                    $reservation->reward,
                    $reservation->quantity,
                    $reservation->amount,
                    $reservation->currency,
                    $reservation->status->value,
                    $reservation->reservedAt,
                    $reservation->expiresAt,
                    null,
                ],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, Types::DATETIME_UTC_IMMUTABLE, Types::DATETIME_UTC_IMMUTABLE, ParameterType::NULL],
            );
        });
    }

    #[Override]
    public function find(ExchangeKey $key): ?Reservation
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE reservation_key = ?', self::COLUMNS, ExchangeTables::RESERVATIONS), [$key->value]);

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function transition(ExchangeKey $key, ReservationStatus $from, ReservationStatus $to, DateTimeImmutable $at): bool
    {
        return 1 === $this->connection->executeStatement(
            \sprintf('UPDATE %s SET status = ?, settled_at = ? WHERE reservation_key = ? AND status = ?', ExchangeTables::RESERVATIONS),
            [$to->value, $to->isOpen() ? null : $at, $key->value, $from->value],
            [ParameterType::STRING, Types::DATETIME_UTC_IMMUTABLE, ParameterType::STRING, ParameterType::STRING],
        );
    }

    #[Override]
    public function discard(ExchangeKey $key): void
    {
        $this->connection->executeStatement(
            \sprintf('DELETE FROM %s WHERE reservation_key = ? AND status = ?', ExchangeTables::RESERVATIONS),
            [$key->value, ReservationStatus::Paying->value],
        );
    }

    #[Override]
    public function held(string $reward, ?ResourceReference $member = null): int
    {
        $query = $this->connection->createQueryBuilder()
            ->select('COALESCE(SUM(quantity), 0)')
            ->from(ExchangeTables::RESERVATIONS);
        $query->where('reward = '.$query->createNamedParameter($reward))
            ->andWhere('status IN ('.$query->createNamedParameter(array_map(static fn (ReservationStatus $status): string => $status->value, ReservationStatus::holding()), ArrayParameterType::STRING).')');

        if ($member instanceof ResourceReference) {
            $query->andWhere('member = '.$query->createNamedParameter($member->toString()));
        }

        return Rows::integer($query->fetchOne());
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        return $this->hydrateAll($this->connection->createQueryBuilder()
            ->select(self::COLUMNS)
            ->from(ExchangeTables::RESERVATIONS)
            ->where('status IN (:statuses)')
            ->andWhere('expires_at <= :now')
            ->orderBy('expires_at')
            ->addOrderBy('reservation_key')
            ->setParameter('statuses', [ReservationStatus::Paying->value, ReservationStatus::Reserved->value], ArrayParameterType::STRING)
            ->setParameter('now', $now, Types::DATETIME_UTC_IMMUTABLE)
            ->setMaxResults($limit)
            ->fetchAllAssociative());
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->hydrateAll($this->connection->fetchAllAssociative(
            \sprintf('SELECT %s FROM %s WHERE member = ? ORDER BY reserved_at, reservation_key', self::COLUMNS, ExchangeTables::RESERVATIONS),
            [$member->toString()],
        ));
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE member = ?', ExchangeTables::RESERVATIONS), [$member->toString()]));
    }

    private function lock(Connection $connection, string $reward): void
    {
        $connection->executeStatement(Rows::ignoring($connection->getDatabasePlatform(), \sprintf('INTO %s (reward, reservations) VALUES (?, 0)', ExchangeTables::STOCK_LOCKS), 'reward'), [$reward]);

        if (1 !== $connection->executeStatement(\sprintf('UPDATE %s SET reservations = reservations + 1 WHERE reward = ?', ExchangeTables::STOCK_LOCKS), [$reward])) {
            throw new LogicException(\sprintf('The lock row of "%s" is neither written nor found.', $reward));
        }
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return list<Reservation>
     */
    private function hydrateAll(array $rows): array
    {
        $reservations = [];

        foreach ($rows as $row) {
            $reservations[] = $this->hydrate($row);
        }

        return $reservations;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Reservation
    {
        return new Reservation(
            ExchangeKey::fromString(Rows::text($row['reservation_key'])),
            Rows::reference($row['member']),
            Rows::text($row['reward']),
            Rows::integer($row['quantity']),
            Rows::integer($row['amount']),
            Rows::text($row['currency']),
            Rows::date($row['reserved_at']),
            Rows::date($row['expires_at']),
            ReservationStatus::from(Rows::text($row['status'])),
            Rows::nullableDate($row['settled_at']),
        );
    }
}
