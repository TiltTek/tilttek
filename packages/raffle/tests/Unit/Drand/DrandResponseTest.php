<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit\Drand;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Raffle\Beacon\BeaconChain;
use Tilttek\Raffle\Beacon\BeaconVerification;
use Tilttek\Raffle\Drand\DrandChains;
use Tilttek\Raffle\Drand\DrandResponse;
use Tilttek\Raffle\Drand\DrandScheme;
use Tilttek\Raffle\Exception\BeaconRefused;

#[CoversClass(DrandResponse::class)]
#[CoversClass(BeaconRefused::class)]
final class DrandResponseTest extends TestCase
{
    public function testTheInformationOfTheChainExpectedGivesItsPublicKey(): void
    {
        $chain = DrandResponse::info(self::info(), DrandChains::quicknet());

        self::assertSame(str_repeat('a0', 96), $chain->publicKey);
        self::assertTrue($chain->isSame(DrandChains::quicknet()));
        self::assertSame(str_repeat('b0', 48), DrandResponse::info(self::info(['public_key' => str_repeat('b0', 48), 'hash' => DrandChains::mainnet()->hash, 'genesis_time' => 1595431050, 'period' => 30, 'schemeID' => 'pedersen-bls-chained']), DrandChains::mainnet())->publicKey);
        self::assertSame(4096, DrandResponse::MAX_BYTES);
    }

    /**
     * @return iterable<string, array{0: string, 1: string}>
     */
    public static function badInformation(): iterable
    {
        yield 'another chain' => [self::info(['hash' => str_repeat('cd', 32)]), 'The relay describes the chain cdcd'];
        yield 'another period' => [self::info(['period' => 30]), 'not the chain 52db'];
        yield 'another genesis' => [self::info(['genesis_time' => 1]), 'not the chain 52db'];
        yield 'another scheme' => [self::info(['schemeID' => 'bls-unchained-on-g1']), 'not the chain 52db'];
        yield 'a key of the other group' => [self::info(['public_key' => str_repeat('a0', 48)]), 'The relay does not describe a drand chain of the scheme expected.'];
        yield 'a key not text' => [self::info(['public_key' => 1]), 'The relay does not describe a drand chain'];
        yield 'a genesis not a number' => [self::info(['genesis_time' => '1']), 'The relay does not describe a drand chain'];
        yield 'a period not a number' => [self::info(['period' => '3']), 'The relay does not describe a drand chain'];
        yield 'a hash not text' => [self::info(['hash' => 1]), 'The relay does not describe a drand chain'];
        yield 'a scheme not text' => [self::info(['schemeID' => 1]), 'The relay does not describe a drand chain'];
        yield 'a key not hexadecimal' => [self::info(['public_key' => str_repeat('zz', 96)]), 'The relay describes a chain that does not read: The public key'];
        yield 'not JSON' => ['<html>', 'An answer of a relay is a JSON object.'];
        yield 'not an object' => ['3', 'An answer of a relay is a JSON object.'];
        yield 'too deep' => ['{"a":{"b":{"c":{"d":1}}}}', 'An answer of a relay is a JSON object.'];
        yield 'too long' => [str_repeat(' ', 4097), 'An answer of a relay is at most 4096 bytes.'];
    }

    #[DataProvider('badInformation')]
    public function testInformationThatIsNotTheChainExpectedIsRefused(string $json, string $message): void
    {
        $this->expectException(BeaconRefused::class);
        $this->expectExceptionMessageIsOrContains($message);

        DrandResponse::info($json, DrandChains::quicknet());
    }

    public function testInformationOfASchemeDrandDoesNotKnowIsRefused(): void
    {
        $this->expectException(BeaconRefused::class);
        $this->expectExceptionMessageIsOrContains('The relay does not describe a drand chain of the scheme expected.');

        DrandResponse::info(self::info(), new BeaconChain(DrandChains::quicknet()->hash, 1692803367, 3, 'other'));
    }

    public function testAnswersAtTheLimitsStillRead(): void
    {
        $info = self::info();

        self::assertNotNull(DrandResponse::info($info.str_repeat(' ', 4096 - \strlen($info)), DrandChains::quicknet())->publicKey);
        self::assertNotNull(DrandResponse::info('{"metadata":{"beaconID":{"x":1}},'.substr($info, 1), DrandChains::quicknet())->publicKey);
    }

