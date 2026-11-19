<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Application\Contract;

use Tilttek\EscapeRoom\Room;

/**
 * The rooms of the application: those of the configuration, or the application's own.
 */
interface RoomCatalog
{
    public function room(string $id): ?Room;

    /**
     * @return list<string> the identifiers of the rooms, in the order the catalog keeps them
     */
    public function ids(): array;
}
