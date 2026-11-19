<?php

declare(strict_types=1);

namespace Tilttek\TableSafetyBundle\Infrastructure\Mercure;

use Override;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Mercure\HubInterface;
use Symfony\Component\Mercure\Update;
use Tilttek\TableSafety\Contract\SignalObserver;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\Signal\Signal;
use Tilttek\TableSafetyBundle\Application\SignalTopics;

/**
 * Pushes each signal, raised or acknowledged, to the private topic of its audience on a Mercure hub, as the JSON of
 * `Signal::toArray()`: identifiers, the kind and the moments — an author only on a named signal. A signal of the whole
 * table goes to the table's topic, which the game master reads too; a discreet one to the game master's topic alone.
 * A hub that cannot be reached — any `\RuntimeException`, that of a hub by URL as that of the hub built into the server —
 * is logged and does not fail the signal: the screens read it from the store when they connect again.
 */
final readonly class MercureSignalObserver implements SignalObserver
{
    public function __construct(
        private HubInterface $hub,
        private SignalTopics $topics,
        private ?LoggerInterface $logger = null,
    ) {
    }

    #[Override]
    public function raised(Signal $signal): void
    {
        $this->push($signal);
    }

    #[Override]
    public function acknowledged(Signal $signal): void
    {
        $this->push($signal);
    }

    private function push(Signal $signal): void
    {
        try {
            $this->hub->publish(new Update(
                Audience::Table === $signal->audience ? $this->topics->topic($signal->table, Audience::Table) : $this->topics->topic($signal->table, Audience::GameMaster),
                json_encode($signal->toArray(), \JSON_THROW_ON_ERROR),
                true,
            ));
        } catch (RuntimeException $runtimeException) {
            $this->logger?->warning('The signal {id} of "{table}" could not be pushed to Mercure: {message}', [
                'id' => $signal->id,
                'table' => $signal->table->toString(),
                'message' => $runtimeException->getMessage(),
                'exception' => $runtimeException,
            ]);
        }
    }
}
