<?php

declare(strict_types=1);

namespace Tilttek\EscapeRoomBundle\Infrastructure\Scheduler;

/**
 * The message the schedule `tilttek_escape_room` sends: end the games whose countdown reached zero. It carries
 * nothing; the games due are read from the store when it is handled.
 */
final readonly class ExpireDue
{
}
