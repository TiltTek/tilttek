<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Event;

use Tilttek\Collections\Copy;

/**
 * A member was given a copy — the item of the day, a pack, a reward, a purchase —: `#[AsEventListener] public function
 * __invoke(CopyGranted $event)`. A set completed by it is told just after, by the library's `SetCompleted`, with the
 * rewards the set declares.
 */
final readonly class CopyGranted
{
    public function __construct(
        public Copy $copy,
    ) {
    }
}
