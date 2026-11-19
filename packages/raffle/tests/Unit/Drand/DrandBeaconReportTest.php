<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Tests\Unit\Drand;

use Closure;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Clock\ClockInterface;
use RuntimeException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Tilttek\Raffle\Beacon\RelayOutcome;
use Tilttek\Raffle\Beacon\RelayReport;
use Tilttek\Raffle\Drand\DrandBeacon;
use Tilttek\Raffle\Drand\DrandChains;
use Tilttek\Raffle\Event\BeaconRelaysRead;
use Tilttek\Raffle\Exception\BeaconRefused;
use Tilttek\Raffle\Exception\BeaconUnavailable;
use Tilttek\Raffle\Tests\Unit\Fixture\RecordingDispatcher;
use Tilttek\Raffle\Tests\Unit\Fixture\ScriptedChunk;
use Tilttek\Raffle\Tests\Unit\Fixture\ScriptedClock;
use Tilttek\Raffle\Tests\Unit\Fixture\ScriptedHttp;

/**
 * The quorum of relays, relay by relay: what each one gave to a read is reported through the event and on the
 * exception that refuses the read, and the whole read ends at the timeout on the clock.
 */
#[CoversClass(DrandBeacon::class)]
#[CoversClass(RelayReport::class)]
#[CoversClass(RelayOutcome::class)]
#[CoversClass(BeaconRelaysRead::class)]
#[CoversClass(BeaconRefused::class)]
#[CoversClass(BeaconUnavailable::class)]
final class DrandBeaconReportTest extends TestCase
{
    private const string A = 'https://relay-a.example';

    private const string B = 'https://relay-b.example';

    private const string C = 'https://relay-c.example';

    private RecordingDispatcher $events;

    #[Override]
    protected function setUp(): void
    {
        $this->events = new RecordingDispatcher();
    }

    public function testAReadEveryRelayAgreesOnIsReportedAndKept(): void
    {
        $beacon = $this->beacon(static fn (): MockResponse => new MockResponse(DrandResponseTest::round(7, 96)));

        self::assertSame(7, $beacon->fetch(7)->round);
        $report = $this->report();
        self::assertSame('round 7', $report->what);
        self::assertSame(2, $report->quorum);
        self::assertTrue($report->kept);
        self::assertSame([self::A => RelayOutcome::Agreed, self::B => RelayOutcome::Agreed, self::C => RelayOutcome::Agreed], $report->outcomes);
        self::assertSame(3, $report->answers());
        self::assertSame([], $report->faults());
        self::assertCount(1, $this->events->events);
        self::assertInstanceOf(BeaconRelaysRead::class, $this->events->events[0]);
        self::assertSame(DrandChains::quicknet()->hash, $this->events->events[0]->chain);
    }

    public function testARelayThatFailsIsReportedWhileTheQuorumKeepsTheRead(): void
    {
        $beacon = $this->beacon(static fn (string $url): MockResponse => str_contains($url, 'relay-c')
            ? new MockResponse('{"error":"too early"}', ['http_code' => 425])
            : new MockResponse(DrandResponseTest::round(7, 96)));

        self::assertSame(7, $beacon->fetch(7)->round);
        $report = $this->report();
        self::assertTrue($report->kept);
        self::assertSame(2, $report->answers());
        self::assertSame([self::C => RelayOutcome::Status], $report->faults());
    }

    public function testEachFailureIsNamedOnTheUnavailableRead(): void
    {
        $failures = [
            'status' => [static fn (): MockResponse => new MockResponse('', ['http_code' => 500]), RelayOutcome::Status],
            'silent' => [static fn (): MockResponse => new MockResponse(['']), RelayOutcome::TimedOut],
            'too long' => [static fn (): MockResponse => new MockResponse(DrandResponseTest::round(7, 96).str_repeat(' ', 4097)), RelayOutcome::TooLarge],
            'broken' => [static fn (): MockResponse => new MockResponse((static function (): iterable {
                yield '{"round":';

                throw new RuntimeException('The connection was reset.');
            })()), RelayOutcome::Unreachable],
            'unreachable' => [static fn (): MockResponse => new MockResponse('', ['error' => 'Could not resolve host']), RelayOutcome::Unreachable],
        ];

        foreach ($failures as $case => [$failure, $outcome]) {
            $this->events = new RecordingDispatcher();
            $beacon = $this->beacon(static fn (string $url): MockResponse => str_contains($url, 'relay-a') ? new MockResponse(DrandResponseTest::round(7, 96)) : $failure(), timeout: 0.1);

            try {
                $beacon->fetch(7);
                self::fail('A round one relay gave was kept: '.$case);
            } catch (BeaconUnavailable $unavailable) {
                $report = $unavailable->report;
                self::assertInstanceOf(RelayReport::class, $report, $case);
                self::assertSame([self::A => RelayOutcome::Agreed, self::B => $outcome, self::C => $outcome], $report->outcomes, $case);
                self::assertFalse($report->kept, $case);
                self::assertSame(1, $report->answers(), $case);
                self::assertSame($report, $this->report(), $case);
            }
        }
    }

