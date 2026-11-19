<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Event;

use Tilttek\Raffle\Beacon\RelayReport;

/**
 * Dispatched by `Drand\DrandBeacon` after each read of its relays, kept or not: a monitor counts the relays that fail
 * or contradict the others before the quorum is lost. It carries the chain's hash and the report, which names the
 * relays configured, never a person.
 */
final readonly class BeaconRelaysRead
{
    public function __construct(
        public string $chain,
        public RelayReport $report,
    ) {
    }
}
