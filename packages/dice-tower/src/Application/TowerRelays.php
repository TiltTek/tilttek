<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Application;

use Psr\Log\LoggerInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Throwable;
use Tilttek\DiceTower\Application\Contract\TowerRelay;
use Tilttek\DiceTower\Application\Message\RelayDrop;

/**
 * Hands each new entry to the relays: through a message bus when one is given, which carries the table and the
 * position only, or at once. A relay that fails is logged and the others still receive the entry; with no relay,
 * nothing is sent.
 */
final readonly class TowerRelays
{
    /**
     * @var list<TowerRelay>
     */
    private array $relays;

    /**
     * @param iterable<TowerRelay> $relays
     */
    public function __construct(
        iterable $relays = [],
        private ?MessageBusInterface $bus = null,
        private ?LoggerInterface $logger = null,
    ) {
        $list = [];

        foreach ($relays as $relay) {
            $list[] = $relay;
        }

        $this->relays = $list;
    }

    public function dispatch(TowerEntry $entry): void
    {
        if ([] === $this->relays) {
            return;
        }

        if ($this->bus instanceof MessageBusInterface) {
            $this->bus->dispatch(new RelayDrop($entry->table, $entry->position));

            return;
        }

        $this->relay($entry);
    }

    public function relay(TowerEntry $entry): void
    {
        foreach ($this->relays as $relay) {
            try {
                $relay->relay($entry);
            } catch (Throwable $throwable) {
                $this->logger?->warning('The entry {position} of the tower of "{table}" could not be relayed by {relay}: {message}', [
                    'position' => $entry->position,
                    'table' => $entry->table,
                    'relay' => $relay::class,
                    'message' => $throwable->getMessage(),
                    'exception' => $throwable,
                ]);
            }
        }
    }
}
