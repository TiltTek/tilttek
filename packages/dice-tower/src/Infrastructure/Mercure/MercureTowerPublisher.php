<?php

declare(strict_types=1);

namespace Tilttek\DiceTower\Infrastructure\Mercure;

use Override;
use Phil\Resource\ResourceReference;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mercure\Exception\RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Tilttek\DiceTower\Application\Contract\TowerPublisher;
use Tilttek\DiceTower\Application\TowerEntry;
use Tilttek\DiceTower\Application\TowerTopics;

/**
 * Pushes each new entry to the private topic of its table on a Mercure hub, as the JSON of `TowerEntry::toArray()`:
 * only the subscribers the application authorized for that topic receive it. A hub that cannot be reached is logged
 * and does not fail the roll: the viewers read the entry from the log when they connect again. Without a hub, nothing
 * is pushed.
 */
final readonly class MercureTowerPublisher implements TowerPublisher
{
    public function __construct(
        private ?HubInterface $hub,
        private TowerTopics $topics,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function publish(TowerEntry $entry): void
    {
        if (!$this->hub instanceof HubInterface) {
            return;
        }

        try {
            $this->hub->publish(new Update(
                $this->topics->topic(ResourceReference::fromString($entry->table)),
                json_encode($entry->toArray(), \JSON_THROW_ON_ERROR),
                true,
            ));
        } catch (RuntimeException $runtimeException) {
            $this->logger?->warning('The entry {position} of the tower of "{table}" could not be pushed to Mercure: {message}', [
                'position' => $entry->position,
                'table' => $entry->table,
                'message' => $runtimeException->getMessage(),
                'exception' => $runtimeException,
            ]);
        }
    }
}
