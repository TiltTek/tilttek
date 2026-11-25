<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use Doctrine\DBAL\ArrayParameterType;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoomBundle\Application\Contract\GameStore;
use UnexpectedValueException;

/**
 * The games in `tilttek_escape_room_game`: each as the JSON of the plain values `Game::toArray()` writes, in UTC, with
 * its room, team and session beside it, and the moment its countdown reaches zero.
 */
final readonly class DbalGameStore implements GameStore
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function save(string $id, array $values, ?DateTimeImmutable $deadline): void
    {
        $row = [
            'room' => $values['room'],
            'team' => $values['team'],
            'session' => $values['session'],
            'deadline' => $deadline,
            'state' => json_encode($values, \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
        ];
        $types = ['deadline' => Types::DATETIME_UTC_IMMUTABLE];

        $this->connection->transactional(static function (Connection $connection) use ($id, $row, $types): void {
            if (false === $connection->fetchOne('SELECT 1 FROM '.EscapeRoomTables::GAMES.' WHERE id = ?', [$id])) {
                $connection->insert(EscapeRoomTables::GAMES, ['id' => $id, ...$row], $types);
            } else {
                $connection->update(EscapeRoomTables::GAMES, $row, ['id' => $id], $types);
            }
        });
    }

    #[Override]
    public function find(string $id): ?array
    {
        $state = $this->connection->fetchOne('SELECT state FROM '.EscapeRoomTables::GAMES.' WHERE id = ?', [$id]);

        return false === $state ? null : $this->values($state);
    }

    #[Override]
    public function due(DateTimeImmutable $at, int $limit): array
    {
        $ids = $this->connection->createQueryBuilder()
            ->select('id')
            ->from(EscapeRoomTables::GAMES)
            ->where('deadline IS NOT NULL')
            ->andWhere('deadline <= :at')
            ->orderBy('deadline')
            ->addOrderBy('id')
            ->setParameter('at', $at, Types::DATETIME_UTC_IMMUTABLE)
            ->setMaxResults(max(1, $limit))
            ->fetchFirstColumn();

        return array_values(array_map(Rows::text(...), $ids));
    }

    #[Override]
    public function ofTeam(ResourceReference $team): array
    {
        $games = [];

        foreach ($this->connection->fetchAllAssociative('SELECT id, state FROM '.EscapeRoomTables::GAMES.' WHERE team = ? ORDER BY id', [$team->toString()]) as $row) {
            $games[Rows::text($row['id'])] = $this->values($row['state']);
        }

        return $games;
    }

    #[Override]
    public function eraseTeam(ResourceReference $team, ResourceReference $pseudonym): int
    {
        return $this->connection->transactional(function (Connection $connection) use ($team, $pseudonym): int {
            $erased = 0;

            foreach ($this->ofTeam($team) as $id => $values) {
                $connection->update(EscapeRoomTables::GAMES, [
                    'team' => $pseudonym->toString(),
                    'state' => json_encode([...$values, 'team' => $pseudonym->toString(), 'public' => false], \JSON_THROW_ON_ERROR | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE),
                ], ['id' => $id]);
                ++$erased;
            }

            return $erased;
        });
    }

    #[Override]
    public function forget(string ...$ids): int
    {
        $deleted = 0;

        foreach (array_chunk(array_values(array_unique($ids)), 500) as $chunk) {
            $deleted += (int) $this->connection->executeStatement('DELETE FROM '.EscapeRoomTables::GAMES.' WHERE id IN (?)', [$chunk], [ArrayParameterType::STRING]);
        }

        return $deleted;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function values(mixed $state): array
    {
        $values = json_decode(Rows::text($state), true, 32, \JSON_THROW_ON_ERROR);

        if (!\is_array($values)) {
            throw new UnexpectedValueException('The state of a game is an object of values.');
        }

        return $values;
    }
}
