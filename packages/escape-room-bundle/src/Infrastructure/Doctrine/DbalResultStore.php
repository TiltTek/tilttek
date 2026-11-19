<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Doctrine;

use DateTimeImmutable;
use DateTimeZone;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoom\RoomResult;
use Tilttek\EscapeRoomBundle\Application\Contract\ResultStore;

/**
 * The results in `tilttek_escape_room_result`, one row per game a team escaped; the moment of the escape to the second,
 * and its microseconds beside it, which order two teams that escaped within the same second.
 */
final readonly class DbalResultStore implements ResultStore
{
    private const array TYPES = ['escaped_at' => Types::DATETIME_UTC_IMMUTABLE, 'public' => Types::BOOLEAN];

    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function record(string $game, RoomResult $result): void
    {
        $row = [
            'room' => $result->room,
            'team' => $result->team->toString(),
            'session' => $result->session,
            'final_seconds' => $result->finalSeconds,
            'played_seconds' => $result->playedSeconds,
            'penalty_seconds' => $result->penaltySeconds,
            'hints' => $result->hints,
            'skips' => $result->skips,
            'escaped_at' => $result->escapedAt,
            'escaped_micro' => (int) $result->escapedAt->format('u'),
            'public' => $result->public,
        ];

        $this->connection->transactional(static function (Connection $connection) use ($game, $row): void {
            if (false === $connection->fetchOne('SELECT 1 FROM '.EscapeRoomTables::RESULTS.' WHERE game = ?', [$game])) {
                $connection->insert(EscapeRoomTables::RESULTS, ['game' => $game, ...$row], self::TYPES);
            } else {
                $connection->update(EscapeRoomTables::RESULTS, $row, ['game' => $game], self::TYPES);
            }
        });
    }

    #[Override]
    public function consent(string $game, bool $public): void
    {
        $this->connection->update(EscapeRoomTables::RESULTS, ['public' => $public], ['game' => $game], ['public' => Types::BOOLEAN]);
    }

    #[Override]
    public function shown(string $room, int $limit): array
    {
        $rows = $this->connection->createQueryBuilder()
            ->select('*')
            ->from(EscapeRoomTables::RESULTS)
            ->where('room = :room')
            ->andWhere('public = :public')
            ->orderBy('final_seconds')
            ->addOrderBy('hints')
            ->addOrderBy('escaped_at')
            ->addOrderBy('escaped_micro')
            ->addOrderBy('game')
            ->setParameter('room', $room)
            ->setParameter('public', true, Types::BOOLEAN)
            ->setMaxResults(max(1, $limit))
            ->fetchAllAssociative();

        return array_values(array_map($this->result(...), $rows));
    }

    #[Override]
    public function ofTeam(ResourceReference $team): array
    {
        $results = [];

        foreach ($this->connection->fetchAllAssociative('SELECT * FROM '.EscapeRoomTables::RESULTS.' WHERE team = ? ORDER BY game', [$team->toString()]) as $row) {
            $results[Rows::text($row['game'])] = $this->result($row);
        }

        return $results;
    }

    #[Override]
    public function eraseTeam(ResourceReference $team, ResourceReference $pseudonym): int
    {
        return (int) $this->connection->update(EscapeRoomTables::RESULTS, ['team' => $pseudonym->toString(), 'public' => false], ['team' => $team->toString()], ['public' => Types::BOOLEAN]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function result(array $row): RoomResult
    {
        $escaped = Rows::date($row['escaped_at']);
        $escaped = new DateTimeImmutable($escaped->format('Y-m-d H:i:s').'.'.str_pad((string) Rows::integer($row['escaped_micro']), 6, '0', \STR_PAD_LEFT), new DateTimeZone('UTC'));

        return new RoomResult(
            Rows::text($row['room']),
            ResourceReference::fromString(Rows::text($row['team'])),
            Rows::optionalText($row['session']),
            Rows::integer($row['final_seconds']),
            Rows::integer($row['played_seconds']),
            Rows::integer($row['penalty_seconds']),
            Rows::integer($row['hints']),
            Rows::integer($row['skips']),
            $escaped,
            Rows::flag($row['public']),
        );
    }
}
