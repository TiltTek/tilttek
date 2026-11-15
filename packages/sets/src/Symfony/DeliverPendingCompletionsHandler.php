<?php

declare(strict_types=1);

namespace Tilttek\Sets\Symfony;

use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Tilttek\Sets\Exception\DeliveryFailed;
use Tilttek\Sets\Exception\UnsupportedStore;
use Tilttek\Sets\Sets;

/**
 * Handles `DeliverPendingCompletions`: tells the undelivered completions of the holders waiting the longest. When a
 * handler of the events throws, the others are told all the same and `DeliveryFailed` reaches the worker, which logs
 * it; the next message tells the rest again.
 */
#[AsMessageHandler]
final readonly class DeliverPendingCompletionsHandler
{
    public function __construct(
        private Sets $sets,
    ) {
    }

    /**
     * @return int how many completions were delivered
     *
     * @throws UnsupportedStore when the completion store cannot list the holders with undelivered completions
     * @throws DeliveryFailed   when a handler of the events threw for one holder or more
     */
    public function __invoke(DeliverPendingCompletions $message): int
    {
        return $this->sets->deliverPending($message->limit);
    }
}
