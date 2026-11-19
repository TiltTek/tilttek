<?php

declare(strict_types=1);

namespace Tilttek\ExchangeBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Exchange\Erasure;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\ExchangeKey;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\Trade\Offer;
use Tilttek\Exchange\Trade\OfferStatus;
use Tilttek\Exchange\Trade\OfferStore;

/**
 * The offers in `tilttek_exchange_offer` and their items in `tilttek_exchange_offer_item`, through a DBAL connection.
 *
 * An offer opens in a transaction: its row is inserted by a statement that writes nothing when its key is already
 * there, then each offered item is written to `tilttek_exchange_hold`, whose primary key is the item — an item another
 * open offer holds is refused, and the transaction rolled back. Closing an offer updates it only while it is open, and
 * deletes its holds in the same transaction.
 */
final readonly class DbalOfferStore implements OfferStore
{
    private const string COLUMNS = 'offer_key, proposer, recipient, offered_points, requested_points, currency, status, made_at, expires_at, closed_at, counters';

    private const string OFFERED = 'offered';

    private const string REQUESTED = 'requested';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function open(Offer $offer): bool
    {
        return $this->connection->transactional(function (Connection $connection) use ($offer): bool {
            $platform = $connection->getDatabasePlatform();
            $inserted = $connection->executeStatement(
                Rows::ignoring($platform, \sprintf('INTO %s (%s) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)', ExchangeTables::OFFERS, self::COLUMNS), 'offer_key'),
                [
                    $offer->key->value,
                    $offer->proposer->toString(),
                    $offer->recipient->toString(),
                    $offer->offeredPoints,
                    $offer->requestedPoints,
                    $offer->currency,
                    $offer->status->value,
                    $offer->madeAt,
                    $offer->expiresAt,
                    null,
                    $offer->counters?->value,
                ],
                [ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::INTEGER, ParameterType::STRING, ParameterType::STRING, Types::DATETIME_UTC_IMMUTABLE, Types::DATETIME_UTC_IMMUTABLE, ParameterType::NULL, ParameterType::STRING],
            );

            if (0 === $inserted) {
                return false;
            }

            foreach ($offer->offered as $item) {
                $holder = $this->holder($item);

                if ($holder instanceof ExchangeKey) {
                    throw ExchangeRefused::because(Refusal::AlreadyHeld, \sprintf('"%s" is held by the offer "%s".', $item->toString(), $holder->value));
                }

                if (1 !== $connection->executeStatement(Rows::ignoring($platform, \sprintf('INTO %s (item, offer_key) VALUES (?, ?)', ExchangeTables::HOLDS), 'item'), [$item->toString(), $offer->key->value])) {
                    throw ExchangeRefused::because(Refusal::AlreadyHeld, \sprintf('"%s" is held by another offer.', $item->toString()));
                }
            }

            foreach ([self::OFFERED => $offer->offered, self::REQUESTED => $offer->requested] as $side => $items) {
                foreach ($items as $position => $item) {
                    $connection->insert(ExchangeTables::OFFER_ITEMS, ['offer_key' => $offer->key->value, 'side' => $side, 'position' => $position, 'item' => $item->toString()], [ParameterType::STRING, ParameterType::STRING, ParameterType::INTEGER, ParameterType::STRING]);
                }
            }

            return true;
        });
    }

    #[Override]
    public function find(ExchangeKey $key): ?Offer
    {
        $row = $this->connection->fetchAssociative(\sprintf('SELECT %s FROM %s WHERE offer_key = ?', self::COLUMNS, ExchangeTables::OFFERS), [$key->value]);

        return false === $row ? null : $this->hydrate($row);
    }

    #[Override]
    public function close(ExchangeKey $key, OfferStatus $status, DateTimeImmutable $at): bool
    {
        return $this->connection->transactional(static function (Connection $connection) use ($key, $status, $at): bool {
            $closed = $connection->executeStatement(
                \sprintf('UPDATE %s SET status = ?, closed_at = ? WHERE offer_key = ? AND status = ?', ExchangeTables::OFFERS),
                [$status->value, $at, $key->value, OfferStatus::Open->value],
                [ParameterType::STRING, Types::DATETIME_UTC_IMMUTABLE, ParameterType::STRING, ParameterType::STRING],
            );

            if (1 !== $closed) {
                return false;
            }

            $connection->executeStatement(\sprintf('DELETE FROM %s WHERE offer_key = ?', ExchangeTables::HOLDS), [$key->value]);

            return true;
        });
    }

    #[Override]
    public function holder(ResourceReference $item): ?ExchangeKey
    {
        $key = $this->connection->fetchOne(\sprintf('SELECT offer_key FROM %s WHERE item = ?', ExchangeTables::HOLDS), [$item->toString()]);

        return false === $key ? null : ExchangeKey::fromString(Rows::text($key));
    }

    #[Override]
    public function made(ResourceReference $proposer, ?DateTimeImmutable $since, ?DateTimeImmutable $until): int
    {
        $query = $this->connection->createQueryBuilder()
            ->select('COUNT(*)')
            ->from(ExchangeTables::OFFERS);
        $query->where('proposer = '.$query->createNamedParameter($proposer->toString()));

        if ($since instanceof DateTimeImmutable) {
            $query->andWhere('made_at >= '.$query->createNamedParameter($since, Types::DATETIME_UTC_IMMUTABLE));
        }

        if ($until instanceof DateTimeImmutable) {
            $query->andWhere('made_at < '.$query->createNamedParameter($until, Types::DATETIME_UTC_IMMUTABLE));
        }

        return Rows::integer($query->fetchOne());
    }

    #[Override]
    public function due(DateTimeImmutable $now, int $limit): array
    {
        return $this->hydrateAll($this->connection->createQueryBuilder()
            ->select(self::COLUMNS)
            ->from(ExchangeTables::OFFERS)
            ->where('status = :status')
            ->andWhere('expires_at <= :now')
            ->orderBy('expires_at')
            ->addOrderBy('offer_key')
            ->setParameter('status', OfferStatus::Open->value)
            ->setParameter('now', $now, Types::DATETIME_UTC_IMMUTABLE)
            ->setMaxResults($limit)
            ->fetchAllAssociative());
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->hydrateAll($this->connection->fetchAllAssociative(
            \sprintf('SELECT %s FROM %s WHERE proposer = ? OR recipient = ? ORDER BY made_at, offer_key', self::COLUMNS, ExchangeTables::OFFERS),
            [$member->toString(), $member->toString()],
        ));
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        $erased = Erasure::ERASED;

        return $this->connection->transactional(static fn (Connection $connection): int => Rows::integer($connection->executeStatement(\sprintf('UPDATE %s SET proposer = ? WHERE proposer = ?', ExchangeTables::OFFERS), [$erased, $member->toString()]))
            + Rows::integer($connection->executeStatement(\sprintf('UPDATE %s SET recipient = ? WHERE recipient = ?', ExchangeTables::OFFERS), [$erased, $member->toString()])));
    }

    /**
     * @param array<int, array<string, mixed>> $rows
     *
     * @return list<Offer>
     */
    private function hydrateAll(array $rows): array
    {
        $values = [];

        foreach ($rows as $row) {
            $values[] = $this->hydrate($row);
        }

        return $values;
    }

    /**
     * @param array<string, mixed> $row
     */
    private function hydrate(array $row): Offer
    {
        $key = Rows::text($row['offer_key']);
        $items = [self::OFFERED => [], self::REQUESTED => []];

        foreach ($this->connection->fetchAllAssociative(\sprintf('SELECT side, item FROM %s WHERE offer_key = ? ORDER BY side, position', ExchangeTables::OFFER_ITEMS), [$key]) as $item) {
            $items[Rows::text($item['side'])][] = Rows::reference($item['item']);
        }

        $counters = Rows::nullableText($row['counters']);

        return new Offer(
            ExchangeKey::fromString($key),
            Rows::reference($row['proposer']),
            Rows::reference($row['recipient']),
            $items[self::OFFERED],
            $items[self::REQUESTED],
            Rows::integer($row['offered_points']),
            Rows::integer($row['requested_points']),
            Rows::text($row['currency']),
            Rows::date($row['made_at']),
            Rows::date($row['expires_at']),
            OfferStatus::from(Rows::text($row['status'])),
            Rows::nullableDate($row['closed_at']),
            null === $counters ? null : ExchangeKey::fromString($counters),
        );
    }
}
