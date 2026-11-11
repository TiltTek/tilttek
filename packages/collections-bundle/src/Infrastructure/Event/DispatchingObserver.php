<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Event;

use Override;
use Psr\EventDispatcher\EventDispatcherInterface;
use Tilttek\Collections\CollectionObserver;
use Tilttek\Collections\Copy;
use Tilttek\Collections\SetCompleted;

/**
 * Turns what the collections decide into events of the application's event dispatcher: `CopyGranted` around the copy
 * given, and the library's `SetCompleted` as it is, with the rewards to give.
 */
final readonly class DispatchingObserver implements CollectionObserver
{
    public function __construct(
        private EventDispatcherInterface $dispatcher,
    ) {
    }

    #[Override]
    public function granted(Copy $copy): void
    {
        $this->dispatcher->dispatch(new CopyGranted($copy));
    }

    #[Override]
    public function setCompleted(SetCompleted $completed): void
    {
        $this->dispatcher->dispatch($completed);
    }
}
