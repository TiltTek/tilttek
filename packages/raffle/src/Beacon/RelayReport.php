<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Beacon;

/**
 * What every relay gave to one read of a beacon — the information of a chain or a round —, in the order of the relays
 * configured, with the quorum asked for and whether the read was kept.
 *
 * A read is kept when no relay gave an answer that does not check, every answer that checks gives the same signature,
 * and at least the quorum of relays gave one. A relay that fails without contradicting the others is reported, not held
 * against the read: the quorum is there to bear it.
 */
final readonly class RelayReport
{
    /**
     * @param array<string, RelayOutcome> $outcomes the outcome of each relay, by its address, in the order configured
     */
    public function __construct(
        public string $what,
        public int $quorum,
        public array $outcomes,
        public bool $kept,
    ) {
    }

    /**
     * How many relays gave an answer that checks.
     */
    public function answers(): int
    {
        return \count(array_filter($this->outcomes, static fn (RelayOutcome $outcome): bool => $outcome->answered()));
    }

    /**
     * The relays that gave no answer that checks or that contradicted the others, by address, with their outcome.
     *
     * @return array<string, RelayOutcome>
     */
    public function faults(): array
    {
        return array_filter($this->outcomes, static fn (RelayOutcome $outcome): bool => RelayOutcome::Agreed !== $outcome);
    }
}
