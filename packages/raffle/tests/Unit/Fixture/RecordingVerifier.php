<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit\Fixture;

use Override;
use Tilttek\Raffle\Beacon\Beacon;
use Tilttek\Raffle\Beacon\BeaconChain;
use Tilttek\Raffle\Contract\BeaconSignatureVerifier;

/**
 * A signature verifier of tests: it answers as it was told, for a chain whose public key is read, and notes what it was
 * asked.
 */
final class RecordingVerifier implements BeaconSignatureVerifier
{
    /** @var list<array{0: BeaconChain, 1: Beacon}> */
    public array $verified = [];

    public function __construct(private readonly bool $verifies)
    {
    }

    #[Override]
    public function verify(BeaconChain $chain, Beacon $beacon): bool
    {
        $this->verified[] = [$chain, $beacon];

        return $this->verifies && null !== $chain->publicKey;
    }
}
