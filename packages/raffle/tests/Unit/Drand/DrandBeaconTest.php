<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit\Drand;

use Closure;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tilttek\Raffle\Beacon\BeaconChain;
use Tilttek\Raffle\Beacon\BeaconVerification;
use Tilttek\Raffle\Contract\BeaconSignatureVerifier;
use Tilttek\Raffle\Drand\DrandBeacon;
use Tilttek\Raffle\Drand\DrandChains;
use Tilttek\Raffle\Exception\BeaconRefused;
use Tilttek\Raffle\Exception\BeaconUnavailable;
use Tilttek\Raffle\Exception\InvalidRaffle;
use Tilttek\Raffle\Tests\Unit\Fixture\RecordingVerifier;

#[CoversClass(DrandBeacon::class)]
#[CoversClass(BeaconUnavailable::class)]
final class DrandBeaconTest extends TestCase
{
    private const array RELAYS = ['https://relay-a.example', 'https://relay-b.example/drand/'];

    /** @var list<array{0: string, 1: string, 2: array<string, mixed>}> */
    private array $requests = [];

    public function testARoundTwoRelaysAgreeOnIsConfirmed(): void
    {
        $beacon = $this->beacon(static fn (string $url): MockResponse => new MockResponse(DrandResponseTest::round(7, 96)));
        $round = $beacon->fetch(7);

        self::assertSame(7, $round->round);
        self::assertSame(BeaconVerification::RelayQuorum, $round->verification);
        self::assertSame(['GET', 'GET'], array_column($this->requests, 0));
        self::assertSame([
            'https://relay-a.example/'.DrandChains::quicknet()->hash.'/public/7',
            'https://relay-b.example/drand/'.DrandChains::quicknet()->hash.'/public/7',
        ], array_column($this->requests, 1));
        $options = $this->requests[0][2];
        self::assertSame(0, $options['max_redirects']);
        self::assertSame(5.0, $options['timeout']);
        self::assertSame(5.0, $options['max_duration']);
        self::assertIsArray($options['headers']);
        self::assertContains('Accept: application/json', $options['headers']);
    }

    public function testAnAnswerInSeveralChunksIsReadWhole(): void
    {
        $body = DrandResponseTest::round(7, 96);
        $beacon = $this->beacon(static fn (): MockResponse => new MockResponse(str_split($body, 10)));

        self::assertSame(7, $beacon->fetch(7)->round);
    }

    public function testRelaysThatDisagreeAreRefused(): void
    {
        $other = '{"round":7,"randomness":"'.hash('sha256', (string) hex2bin(str_repeat('c3', 48))).'","signature":"'.str_repeat('c3', 48).'"}';
        $beacon = $this->beacon(static fn (string $url): MockResponse => new MockResponse(str_contains($url, 'relay-a') ? DrandResponseTest::round(7, 96) : $other));

        $this->expectException(BeaconRefused::class);
        $this->expectExceptionMessageIsOrContains('The relays give different signatures for round 7.');

        $beacon->fetch(7);
    }

    public function testARelayThatAnswersWithARoundThatDoesNotCheckStopsTheRead(): void
    {
        $beacon = $this->beacon(static fn (string $url): MockResponse => new MockResponse(str_contains($url, 'relay-a') ? DrandResponseTest::round(7, 96) : DrandResponseTest::round(8, 96)));

        $this->expectException(BeaconRefused::class);
        $this->expectExceptionMessageIsOrContains('The relay does not give round 7');

        $beacon->fetch(7);
    }

    public function testARoundTooFewRelaysGiveIsUnavailable(): void
    {
        $failures = [
            'not published yet' => static fn (): MockResponse => new MockResponse('{"error":"too early"}', ['http_code' => 425]),
            'a redirect' => static fn (): MockResponse => new MockResponse('', ['http_code' => 302, 'response_headers' => ['Location' => 'https://elsewhere.example']]),
            'too long' => static fn (): MockResponse => new MockResponse(DrandResponseTest::round(7, 96).str_repeat(' ', 4097)),
            'too long in chunks' => static fn (): MockResponse => new MockResponse([str_repeat(' ', 4000), str_repeat(' ', 97), DrandResponseTest::round(7, 96)]),
            'silent' => static fn (): MockResponse => new MockResponse(['']),
            'broken' => static fn (): MockResponse => new MockResponse((static function (): iterable {
                yield '{"round":';

                throw new RuntimeException('The connection was reset.');
            })()),
            'unreachable' => static fn (): MockResponse => new MockResponse('', ['error' => 'Could not resolve host']),
        ];

        foreach ($failures as $case => $failure) {
            $beacon = $this->beacon(static fn (string $url): MockResponse => str_contains($url, 'relay-a') ? new MockResponse(DrandResponseTest::round(7, 96)) : $failure(), timeout: 0.1);

            try {
                $beacon->fetch(7);
                self::fail('A round one relay gave was confirmed: '.$case);
            } catch (BeaconUnavailable $unavailable) {
                self::assertSame('The beacon gave 1 answer(s) for round 7, 2 needed: the round is not published yet, or the relays are unreachable.', $unavailable->getMessage(), $case);
            }
        }
    }

