<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit\Fixture;

use Override;
use Tilttek\Raffle\Beacon\Beacon;
use Tilttek\Raffle\Beacon\BeaconChain;
use Tilttek\Raffle\Beacon\BeaconVerification;
use Tilttek\Raffle\Contract\RandomnessBeacon;

/**
 * A beacon of tests: a chain of 3-second rounds since 2026-01-01, each round signed by the hash of its number, or the
 * round given instead.
 */
final class FakeBeacon implements RandomnessBeacon
{
    /** @var list<int> */
    public array $fetched = [];

    public ?Beacon $instead = null;

    #[Override]
    public function chain(): BeaconChain
    {
        return self::chainOfTests();
    }

    #[Override]
    public function fetch(int $round): Beacon
    {
        $this->fetched[] = $round;

        return $this->instead ?? self::round($round);
    }

    public static function chainOfTests(): BeaconChain
    {
        return new BeaconChain(str_repeat('ab', 32), 1767225600, 3, 'test-scheme', str_repeat('cd', 48));
    }

    public static function round(int $round, BeaconVerification $verification = BeaconVerification::RelayQuorum): Beacon
    {
        $signature = hash('sha384', 'round '.$round);

        return new Beacon($round, hash('sha256', (string) hex2bin($signature)), $signature, null, $verification);
    }
}
