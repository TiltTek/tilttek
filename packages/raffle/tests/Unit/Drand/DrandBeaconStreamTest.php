<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit\Drand;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Raffle\Beacon\BeaconChain;
use Tilttek\Raffle\Drand\DrandBeacon;
use Tilttek\Raffle\Drand\DrandChains;
use Tilttek\Raffle\Exception\BeaconUnavailable;
use Tilttek\Raffle\Tests\Unit\Fixture\ScriptedChunk;
use Tilttek\Raffle\Tests\Unit\Fixture\ScriptedHttp;

/**
 * What a stream of a real transport may do that a mock never does: end without a last chunk, give a long answer in its
 * last chunk, break on reading, or interleave the relays.
 */
#[CoversClass(DrandBeacon::class)]
#[CoversClass(BeaconUnavailable::class)]
final class DrandBeaconStreamTest extends TestCase
{
    private const array RELAYS = ['https://relay-a.example', 'https://relay-b.example'];

    public function testAnAnswerThatNeverEndsIsNotCounted(): void
    {
        $http = new ScriptedHttp([
            [0, new ScriptedChunk(DrandResponseTest::round(7, 96))],
            [0, new ScriptedChunk('', true)],
            [1, new ScriptedChunk(DrandResponseTest::round(7, 96))],
        ]);

        $this->assertUnavailable($http);
        self::assertFalse($http->responses[1]->getInfo('canceled'));
    }

    public function testAnAnswerTooLongInItsLastChunkIsNotCounted(): void
    {
        $http = new ScriptedHttp([
            [0, new ScriptedChunk(DrandResponseTest::round(7, 96), true)],
            [1, new ScriptedChunk(DrandResponseTest::round(7, 96).str_repeat(' ', 4097), true)],
        ]);

        $this->assertUnavailable($http);
        self::assertTrue($http->responses[1]->getInfo('canceled'));
        self::assertFalse($http->responses[0]->getInfo('canceled'));
    }

    public function testARelayWhoseAnswerBreaksIsCancelled(): void
    {
        $http = new ScriptedHttp([
            [0, new ScriptedChunk(DrandResponseTest::round(7, 96), true)],
            [1, new ScriptedChunk('', false, true)],
        ]);

        $this->assertUnavailable($http);
        self::assertTrue($http->responses[1]->getInfo('canceled'));
    }

    public function testTheAnswerOfTheFirstRelayIsKeptWhateverTheOrderTheyArriveIn(): void
    {
        $signature = str_repeat('ab', 96);
        $answer = static fn (string $previous): string => json_encode(['round' => 7, 'randomness' => hash('sha256', (string) hex2bin($signature)), 'signature' => $signature, 'previous_signature' => $previous], \JSON_THROW_ON_ERROR);
        $http = new ScriptedHttp([
            [1, new ScriptedChunk($answer(str_repeat('ef', 96)), true)],
            [0, new ScriptedChunk($answer(str_repeat('cd', 96)), true)],
        ]);
        $chain = new BeaconChain(DrandChains::quicknet()->hash, 1692803367, 30, 'pedersen-bls-chained');

        self::assertSame(str_repeat('cd', 96), new DrandBeacon($http, $chain, self::RELAYS)->fetch(7)->previousSignature);
    }

    private function assertUnavailable(ScriptedHttp $http): void
    {
        try {
            new DrandBeacon($http, DrandChains::quicknet(), self::RELAYS)->fetch(7);
            self::fail('A round one relay gave was confirmed.');
        } catch (BeaconUnavailable $beaconUnavailable) {
            self::assertSame('The beacon gave 1 answer(s) for round 7, 2 needed: the round is not published yet, or the relays are unreachable.', $beaconUnavailable->getMessage());
        }
    }
}