    public function testAnAnswerAtTheLongestLengthStillCounts(): void
    {
        $body = DrandResponseTest::round(7, 96);
        $beacon = $this->beacon(static fn (): MockResponse => new MockResponse($body.str_repeat(' ', 4096 - \strlen($body))));

        self::assertSame(7, $beacon->fetch(7)->round);
    }

    public function testAQuorumOfOneOfTwoRelaysTakesTheFirstAnswer(): void
    {
        $beacon = $this->beacon(static fn (string $url): MockResponse => match (true) {
            str_contains($url, 'relay-a') => new MockResponse('', ['http_code' => 404]),
            str_ends_with($url, '/info') => new MockResponse(self::info()),
            default => new MockResponse(DrandResponseTest::round(7, 96)),
        }, new RecordingVerifier(true), 1);

        self::assertSame(BeaconVerification::Signature, $beacon->fetch(7)->verification);
    }

    public function testASignatureVerifierVerifiesTheRoundAgainstTheKeyTheRelaysGive(): void
    {
        $verifier = new RecordingVerifier(true);
        $beacon = $this->beacon(static fn (string $url): MockResponse => new MockResponse(str_ends_with($url, '/info') ? self::info() : DrandResponseTest::round(7, 96)), $verifier);
        $round = $beacon->fetch(7);

        self::assertSame(BeaconVerification::Signature, $round->verification);
        self::assertCount(1, $verifier->verified);
        self::assertSame(str_repeat('a0', 96), $verifier->verified[0][0]->publicKey);
        self::assertSame($round, $verifier->verified[0][1]);
        self::assertCount(4, $this->requests);
        self::assertStringEndsWith('/info', $this->requests[3][1]);
    }

    public function testASignatureThatDoesNotVerifyIsRefused(): void
    {
        $beacon = $this->beacon(static fn (string $url): MockResponse => new MockResponse(str_ends_with($url, '/info') ? self::info() : DrandResponseTest::round(7, 96)), new RecordingVerifier(false));

        $this->expectException(BeaconRefused::class);
        $this->expectExceptionMessageIsOrContains('The signature of round 7 does not verify against the public key of the chain.');

        $beacon->fetch(7);
    }

    public function testTheChainIsReadWithItsPublicKey(): void
    {
        $beacon = $this->beacon(static fn (): MockResponse => new MockResponse(self::info()));
        $chain = $beacon->chain();

        self::assertSame(str_repeat('a0', 96), $chain->publicKey);
        self::assertTrue($chain->isSame(DrandChains::quicknet()));
        self::assertSame('https://relay-a.example/'.DrandChains::quicknet()->hash.'/info', $this->requests[0][1]);
    }

    public function testRelaysThatGiveDifferentKeysAreRefused(): void
    {
        $beacon = $this->beacon(static fn (string $url): MockResponse => new MockResponse(str_contains($url, 'relay-a') ? self::info() : self::info(str_repeat('a1', 96))));

        $this->expectException(BeaconRefused::class);
        $this->expectExceptionMessageIsOrContains('The relays give different public keys for the chain 52db');

        $beacon->chain();
    }

    public function testAChainTooFewRelaysDescribeIsUnavailable(): void
    {
        $beacon = $this->beacon(static fn (string $url): MockResponse => str_contains($url, 'relay-a') ? new MockResponse(self::info()) : new MockResponse('', ['http_code' => 500]));

        $this->expectException(BeaconUnavailable::class);
        $this->expectExceptionMessageIsOrContains('The beacon gave 1 answer(s) for the chain information, 2 needed');

        $beacon->chain();
    }

    public function testARoundOutOfRangeIsRefusedBeforeAnyRequest(): void
    {
        $beacon = $this->beacon(static fn (): MockResponse => new MockResponse(''));

        try {
            $beacon->fetch(0);
            self::fail('Round 0 was asked for.');
        } catch (InvalidRaffle) {
            self::assertSame([], $this->requests);
        }
    }

