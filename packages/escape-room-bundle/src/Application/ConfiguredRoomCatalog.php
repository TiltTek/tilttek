<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application;

use Override;
use Tilttek\EscapeRoom\Room;
use Tilttek\EscapeRoomBundle\Application\Contract\RoomCatalog;
use Tilttek\PuzzleBuilder\Scenario;

/**
 * The rooms of the configuration, each read once, when the catalog is built: its title, duration, penalties, team sizes
 * and scenario, as `Scenario::fromArray()` reads it.
 */
final readonly class ConfiguredRoomCatalog implements RoomCatalog
{
    /** @var array<string, Room> */
    private array $rooms;

    /**
     * @param array<string, array{title: string, duration: int, hint_penalty: int, skip_penalty: int, min_players: int, max_players: int, scenario: array<array-key, mixed>}> $rooms
     */
    public function __construct(array $rooms = [])
    {
        $read = [];

        foreach ($rooms as $id => $room) {
            $read[$id] = new Room($id, $room['title'], Scenario::fromArray($room['scenario']), $room['duration'], $room['hint_penalty'], $room['skip_penalty'], $room['min_players'], $room['max_players']);
        }

        $this->rooms = $read;
    }

    #[Override]
    public function room(string $id): ?Room
    {
        return $this->rooms[$id] ?? null;
    }

    #[Override]
    public function ids(): array
    {
        return array_map(strval(...), array_keys($this->rooms));
    }
}
