<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Doctrine;

use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Override;
use Phil\Resource\ResourceReference;
use Tilttek\EscapeRoomBundle\Application\Contract\ScreenStore;
use Tilttek\EscapeRoomBundle\Application\Screen;
use Tilttek\EscapeRoomBundle\Application\ScreenKind;

/**
 * The hint screens in `tilttek_escape_room_screen`, one row per game: what the host showed last.
 */
final readonly class DbalScreenStore implements ScreenStore
{
    public function __construct(
        private Connection $connection,
    ) {
    }

    #[Override]
    public function show(string $game, Screen $screen): void
    {
        $row = [
            'kind' => $screen->kind->value,
            'host' => $screen->host->toString(),
            'shown_at' => $screen->at,
            'puzzle' => $screen->puzzle,
            'tier' => $screen->tier,
            'penalty' => $screen->penalty,
            'message' => $screen->message,
        ];
        $types = ['shown_at' => Types::DATETIME_UTC_IMMUTABLE];

        $this->connection->transactional(static function (Connection $connection) use ($game, $row, $types): void {
            if (false === $connection->fetchOne('SELECT 1 FROM '.EscapeRoomTables::SCREENS.' WHERE game = ?', [$game])) {
                $connection->insert(EscapeRoomTables::SCREENS, ['game' => $game, ...$row], $types);
            } else {
                $connection->update(EscapeRoomTables::SCREENS, $row, ['game' => $game], $types);
            }
        });
    }

    #[Override]
    public function screen(string $game): ?Screen
    {
        $row = $this->connection->fetchAssociative('SELECT * FROM '.EscapeRoomTables::SCREENS.' WHERE game = ?', [$game]);

        return false === $row ? null : $this->screenOf($row);
    }

    #[Override]
    public function ofHost(ResourceReference $host): array
    {
        $screens = [];

        foreach ($this->connection->fetchAllAssociative('SELECT * FROM '.EscapeRoomTables::SCREENS.' WHERE host = ? ORDER BY game', [$host->toString()]) as $row) {
            $screens[Rows::text($row['game'])] = $this->screenOf($row);
        }

        return $screens;
    }

    #[Override]
    public function eraseHost(ResourceReference $host, ResourceReference $pseudonym): int
    {
        return (int) $this->connection->update(EscapeRoomTables::SCREENS, ['host' => $pseudonym->toString()], ['host' => $host->toString()]);
    }

    /**
     * @param array<string, mixed> $row
     */
    private function screenOf(array $row): Screen
    {
        return new Screen(
            ScreenKind::from(Rows::text($row['kind'])),
            ResourceReference::fromString(Rows::text($row['host'])),
            Rows::date($row['shown_at']),
            Rows::optionalText($row['puzzle']),
            null === $row['tier'] ? null : Rows::integer($row['tier']),
            Rows::integer($row['penalty']),
            Rows::optionalText($row['message']),
        );
    }
}
