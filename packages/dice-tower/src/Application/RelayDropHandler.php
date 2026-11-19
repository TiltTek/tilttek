<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Application;

use Phil\Resource\ResourceReference;
use Tilttek\DiceTower\Application\Message\RelayDrop;

/**
 * Reads the entry a `RelayDrop` names from the log and hands it to the relays; an entry erased since is skipped.
 */
final readonly class RelayDropHandler
{
    public function __construct(
        private DiceTower $tower,
        private TowerRelays $relays,
    ) {
    }

    public function __invoke(RelayDrop $message): void
    {
        $entry = $this->tower->entry(ResourceReference::fromString($message->table), $message->position);

        if ($entry instanceof TowerEntry) {
            $this->relays->relay($entry);
        }
    }
}
