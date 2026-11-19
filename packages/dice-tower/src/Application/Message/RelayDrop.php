<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Application\Message;

/**
 * Asks for an entry of a tower to be relayed: the table and the position only; the handler reads the entry from the
 * log.
 */
final readonly class RelayDrop
{
    public function __construct(
        public string $table,
        public int $position,
    ) {
    }
}