    public function testARoundOfAnUnchainedSchemeIsRead(): void
    {
        $beacon = DrandResponse::beacon(self::round(7, 96), DrandScheme::BlsUnchainedG1Rfc9380, 7, BeaconVerification::RelayQuorum);

        self::assertSame(7, $beacon->round);
        self::assertSame(str_repeat('c1', 48), $beacon->signature);
        self::assertNull($beacon->previousSignature);
        self::assertSame(BeaconVerification::RelayQuorum, $beacon->verification);
    }

    public function testARoundOfAChainedSchemeKeepsThePreviousSignature(): void
    {
        $beacon = DrandResponse::beacon(self::round(7, 192, str_repeat('d2', 96)), DrandScheme::PedersenBlsChained, 7, BeaconVerification::Signature);

        self::assertSame(str_repeat('d2', 96), $beacon->previousSignature);
        self::assertSame(BeaconVerification::Signature, $beacon->verification);
    }

    /**
     * @return iterable<string, array{0: string, 1: DrandScheme, 2: string}>
     */
    public static function badRounds(): iterable
    {
        $unchained = DrandScheme::BlsUnchainedG1Rfc9380;
        $chained = DrandScheme::PedersenBlsChained;

        yield 'another round' => [self::round(8, 96), $unchained, 'The relay does not give round 7 with a signature of the scheme bls-unchained-g1-rfc9380.'];
        yield 'a round as text' => [str_replace('"round":7', '"round":"7"', self::round(7, 96)), $unchained, 'The relay does not give round 7'];
        yield 'a signature of the other group' => [self::round(7, 192), $unchained, 'The relay does not give round 7'];
        yield 'a signature not text' => [str_replace('"signature":"'.str_repeat('c1', 48).'"', '"signature":1', self::round(7, 96)), $unchained, 'The relay does not give round 7'];
        yield 'a randomness not text' => ['{"round":7,"randomness":1,"signature":"'.str_repeat('c1', 48).'"}', $unchained, 'The relay does not give round 7'];
        yield 'a chained round without its previous signature' => [self::round(7, 192), $chained, 'The relay does not give round 7 with a signature of the scheme pedersen-bls-chained.'];
        yield 'a chained round with a short previous signature' => [self::round(7, 192, str_repeat('d2', 48)), $chained, 'The relay does not give round 7'];
        yield 'a chained round with a previous signature not text' => [str_replace('}', ',"previous_signature":1}', self::round(7, 192)), $chained, 'The relay does not give round 7'];
        yield 'a randomness that is not the hash of the signature' => [str_replace('"randomness":"', '"randomness":"00', substr(self::round(7, 96), 0, -2)).'"}', $unchained, 'The relay gives a round 7 that does not check:'];
        yield 'a signature not hexadecimal' => ['{"round":7,"randomness":"'.str_repeat('0', 64).'","signature":"'.str_repeat('zz', 48).'"}', $unchained, 'The relay gives a round 7 that does not check: A beacon signature'];
    }

    #[DataProvider('badRounds')]
    public function testARoundThatDoesNotCheckIsRefused(string $json, DrandScheme $scheme, string $message): void
    {
        $this->expectException(BeaconRefused::class);
        $this->expectExceptionMessageIsOrContains($message);

        DrandResponse::beacon($json, $scheme, 7, BeaconVerification::RelayQuorum);
    }

    public function testAnUnchainedRoundIgnoresAPreviousSignature(): void
    {
        self::assertNull(DrandResponse::beacon(self::round(7, 96, 'not even hexadecimal'), DrandScheme::BlsUnchainedG1Rfc9380, 7, BeaconVerification::RelayQuorum)->previousSignature);
    }

    /**
     * @param array<string, mixed> $changes
     */
    private static function info(array $changes = []): string
    {
        return json_encode([
            'public_key' => str_repeat('a0', 96),
            'period' => 3,
            'genesis_time' => 1692803367,
            'hash' => '52db9ba70e0cc0f6eaf7803dd07447a1f5477735fd3f661792ba94600c84e971',
            'groupHash' => str_repeat('ee', 32),
            'schemeID' => 'bls-unchained-g1-rfc9380',
            'metadata' => ['beaconID' => 'quicknet'],
            ...$changes,
        ], \JSON_THROW_ON_ERROR);
    }

    public static function round(int $round, int $length, ?string $previous = null): string
    {
        $signature = str_repeat(192 === $length ? 'c2' : 'c1', intdiv($length, 2));
        $data = ['round' => $round, 'randomness' => hash('sha256', (string) hex2bin($signature)), 'signature' => $signature];

        return json_encode(null === $previous ? $data : [...$data, 'previous_signature' => $previous], \JSON_THROW_ON_ERROR);
    }
}
