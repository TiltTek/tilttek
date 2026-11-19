<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Exception;

use RuntimeException;
use Tilttek\Raffle\Beacon\RelayReport;

/**
 * A beacon answer that does not check: malformed, for another round or chain, whose randomness is not the hash of its
 * signature, whose signature does not verify, or that another relay contradicts. Nothing is drawn from it. `$report`
 * says what each relay gave, when the refusal comes from a read of the relays.
 */
final class BeaconRefused extends RuntimeException implements RaffleException
{
    public private(set) ?RelayReport $report = null;

    public static function because(string $reason, ?RelayReport $report = null): self
    {
        $refused = new self($reason);
        $refused->report = $report;

        return $refused;
    }
}
