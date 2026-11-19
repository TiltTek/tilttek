<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit\Drand;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Raffle\Drand\DrandChains;
use Tilttek\Raffle\Drand\DrandScheme;
use Tilttek\Raffle\Exception\InvalidRaffle;

#[CoversClass(DrandScheme::class)]
#[CoversClass(DrandChains::class)]
final class DrandSchemeTest extends TestCase
{
    public function testEachSchemeKnowsTheLengthOfItsSignaturesAndKeys(): void
    {
        $lengths = [];

        foreach (DrandScheme::cases() as $scheme) {
            $lengths[$scheme->value] = [$scheme->signatureLength(), $scheme->publicKeyLength(), $scheme->isChained()];
        }

        self::assertSame([
            'pedersen-bls-chained' => [192, 96, true],
            'pedersen-bls-unchained' => [192, 96, false],
            'bls-unchained-on-g1' => [96, 192, false],
            'bls-unchained-g1-rfc9380' => [96, 192, false],
        ], $lengths);
    }

    public function testTheMessageIsTheHashOfTheRoundPrecededByThePreviousSignatureWhenChained(): void
    {
        $previous = str_repeat('ab', 96);

        self::assertSame(hash('sha256', "\0\0\0\0\0\0\0\x07", true), DrandScheme::BlsUnchainedG1Rfc9380->message(7, null));
        self::assertSame(hash('sha256', "\0\0\0\0\0\0\0\x07", true), DrandScheme::PedersenBlsUnchained->message(7, $previous));
        self::assertSame(hash('sha256', hex2bin($previous)."\0\0\0\0\0\0\x01\x00", true), DrandScheme::PedersenBlsChained->message(256, $previous));
    }

    public function testAChainedMessageNeedsThePreviousSignature(): void
    {
        foreach ([null, str_repeat('ab', 95), str_repeat('ab', 97), str_repeat('AB', 96), str_repeat('ab', 95)."a\n"] as $previous) {
            try {
                DrandScheme::PedersenBlsChained->message(7, $previous);
                self::fail('A chained message was signed without its previous signature.');
            } catch (InvalidRaffle $invalid) {
                self::assertSame('A round of the scheme pedersen-bls-chained signs the previous signature, 192 hexadecimal digits.', $invalid->getMessage());
            }
        }
    }

    public function testThePublicChainsAndRelaysAreKnown(): void
    {
        $quicknet = DrandChains::quicknet();
        $mainnet = DrandChains::mainnet();

        self::assertSame(['52db9ba70e0cc0f6eaf7803dd07447a1f5477735fd3f661792ba94600c84e971', 1692803367, 3, 'bls-unchained-g1-rfc9380', null], [$quicknet->hash, $quicknet->genesis, $quicknet->period, $quicknet->scheme, $quicknet->publicKey]);
        self::assertSame(['8990e7a9aaed2ffed73dbd7092123d6f289930540d7651336225dc172e51b2ce', 1595431050, 30, 'pedersen-bls-chained', null], [$mainnet->hash, $mainnet->genesis, $mainnet->period, $mainnet->scheme, $mainnet->publicKey]);
        self::assertSame(['https://api.drand.sh', 'https://api2.drand.sh', 'https://api3.drand.sh', 'https://drand.cloudflare.com'], DrandChains::RELAYS);
    }

    public function testThePreviousSignatureIsCheckedFromItsFirstDigit(): void
    {
        $this->expectException(InvalidRaffle::class);

        DrandScheme::PedersenBlsChained->message(7, 'g'.str_repeat('a', 191));
    }
}