    public function testAnAnswerThatNeverEndsIsReportedIncomplete(): void
    {
        $http = new ScriptedHttp([
            [0, new ScriptedChunk(DrandResponseTest::round(7, 96), true)],
            [1, new ScriptedChunk(DrandResponseTest::round(7, 96))],
        ]);

        try {
            new DrandBeacon($http, DrandChains::quicknet(), [self::A, self::B])->fetch(7);
            self::fail('A round one relay gave whole was kept.');
        } catch (BeaconUnavailable $beaconUnavailable) {
            self::assertSame([self::A => RelayOutcome::Agreed, self::B => RelayOutcome::Incomplete], $beaconUnavailable->report?->outcomes);
        }
    }

    public function testTheRelayThatContradictsTheOthersIsNamed(): void
    {
        $other = $this->otherRound();
        $beacon = $this->beacon(static fn (string $url): MockResponse => new MockResponse(str_contains($url, 'relay-a') ? $other : DrandResponseTest::round(7, 96)));

        try {
            $beacon->fetch(7);
            self::fail('A round two relays tell differently was kept.');
        } catch (BeaconRefused $beaconRefused) {
            self::assertSame('The relays give different signatures for round 7.', $beaconRefused->getMessage());
            self::assertSame([self::A => RelayOutcome::Disagreed, self::B => RelayOutcome::Agreed, self::C => RelayOutcome::Agreed], $beaconRefused->report?->outcomes);
            self::assertFalse($beaconRefused->report->kept);
            self::assertSame(3, $beaconRefused->report->answers());
            self::assertSame([self::A => RelayOutcome::Disagreed], $beaconRefused->report->faults());
            self::assertSame($beaconRefused->report, $this->report());
        }
    }

    public function testOnATieTheFirstRelayIsTheOneTheOthersAreComparedTo(): void
    {
        $other = $this->otherRound();
        $beacon = $this->beacon(static fn (string $url): MockResponse => match (true) {
            str_contains($url, 'relay-a') => new MockResponse(DrandResponseTest::round(7, 96)),
            str_contains($url, 'relay-b') => new MockResponse($other),
            default => new MockResponse('', ['http_code' => 404]),
        });

        try {
            $beacon->fetch(7);
            self::fail('A round two relays tell differently was kept.');
        } catch (BeaconRefused $beaconRefused) {
            self::assertSame([self::A => RelayOutcome::Agreed, self::B => RelayOutcome::Disagreed, self::C => RelayOutcome::Status], $beaconRefused->report?->outcomes);
        }
    }

    public function testAnAnswerThatDoesNotCheckIsNamedAndRefusesTheRead(): void
    {
        $beacon = $this->beacon(static fn (string $url): MockResponse => new MockResponse(str_contains($url, 'relay-b') ? DrandResponseTest::round(8, 96) : DrandResponseTest::round(7, 96)));

        try {
            $beacon->fetch(7);
            self::fail('A read with an answer that does not check was kept.');
        } catch (BeaconRefused $beaconRefused) {
            self::assertStringStartsWith('The relay does not give round 7', $beaconRefused->getMessage());
            self::assertSame([self::A => RelayOutcome::Agreed, self::B => RelayOutcome::Invalid, self::C => RelayOutcome::Agreed], $beaconRefused->report?->outcomes);
            self::assertFalse($beaconRefused->report->kept);
            self::assertSame(2, $beaconRefused->report->answers());
        }
    }

    public function testTheChainInformationIsReportedToo(): void
    {
        $beacon = $this->beacon(static fn (string $url): MockResponse => new MockResponse(str_contains($url, 'relay-c') ? self::info('a1') : self::info('a0')));

        try {
            $beacon->chain();
            self::fail('A chain two relays describe differently was kept.');
        } catch (BeaconRefused $beaconRefused) {
            self::assertSame('the chain information', $beaconRefused->report?->what);
            self::assertSame([self::A => RelayOutcome::Agreed, self::B => RelayOutcome::Agreed, self::C => RelayOutcome::Disagreed], $beaconRefused->report->outcomes);
        }

        $kept = $this->beacon(static fn (): MockResponse => new MockResponse(self::info('a0')));
        self::assertSame(str_repeat('a0', 96), $kept->chain()->publicKey);
        self::assertTrue($this->report()->kept);
    }

