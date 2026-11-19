<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Exception;

use RuntimeException;
use Tilttek\Raffle\Beacon\RelayReport;

/**
 * The beacon gave no answer to rely on: the round is not published yet, or too few relays answered. The draw is tried
 * again later. `$report` says what each relay gave, when the beacon reads relays.
 */
final class BeaconUnavailable extends RuntimeException implements RaffleException
{
    public private(set) ?RelayReport $report = null;

    public static function quorum(string $what, int $answers, int $quorum, ?RelayReport $report = null): self
    {
        $unavailable = new self(\sprintf('The beacon gave %d answer(s) for %s, %d needed: the round is not published yet, or the relays are unreachable.', $answers, $what, $quorum));
        $unavailable->report = $report;

        return $unavailable;
    }
}
