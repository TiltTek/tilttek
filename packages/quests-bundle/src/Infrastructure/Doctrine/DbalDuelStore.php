<?php

declare(strict_types=1);

namespace Tilttek\QuestsBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\ParameterType;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\Duel\DuelStatus;
use Tilttek\Quests\Store\DuelStore;

/**
 * The duels in `tilttek_quests_duel`, through a DBAL connection: a duel's row replaced in one transaction.
 */
final readonly class DbalDuelStore implements DuelStore
{
    private const string COLUMNS = 'duel_key, quest, challenger, opponent, offered_at, status, accepted_at, ends_at, winner, decided_at';

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function save(Duel $duel): void
    {
        $this->connection->transactional(static function (Connection $connection) use ($duel): void {
            $connection->delete(QuestsTables::DUELS, ['duel_key' => $duel->key]);
            $connection->insert(QuestsTables::DUELS, [
                'duel_key' => $duel->key,
                'quest' => $duel->quest,
                'challenger' => $duel->challenger->toString(),
                'opponent' => $duel->opponent->toString(),
                'offered_at' => Rows::utc($duel->offeredAt),
                'status' => $duel->status->value,
                'accepted_at' => self::utc($duel->acceptedAt),
                'ends_at' => self::utc($duel->endsAt),
                'winner' => $duel->winner?->toString(),
                'decided_at' => self::utc($duel->decidedAt),
            ], [
                ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, ParameterType::STRING, Types::DATETIME_IMMUTABLE,
                ParameterType::STRING, Types::DATETIME_IMMUTABLE, Types::DATETIME_IMMUTABLE, ParameterType::STRING, Types::DATETIME_IMMUTABLE,
            ]);
        });
    }

    #[Override]
    public function find(string $key): ?Duel
    {
        return $this->read('WHERE duel_key = ?', [$key])[0] ?? null;
    }

    #[Override]
    public function accepted(string $quest, ResourceReference $member): array
    {
        return $this->read('WHERE status = ? AND quest = ? AND (challenger = ? OR opponent = ?) ORDER BY duel_key', [DuelStatus::Accepted->value, $quest, $member->toString(), $member->toString()]);
    }

    #[Override]
    public function ofMember(ResourceReference $member): array
    {
        return $this->read('WHERE challenger = ? OR opponent = ? ORDER BY offered_at, duel_key', [$member->toString(), $member->toString()]);
    }

    #[Override]
    public function due(DateTimeImmutable $offeredBefore, DateTimeImmutable $now): array
    {
        return $this->read(
            'WHERE (status = ? AND offered_at <= ?) OR (status = ? AND ends_at IS NOT NULL AND ends_at <= ?) ORDER BY duel_key',
            [DuelStatus::Offered->value, Rows::utc($offeredBefore), DuelStatus::Accepted->value, Rows::utc($now)],
            [ParameterType::STRING, Types::DATETIME_IMMUTABLE, ParameterType::STRING, Types::DATETIME_IMMUTABLE],
        );
    }

    #[Override]
    public function reopen(?DateTimeImmutable $since): array
    {
        $won = $since instanceof DateTimeImmutable
            ? $this->read('WHERE status = ? AND decided_at >= ? ORDER BY duel_key', [DuelStatus::Won->value, Rows::utc($since)], [ParameterType::STRING, Types::DATETIME_IMMUTABLE])
            : $this->read('WHERE status = ? ORDER BY duel_key', [DuelStatus::Won->value]);

        foreach ($won as $duel) {
            $this->save($duel->reopened());
        }

        return $won;
    }

    #[Override]
    public function eraseMember(ResourceReference $member): int
    {
        return Rows::integer($this->connection->executeStatement(\sprintf('DELETE FROM %s WHERE challenger = ? OR opponent = ?', QuestsTables::DUELS), [$member->toString(), $member->toString()]));
    }

    private static function utc(?DateTimeImmutable $date): ?DateTimeImmutable
    {
        return $date instanceof DateTimeImmutable ? Rows::utc($date) : null;
    }

    /**
     * @param list<mixed>                $parameters
     * @param list<ParameterType|string> $types
     *
     * @return list<Duel>
     */
    private function read(string $where, array $parameters, array $types = []): array
    {
        return array_map(
            static fn (array $row): Duel => new Duel(
                Rows::text($row['duel_key']),
                Rows::text($row['quest']),
                Rows::reference($row['challenger']),
                Rows::reference($row['opponent']),
                Rows::date($row['offered_at']),
                DuelStatus::from(Rows::text($row['status'])),
                Rows::optionalDate($row['accepted_at']),
                Rows::optionalDate($row['ends_at']),
                Rows::optionalReference($row['winner']),
                Rows::optionalDate($row['decided_at']),
            ),
            $this->connection->fetchAllAssociative(\sprintf('SELECT %s FROM %s %s', self::COLUMNS, QuestsTables::DUELS, $where), $parameters, $types),
        );
    }
}
