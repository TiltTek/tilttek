<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Contract;

use Tilttek\Raffle\Beacon\Beacon;
use Tilttek\Raffle\Beacon\BeaconChain;

/**
 * Verifies the signature of a beacon round against the public key of its chain. For drand, a BLS signature on the
 * curve BLS12-381, over the message `Drand\DrandScheme::message()` gives.
 */
interface BeaconSignatureVerifier
{
    /**
     * @param BeaconChain $chain the chain, its public key read
     */
    public function verify(BeaconChain $chain, Beacon $beacon): bool;
}
