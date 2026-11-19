<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Beacon;

/**
 * How a beacon round was checked before a draw used it.
 */
enum BeaconVerification: string
{
    /** Its signature was verified against the chain's public key. */
    case Signature = 'signature';

    /**
     * Several independent relays gave the same signature, and its randomness is the SHA-256 of that signature; the
     * signature itself is left to the player, with the beacon's own tools.
     */
    case RelayQuorum = 'relay-quorum';
}
