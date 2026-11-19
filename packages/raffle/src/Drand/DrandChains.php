<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Drand;

use Tilttek\Raffle\Beacon\BeaconChain;

/**
 * The public chains of the League of Entropy, and the relays that serve them. Their public keys are read from the
 * relays, by `DrandBeacon::chain()`, and checked against the hash of the chain.
 */
final class DrandChains
{
    /** The HTTP relays of the League of Entropy, each run by a different operator. */
    public const array RELAYS = ['https://api.drand.sh', 'https://api2.drand.sh', 'https://api3.drand.sh', 'https://drand.cloudflare.com'];

    private function __construct()
    {
    }

    /**
     * quicknet: a round every 3 seconds since 2023-08-23, signatures on G1, each round signed alone.
     */
    public static function quicknet(): BeaconChain
    {
        return new BeaconChain('52db9ba70e0cc0f6eaf7803dd07447a1f5477735fd3f661792ba94600c84e971', 1692803367, 3, DrandScheme::BlsUnchainedG1Rfc9380->value);
    }

    /**
     * The first network: a round every 30 seconds since 2020-07-22, signatures on G2, each round chained to the one
     * before.
     */
    public static function mainnet(): BeaconChain
    {
        return new BeaconChain('8990e7a9aaed2ffed73dbd7092123d6f289930540d7651336225dc172e51b2ce', 1595431050, 30, DrandScheme::PedersenBlsChained->value);
    }
}
