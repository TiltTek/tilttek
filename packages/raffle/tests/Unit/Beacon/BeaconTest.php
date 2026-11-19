<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit\Beacon;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Raffle\Beacon\Beacon;
use Tilttek\Raffle\Beacon\BeaconChain;
use Tilttek\Raffle\Beacon\BeaconVerification;
use Tilttek\Raffle\Exception\InvalidRaffle;

#[CoversClass(BeaconChain::class)]
#[CoversClass(Beacon::class)]
final class BeaconTest extends TestCase
{
    public function testARoundIsPublishedAtTheGenesisPlusItsPeriods(): void
    {
        $chain = new BeaconChain(str_repeat('ab', 32), 1000, 3, 'quick-scheme');

        self::assertEquals(new DateTimeImmutable('@1000'), $chain->timeOf(1));
        self::assertEquals(new DateTimeImmutable('@1006'), $chain->timeOf(3));
        self::assertSame(1, $chain->firstRoundAfter(new DateTimeImmutable('@999')));
        self::assertSame(1, $chain->firstRoundAfter(new DateTimeImmutable('@997')));
        self::assertSame(2, $chain->firstRoundAfter(new DateTimeImmutable('@1000')));
        self::assertSame(2, $chain->firstRoundAfter(new DateTimeImmutable('@1002')));
        self::assertSame(3, $chain->firstRoundAfter(new DateTimeImmutable('@1003')));
    }

    public function testAChainIsTheSameByItsHashGenesisPeriodAndScheme(): void
    {
        $chain = new BeaconChain(str_repeat('ab', 32), 1000, 3, 'quick-scheme');
        $keyed = $chain->withPublicKey('cd01');

        self::assertSame('cd01', $keyed->publicKey);
        self::assertNull($chain->publicKey);
        self::assertTrue($chain->isSame($keyed));
        self::assertFalse($chain->isSame(new BeaconChain(str_repeat('ac', 32), 1000, 3, 'quick-scheme')));
        self::assertFalse($chain->isSame(new BeaconChain(str_repeat('ab', 32), 1001, 3, 'quick-scheme')));
        self::assertFalse($chain->isSame(new BeaconChain(str_repeat('ab', 32), 1000, 4, 'quick-scheme')));
        self::assertFalse($chain->isSame(new BeaconChain(str_repeat('ab', 32), 1000, 3, 'other-scheme')));
    }

    public function testTheValuesOfAChainAreBounded(): void
    {
        self::assertSame(3600, new BeaconChain(str_repeat('ab', 32), 1, 3600, str_repeat('a', 64), str_repeat('f', 512))->period);

        $bad = [
            [str_repeat('ab', 31), 1, 3, 's', null, 'The chain hash is 64'],
            [str_repeat('ab', 32), 0, 3, 's', null, 'publishes a round every 1 to 3600 seconds'],
            [str_repeat('ab', 32), 1, 0, 's', null, 'publishes a round every 1 to 3600 seconds'],
            [str_repeat('ab', 32), 1, 3601, 's', null, 'publishes a round every 1 to 3600 seconds'],
            [str_repeat('ab', 32), 1, 3, 'S', null, 'The scheme of a beacon chain'],
            [str_repeat('ab', 32), 1, 3, str_repeat('a', 65), null, 'The scheme of a beacon chain'],
            [str_repeat('ab', 32), 1, 3, "s\n", null, 'The scheme of a beacon chain'],
            [str_repeat('ab', 32), 1, 3, 's', 'a', 'The public key of a beacon chain'],
            [str_repeat('ab', 32), 1, 3, 's', str_repeat('f', 513), 'The public key of a beacon chain'],
            [str_repeat('ab', 32), 1, 3, 's', "ff\n", 'The public key of a beacon chain'],
        ];

        foreach ($bad as [$hash, $genesis, $period, $scheme, $key, $message]) {
            try {
                new BeaconChain($hash, $genesis, $period, $scheme, $key);
                self::fail('A chain out of range was taken: '.$message);
            } catch (InvalidRaffle $invalid) {
                self::assertStringContainsString($message, $invalid->getMessage());
            }
        }
    }

    public function testARoundIsOneTo2Power40(): void
    {
        self::assertSame(1, BeaconChain::round(1));
        self::assertSame(1 << 40, BeaconChain::round(BeaconChain::MAX_ROUND));

        foreach ([0, (1 << 40) + 1] as $round) {
            try {
                BeaconChain::round($round);
                self::fail('Round '.$round.' was taken.');
            } catch (InvalidRaffle $invalid) {
                self::assertSame('A beacon round is 1 to 1099511627776.', $invalid->getMessage());
            }
        }

        $this->expectException(InvalidRaffle::class);
        new BeaconChain(str_repeat('ab', 32), 1, 3, 's')->timeOf(0);
    }

    public function testTheRandomnessOfARoundIsTheHashOfItsSignature(): void
    {
        $signature = str_repeat('a1', 48);
        $beacon = new Beacon(7, hash('sha256', (string) hex2bin($signature)), $signature, str_repeat('b2', 96), BeaconVerification::Signature);

        self::assertSame(7, $beacon->round);
        self::assertSame(str_repeat('b2', 96), $beacon->previousSignature);
        self::assertSame(BeaconVerification::Signature, $beacon->verification);
        self::assertSame(512, Beacon::MAX_SIGNATURE);
        self::assertSame(str_repeat('ab', 256), new Beacon(1, hash('sha256', (string) hex2bin(str_repeat('ab', 256))), str_repeat('ab', 256), null, BeaconVerification::RelayQuorum)->signature);
    }

    public function testARoundThatDoesNotCheckIsRefused(): void
    {
        $signature = str_repeat('a1', 48);
        $randomness = hash('sha256', (string) hex2bin($signature));
        $bad = [
            [0, $randomness, $signature, null, 'A beacon round is 1'],
            [1, str_repeat('0', 64), $signature, null, 'is the SHA-256 of its signature'],
            [1, 'short', $signature, null, 'The beacon randomness is 64'],
            [1, $randomness, 'abc', null, 'A beacon signature is 2 to 512'],
            [1, $randomness, '', null, 'A beacon signature is 2 to 512'],
            [1, $randomness, str_repeat('ab', 257), null, 'A beacon signature is 2 to 512'],
            [1, $randomness, $signature."\n", null, 'A beacon signature is 2 to 512'],
            [1, $randomness, $signature, 'ABCD', 'A beacon signature is 2 to 512'],
        ];

        foreach ($bad as [$round, $random, $sig, $previous, $message]) {
            try {
                new Beacon($round, $random, $sig, $previous, BeaconVerification::RelayQuorum);
                self::fail('A round that does not check was taken: '.$message);
            } catch (InvalidRaffle $invalid) {
                self::assertStringContainsString($message, $invalid->getMessage());
            }
        }
    }

    public function testAChainMayPublishARoundEverySecond(): void
    {
        self::assertSame(1, new BeaconChain(str_repeat('ab', 32), 1, 1, 's')->period);
    }
}
