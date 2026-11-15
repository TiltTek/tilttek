<?php

declare(strict_types=1);

namespace Tilttek\Sets\Event;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Throwable;

/**
 * Told by `Sets::deliverPending()` when the delivery of a holder's completions failed again, from the number of
 * failures in a row the `DeliveryBackoff` reports: the holder, those failures, the moment of the next attempt and what
 * the handler threw, for the application to log or alert someone.
 */
final readonly class DeliveryKeepsFailing
{
    public function __construct(
        public ResourceReference $holder,
        public int $failures,
        public DateTimeImmutable $nextAttemptAt,
        public Throwable $failure,
    ) {
    }
}