    public function testTheConfigurationIsChecked(): void
    {
        $http = new MockHttpClient();
        $chain = DrandChains::quicknet();
        $verifier = new RecordingVerifier(true);
        $bad = [
            'an unknown scheme' => [static fn (): DrandBeacon => new DrandBeacon($http, new BeaconChain($chain->hash, 1, 3, 'other'), self::RELAYS), 'The scheme other is not one of drand.'],
            'no relay' => [static fn (): DrandBeacon => new DrandBeacon($http, $chain, [], $verifier), 'A beacon reads 1 to 8 distinct relays.'],
            'too many relays' => [static fn (): DrandBeacon => new DrandBeacon($http, $chain, array_map(static fn (int $relay): string => 'https://r'.$relay.'.example', range(1, 9))), 'A beacon reads 1 to 8 distinct relays.'],
            'not an address' => [static fn (): DrandBeacon => new DrandBeacon($http, $chain, ['relay-a', 'https://relay-b.example']), 'The relay relay-a is not an address.'],
            'plain HTTP' => [static fn (): DrandBeacon => new DrandBeacon($http, $chain, ['http://relay-a.example', 'https://relay-b.example']), 'A relay is an HTTPS address without a query nor a fragment, not http://relay-a.example.'],
            'a query' => [static fn (): DrandBeacon => new DrandBeacon($http, $chain, ['https://relay-a.example/?a=1', 'https://relay-b.example']), 'A relay is an HTTPS address without a query nor a fragment, not https://relay-a.example/?a=1.'],
            'a fragment' => [static fn (): DrandBeacon => new DrandBeacon($http, $chain, ['https://relay-a.example/#top', 'https://relay-b.example']), 'A relay is an HTTPS address without a query nor a fragment, not https://relay-a.example/#top.'],
            'the same relay twice' => [static fn (): DrandBeacon => new DrandBeacon($http, $chain, ['https://relay-a.example', 'https://relay-a.example/']), 'The quorum is 1 to the 1 relays given.'],
            'one relay without a verifier' => [static fn (): DrandBeacon => new DrandBeacon($http, $chain, ['https://relay-a.example'], quorum: 1), 'Without a signature verifier, a round is confirmed by two relays at least.'],
            'a quorum of zero' => [static fn (): DrandBeacon => new DrandBeacon($http, $chain, self::RELAYS, $verifier, 0), 'The quorum is 1 to the 2 relays given.'],
            'a quorum above the relays' => [static fn (): DrandBeacon => new DrandBeacon($http, $chain, self::RELAYS, quorum: 3), 'The quorum is 1 to the 2 relays given.'],
            'a short timeout' => [static fn (): DrandBeacon => new DrandBeacon($http, $chain, self::RELAYS, timeout: 0.09), 'A read of the beacon takes 0.1 to 30 seconds.'],
            'a long timeout' => [static fn (): DrandBeacon => new DrandBeacon($http, $chain, self::RELAYS, timeout: 30.1), 'A read of the beacon takes 0.1 to 30 seconds.'],
        ];

        foreach ($bad as $case => [$build, $message]) {
            try {
                $build();
                self::fail('A configuration was taken: '.$case);
            } catch (InvalidRaffle $invalid) {
                self::assertSame($message, $invalid->getMessage(), $case);
            }
        }

        self::assertInstanceOf(DrandBeacon::class, new DrandBeacon($http, $chain, ['https://relay-a.example'], $verifier));
        self::assertInstanceOf(DrandBeacon::class, new DrandBeacon($http, $chain, array_map(static fn (int $relay): string => 'https://r'.$relay.'.example', range(1, 8)), timeout: 30.0));
        self::assertInstanceOf(DrandBeacon::class, new DrandBeacon($http, $chain, self::RELAYS, quorum: 2, timeout: 0.1));
        self::assertSame(8, DrandBeacon::MAX_RELAYS);
    }

    /**
     * @param Closure(string): MockResponse $answer
     */
    private function beacon(Closure $answer, ?BeaconSignatureVerifier $verifier = null, ?int $quorum = null, float $timeout = 5.0): DrandBeacon
    {
        $http = new MockHttpClient(function (string $method, string $url, array $options) use ($answer): MockResponse {
            /** @var array<string, mixed> $options */
            $this->requests[] = [$method, $url, $options];

            return $answer($url);
        });

        return new DrandBeacon($http, DrandChains::quicknet(), self::RELAYS, $verifier, $quorum, $timeout);
    }

    private static function info(string $key = ''): string
    {
        return json_encode([
            'public_key' => '' === $key ? str_repeat('a0', 96) : $key,
            'period' => 3,
            'genesis_time' => 1692803367,
            'hash' => DrandChains::quicknet()->hash,
            'schemeID' => 'bls-unchained-g1-rfc9380',
        ], \JSON_THROW_ON_ERROR);
    }
}