    public function testAnInvalidChainInformationIsNamed(): void
    {
        $beacon = $this->beacon(static fn (string $url): MockResponse => new MockResponse(str_contains($url, 'relay-a') ? '[]' : self::info('a0')));

        try {
            $beacon->chain();
            self::fail('A chain one relay describes wrongly was kept.');
        } catch (BeaconRefused $beaconRefused) {
            self::assertSame([self::A => RelayOutcome::Invalid, self::B => RelayOutcome::Agreed, self::C => RelayOutcome::Agreed], $beaconRefused->report?->outcomes);
        }
    }

    public function testTheWholeReadEndsAtTheTimeoutOnTheClock(): void
    {
        $late = new ScriptedClock('2026-11-12 20:00:00.000000 UTC', '2026-11-12 20:00:05.000002 UTC');
        $beacon = $this->beacon(static fn (): MockResponse => new MockResponse(DrandResponseTest::round(7, 96)), clock: $late);

        try {
            $beacon->fetch(7);
            self::fail('A read past its time was kept.');
        } catch (BeaconUnavailable $beaconUnavailable) {
            self::assertSame([self::A => RelayOutcome::TimedOut, self::B => RelayOutcome::TimedOut, self::C => RelayOutcome::TimedOut], $beaconUnavailable->report?->outcomes);
        }
    }

    public function testAReadThatEndsOnItsTimeIsKept(): void
    {
        $onTime = new ScriptedClock('2026-11-12 20:00:00.000000 UTC', '2026-11-12 20:00:05.000000 UTC');
        $beacon = $this->beacon(static fn (): MockResponse => new MockResponse(DrandResponseTest::round(7, 96)), clock: $onTime);

        self::assertSame(7, $beacon->fetch(7)->round);
    }

    public function testARelayAlreadyWholeIsKeptWhenTheTimeRunsOut(): void
    {
        $http = new ScriptedHttp([
            [0, new ScriptedChunk(DrandResponseTest::round(7, 96), true)],
            [1, new ScriptedChunk(DrandResponseTest::round(7, 96), true)],
            [2, new ScriptedChunk('{"round"')],
        ]);
        $clock = new ScriptedClock('2026-11-12 20:00:00 UTC', '2026-11-12 20:00:01 UTC', '2026-11-12 20:00:01 UTC', '2026-11-12 20:00:09 UTC');

        $round = new DrandBeacon($http, DrandChains::quicknet(), [self::A, self::B, self::C], events: $this->events, clock: $clock)->fetch(7);

        self::assertSame(7, $round->round);
        self::assertSame([self::A => RelayOutcome::Agreed, self::B => RelayOutcome::Agreed, self::C => RelayOutcome::TimedOut], $this->report()->outcomes);
        self::assertFalse($http->responses[0]->getInfo('canceled'));
        self::assertTrue($http->responses[2]->getInfo('canceled'));
    }

    public function testTheFirstAnswerThatDoesNotCheckIsTheOneNamedOnTheRound(): void
    {
        $beacon = $this->beacon(static fn (string $url): MockResponse => match (true) {
            str_contains($url, 'relay-a') => new MockResponse('not JSON'),
            str_contains($url, 'relay-b') => new MockResponse(DrandResponseTest::round(8, 96)),
            default => new MockResponse(DrandResponseTest::round(7, 96)),
        });

        try {
            $beacon->fetch(7);
            self::fail('A read with two answers that do not check was kept.');
        } catch (BeaconRefused $beaconRefused) {
            self::assertSame('An answer of a relay is a JSON object.', $beaconRefused->getMessage());
            self::assertSame([self::A => RelayOutcome::Invalid, self::B => RelayOutcome::Invalid, self::C => RelayOutcome::Agreed], $beaconRefused->report?->outcomes);
        }
    }

