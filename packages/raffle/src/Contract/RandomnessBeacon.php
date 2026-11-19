<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Contract;

use Tilttek\Raffle\Beacon\Beacon;
use Tilttek\Raffle\Beacon\BeaconChain;
use Tilttek\Raffle\Exception\BeaconRefused;
use Tilttek\Raffle\Exception\BeaconUnavailable;
use Tilttek\Raffle\Exception\InvalidRaffle;

/**
 * A public randomness beacon: a source of rounds published at known times, which nobody — the organiser of a raffle
 * included — knows before their time. `Drand\DrandBeacon` reads drand.
 */
interface RandomnessBeacon
{
    /**
     * The chain the rounds come from, its public key read when the beacon gives it.
     *
     * @throws BeaconUnavailable when the beacon cannot be read
     * @throws BeaconRefused     when the beacon describes another chain
     */
    public function chain(): BeaconChain;

    /**
     * The round, checked: its randomness is the hash of its signature, and the signature verified or confirmed.
     *
     * @throws InvalidRaffle     when the round is not 1 to 2⁴⁰
     * @throws BeaconUnavailable when the round is not published yet, or the beacon cannot be read
     * @throws BeaconRefused     when an answer does not check
     */
    public function fetch(int $round): Beacon;
}
