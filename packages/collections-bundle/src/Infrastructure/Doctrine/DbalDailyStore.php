<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Override;
use Tilttek\Collections\Daily\DailyPick;
use Tilttek\Collections\Daily\DailyStore;
use Tilttek\Collections\Daily\PickOrigin;

/**
 * The calendar of the item of the day in `tilttek_collections_daily`, through a DBAL connection: one row per day,
 * replaced in a transaction. Days are written `Y-m-d`, so that their order is that of their text.
 */
final readonly class DbalDailyStore implements DailyStore
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function save(DailyPick $pick): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($pick): void {
            $connection->delete(CollectionsTables::DAILY, ['day' => $pick->day]);
            $connection->insert(CollectionsTables::DAILY, ['day' => $pick->day, 'item' => $pick->item, 'origin' => $pick->origin->value]);
        });
    }

    #[Override]
    public function find(string $day): ?DailyPick
    {
        return $this->read('WHERE day = ?', [$day])[0] ?? null;
    }

    #[Override]
    public function between(string $from, string $until): array
    {
        return $this->read('WHERE day >= ? AND day <= ?', [$from, $until]);
    }

    #[Override]
    public function remove(string $day): bool
    {
        return 0 < $this->connection->delete(CollectionsTables::DAILY, ['day' => $day]);
    }

    /**
     * @param list<mixed> $parameters
     *
     * @return list<DailyPick>
     */
    private function read(string $where, array $parameters): array
    {
        return array_map(
            static fn (array $row): DailyPick => new DailyPick(Rows::text($row['day']), Rows::text($row['item']), PickOrigin::from(Rows::text($row['origin']))),
            $this->connection->fetchAllAssociative(\sprintf('SELECT day, item, origin FROM %s %s ORDER BY day', CollectionsTables::DAILY, $where), $parameters),
        );
    }
}