    public function testTheFirstChainInformationThatDoesNotCheckIsTheOneNamed(): void
    {
        $beacon = $this->beacon(static fn (string $url): MockResponse => match (true) {
            str_contains($url, 'relay-a') => new MockResponse('not JSON'),
            str_contains($url, 'relay-b') => new MockResponse('[]'),
            default => new MockResponse(self::info('a0')),
        });

        try {
            $beacon->chain();
            self::fail('A read with two chain descriptions that do not check was kept.');
        } catch (BeaconRefused $beaconRefused) {
            self::assertSame('An answer of a relay is a JSON object.', $beaconRefused->getMessage());
        }
    }

    public function testNothingMoreIsReadOnceTheTimeRunsOut(): void
    {
        $http = new ScriptedHttp([
            [0, new ScriptedChunk(DrandResponseTest::round(7, 96), true)],
            [0, new ScriptedChunk(' ')],
        ]);
        $clock = new ScriptedClock('2026-11-12 20:00:00 UTC', '2026-11-12 20:00:09 UTC');

        try {
            new DrandBeacon($http, DrandChains::quicknet(), [self::A, self::B], events: $this->events, clock: $clock)->fetch(7);
            self::fail('A read past its time was kept.');
        } catch (BeaconUnavailable $beaconUnavailable) {
            self::assertSame([self::A => RelayOutcome::Agreed, self::B => RelayOutcome::TimedOut], $beaconUnavailable->report?->outcomes);
        }
    }

    public function testARelayThatBreaksAfterItsLastChunkIsNotCounted(): void
    {
        $http = new ScriptedHttp([
            [0, new ScriptedChunk(DrandResponseTest::round(7, 96), true)],
            [1, new ScriptedChunk(DrandResponseTest::round(7, 96), true)],
            [1, new ScriptedChunk(str_repeat(' ', 4097), true)],
        ]);

        try {
            new DrandBeacon($http, DrandChains::quicknet(), [self::A, self::B], events: $this->events)->fetch(7);
            self::fail('A round one relay gave too much of was kept.');
        } catch (BeaconUnavailable $beaconUnavailable) {
            self::assertSame([self::A => RelayOutcome::Agreed, self::B => RelayOutcome::TooLarge], $beaconUnavailable->report?->outcomes);
        }
    }

    public function testOnATieTheFirstRelayIsStillTheReferenceWhenTheLastOneAnswersFirst(): void
    {
        $http = new ScriptedHttp([
            [1, new ScriptedChunk($this->otherRound(), true)],
            [0, new ScriptedChunk(DrandResponseTest::round(7, 96), true)],
        ]);

        try {
            new DrandBeacon($http, DrandChains::quicknet(), [self::A, self::B], events: $this->events)->fetch(7);
            self::fail('A round two relays tell differently was kept.');
        } catch (BeaconRefused $beaconRefused) {
            self::assertSame([self::A => RelayOutcome::Agreed, self::B => RelayOutcome::Disagreed], $beaconRefused->report?->outcomes);
        }
    }

    public function testOutcomesSayWhetherTheRelayAnswered(): void
    {
        $answered = array_map(static fn (RelayOutcome $outcome): bool => $outcome->answered(), RelayOutcome::cases());

        self::assertSame([true, true, false, false, false, false, false, false], $answered);
    }

    /**
     * @param Closure(string): MockResponse $answer
     */
    private function beacon(Closure $answer, float $timeout = 5.0, ?ClockInterface $clock = null): DrandBeacon
    {
        $http = new MockHttpClient(static fn (string $method, string $url): MockResponse => $answer($url));

        return $clock instanceof ClockInterface
            ? new DrandBeacon($http, DrandChains::quicknet(), [self::A, self::B, self::C], timeout: $timeout, events: $this->events, clock: $clock)
            : new DrandBeacon($http, DrandChains::quicknet(), [self::A, self::B, self::C], timeout: $timeout, events: $this->events);
    }

    private function report(): RelayReport
    {
        $event = $this->events->events[array_key_last($this->events->events) ?? 0] ?? null;
        self::assertInstanceOf(BeaconRelaysRead::class, $event);

        return $event->report;
    }

    private function otherRound(): string
    {
        $signature = str_repeat('c3', 48);

        return json_encode(['round' => 7, 'randomness' => hash('sha256', (string) hex2bin($signature)), 'signature' => $signature], \JSON_THROW_ON_ERROR);
    }

    private static function info(string $key): string
    {
        return json_encode([
            'public_key' => str_repeat($key, 96),
            'period' => 3,
            'genesis_time' => 1692803367,
            'hash' => DrandChains::quicknet()->hash,
            'schemeID' => 'bls-unchained-g1-rfc9380',
        ], \JSON_THROW_ON_ERROR);
    }
}
