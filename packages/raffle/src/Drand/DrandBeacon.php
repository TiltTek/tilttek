<?php

declare(strict_types=1);

namespace Tilttek\Raffle\Drand;

use Override;
use Phil\Url\Exception\UrlException;
use Phil\Url\Url;
use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Symfony\Contracts\HttpClient\ChunkInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Tilttek\Raffle\Beacon\Beacon;
use Tilttek\Raffle\Beacon\BeaconChain;
use Tilttek\Raffle\Beacon\BeaconVerification;
use Tilttek\Raffle\Beacon\RelayOutcome;
use Tilttek\Raffle\Beacon\RelayReport;
use Tilttek\Raffle\Contract\BeaconSignatureVerifier;
use Tilttek\Raffle\Contract\RandomnessBeacon;
use Tilttek\Raffle\Event\BeaconRelaysRead;
use Tilttek\Raffle\Exception\BeaconRefused;
use Tilttek\Raffle\Exception\BeaconUnavailable;
use Tilttek\Raffle\Exception\InvalidRaffle;
use Tilttek\Raffle\SystemClock;

/**
 * drand, the randomness beacon of the League of Entropy, read over HTTP through Symfony HttpClient.
 *
 * Each read asks every relay at once and waits for their answers, each bounded in time and in size. A round is kept
 * only when its randomness is the SHA-256 of its signature, every relay that answered gives the same signature, and at
 * least the quorum of relays answered; with a signature verifier, the signature is also verified against the public
 * key of the chain, read from the relays and checked the same way. A relay that answers with a round or a chain that
 * does not check stops the read: nothing is drawn from a beacon that two relays tell differently.
 *
 * Each read is reported, relay by relay — agreed, disagreed, invalid, silent, late, too long, unreachable —, through
 * `Event\BeaconRelaysRead` and on the exception that refuses it, so that a relay that fails is seen before the quorum
 * is lost.
 *
 * Without a signature verifier, the quorum is two relays at least, run by different operators: the round is then
 * confirmed, not verified, and the proof says so, so that a player verifies its signature with drand's own tools.
 */
final readonly class DrandBeacon implements RandomnessBeacon
{
    /** The most relays read at once. */
    public const int MAX_RELAYS = 8;

    private DrandScheme $scheme;

    /** @var non-empty-list<string> */
    private array $relays;

    private int $quorum;

    /**
     * @param list<string>              $relays  the base addresses of the relays, HTTPS only; `DrandChains::RELAYS` serves the
     *                                           public chains
     * @param ?int                      $quorum  how many relays must give the same answer: 1 with a signature verifier and 2
     *                                           without by default, all the relays at most
     * @param float                     $timeout the seconds each read may take, 0.1 to 30, counted by the transport for each
     *                                           relay and on the clock for the whole read
     * @param ?EventDispatcherInterface $events  who hears of each read through `Event\BeaconRelaysRead`
     *
     * @throws InvalidRaffle when the scheme is not one of drand, a relay is not an HTTPS address, or the quorum is out
     *                       of range
     */
    public function __construct(
        private HttpClientInterface $http,
        private BeaconChain $chain,
        array $relays,
        private ?BeaconSignatureVerifier $signatures = null,
        ?int $quorum = null,
        private float $timeout = 5.0,
        private ?EventDispatcherInterface $events = null,
        private ClockInterface $clock = new SystemClock(),
    ) {
        $this->scheme = DrandScheme::tryFrom($chain->scheme) ?? throw InvalidRaffle::because(\sprintf('The scheme %s is not one of drand.', $chain->scheme));
        $this->relays = $this->relays($relays);
        $this->quorum = $quorum ?? ($signatures instanceof BeaconSignatureVerifier ? 1 : 2);

        if ($this->quorum < 1 || $this->quorum > \count($this->relays)) {
            throw InvalidRaffle::because(\sprintf('The quorum is 1 to the %d relays given.', \count($this->relays)));
        }

        if (!$signatures instanceof BeaconSignatureVerifier && $this->quorum < 2) {
            throw InvalidRaffle::because('Without a signature verifier, a round is confirmed by two relays at least.');
        }

        if ($timeout < 0.1 || $timeout > 30.0) {
            throw InvalidRaffle::because('A read of the beacon takes 0.1 to 30 seconds.');
        }
    }

    #[Override]
    public function chain(): BeaconChain
    {
        [$bodies, $outcomes] = $this->read('/info');
        $keys = [];
        $invalid = null;

        foreach ($bodies as $index => $body) {
            try {
                $keys[$index] = (string) DrandResponse::info($body, $this->chain)->publicKey;
            } catch (BeaconRefused $refused) {
                $outcomes[$index] = RelayOutcome::Invalid;
                $invalid ??= $refused;
            }
        }

        $first = $this->settle('the chain information', \count($bodies), $outcomes, $keys, $invalid, \sprintf('The relays give different public keys for the chain %s.', $this->chain->hash));

        return $this->chain->withPublicKey($keys[$first]);
    }

    #[Override]
    public function fetch(int $round): Beacon
    {
        BeaconChain::round($round);
        $verification = $this->signatures instanceof BeaconSignatureVerifier ? BeaconVerification::Signature : BeaconVerification::RelayQuorum;
        [$bodies, $outcomes] = $this->read('/public/'.$round);
        $beacons = [];
        $invalid = null;

        foreach ($bodies as $index => $body) {
            try {
                $beacons[$index] = DrandResponse::beacon($body, $this->scheme, $round, $verification);
            } catch (BeaconRefused $refused) {
                $outcomes[$index] = RelayOutcome::Invalid;
                $invalid ??= $refused;
            }
        }

        $signatures = array_map(static fn (Beacon $beacon): string => $beacon->signature, $beacons);
        $beacon = $beacons[$this->settle('round '.$round, \count($bodies), $outcomes, $signatures, $invalid, \sprintf('The relays give different signatures for round %d.', $round))];

        if ($this->signatures instanceof BeaconSignatureVerifier && !$this->signatures->verify($this->chain(), $beacon)) {
            throw BeaconRefused::because(\sprintf('The signature of round %d does not verify against the public key of the chain.', $round));
        }

        return $beacon;
    }

    /**
     * Reports the read, then keeps it or throws: too few answers, an answer that does not check, or relays that give
     * different values. The value most relays give is the one the others are compared to; on a tie, that of the first
     * relay.
     *
     * @param array<int, RelayOutcome> $outcomes the relays that gave no answer, by index
     * @param array<int, string>       $values   what each relay whose answer checks gives, by index
     *
     * @return int the index of the first relay whose answer is kept
     *
     * @throws BeaconUnavailable when fewer relays answered than the quorum
     * @throws BeaconRefused     when an answer does not check, or two relays disagree
     */
    private function settle(string $what, int $answers, array $outcomes, array $values, ?BeaconRefused $invalid, string $disagreement): int
    {
        $counts = array_count_values($values);
        $reference = null;

        foreach ($values as $value) {
            if (null === $reference || $counts[$value] > $counts[$reference]) {
                $reference = $value;
            }
        }

        foreach ($values as $index => $value) {
            $outcomes[$index] = $value === $reference ? RelayOutcome::Agreed : RelayOutcome::Disagreed;
        }

        $byRelay = [];
        $agreed = [];

        foreach ($this->relays as $index => $relay) {
            $byRelay[$relay] = $outcomes[$index] ?? RelayOutcome::Incomplete;

            if (RelayOutcome::Agreed === $byRelay[$relay]) {
                $agreed[] = $index;
            }
        }

        $kept = $answers >= $this->quorum && !$invalid instanceof BeaconRefused && 1 === \count($counts);
        $report = new RelayReport($what, $this->quorum, $byRelay, $kept);
        $this->events?->dispatch(new BeaconRelaysRead($this->chain->hash, $report));

        if ($answers < $this->quorum) {
            throw BeaconUnavailable::quorum($what, $answers, $this->quorum, $report);
        }

        if ($invalid instanceof BeaconRefused) {
            throw BeaconRefused::because($invalid->getMessage(), $report);
        }

        if (!$kept) {
            throw BeaconRefused::because($disagreement, $report);
        }

        return $agreed[0];
    }

    /**
     * Asks every relay at once: the bodies of those that answered `200` whole, within the time and the size allowed,
     * and the outcome of the others, both by the index of the relay.
     *
     * @return array{0: array<int, string>, 1: array<int, RelayOutcome>}
     */
    private function read(string $path): array
    {
        $responses = [];

        foreach ($this->relays as $relay) {
            $responses[] = $this->http->request('GET', $relay.'/'.$this->chain->hash.$path, [
                'headers' => ['Accept' => 'application/json'],
                'max_redirects' => 0,
                'timeout' => $this->timeout,
                'max_duration' => $this->timeout,
            ]);
        }

        return $this->bodies($responses);
    }

    /**
     * The bodies of the responses that came whole, with the status `200`, within the time and the size allowed, and
     * why the others did not, by the index of their relay. The whole read ends at the timeout on the clock, whatever
     * the transport does.
     *
     * @param list<ResponseInterface> $responses
     *
     * @return array{0: array<int, string>, 1: array<int, RelayOutcome>}
     */
    private function bodies(array $responses): array
    {
        $indexes = [];

        foreach ($responses as $index => $response) {
            $indexes[spl_object_id($response)] = $index;
        }

        $deadline = (float) $this->clock->now()->format('U.u') + $this->timeout;
        $bodies = [];
        $complete = [];
        $failed = [];

        foreach ($this->http->stream($responses, $this->timeout) as $response => $chunk) {
            $index = $indexes[spl_object_id($response)];

            if (isset($failed[$index])) {
                continue;
            }

            $outcome = $this->chunk($response, $chunk, $bodies, $index);

            if ($outcome instanceof RelayOutcome) {
                $failed[$index] = $outcome;
                $response->cancel();
            } else {
                $complete[$index] = $chunk->isLast();
            }

            if ((float) $this->clock->now()->format('U.u') > $deadline) {
                foreach ($responses as $late => $pending) {
                    if (!isset($failed[$late]) && !($complete[$late] ?? false)) {
                        $failed[$late] = RelayOutcome::TimedOut;
                        $pending->cancel();
                    }
                }

                break;
            }
        }

        $bodies = array_diff_key(array_intersect_key($bodies, array_filter($complete)), $failed);
        ksort($bodies);

        return [$bodies, $failed];
    }

    /**
     * Adds a chunk to the body of its relay, or says why the relay fails.
     *
     * @param array<int, string> $bodies
     */
    private function chunk(ResponseInterface $response, ChunkInterface $chunk, array &$bodies, int $index): ?RelayOutcome
    {
        try {
            if ($chunk->isTimeout()) {
                return RelayOutcome::TimedOut;
            }

            if ($chunk->isFirst() && 200 !== $response->getStatusCode()) {
                return RelayOutcome::Status;
            }

            $bodies[$index] = ($bodies[$index] ?? '').$chunk->getContent();

            return \strlen($bodies[$index]) > DrandResponse::MAX_BYTES ? RelayOutcome::TooLarge : null;
        } catch (TransportExceptionInterface) {
            return RelayOutcome::Unreachable;
        }
    }

    /**
     * @param list<string> $relays
     *
     * @return non-empty-list<string>
     */
    private function relays(array $relays): array
    {
        $checked = [];

        foreach ($relays as $relay) {
            try {
                $url = Url::fromString($relay);
            } catch (UrlException) {
                throw InvalidRaffle::because(\sprintf('The relay %s is not an address.', $relay));
            }

            if ('https' !== $url->scheme || null !== $url->query || null !== $url->fragment) {
                throw InvalidRaffle::because(\sprintf('A relay is an HTTPS address without a query nor a fragment, not %s.', $relay));
            }

            $checked[] = rtrim($url->toString(), '/');
        }

        $checked = array_values(array_unique($checked));

        if ([] === $checked || \count($checked) > self::MAX_RELAYS) {
            throw InvalidRaffle::because(\sprintf('A beacon reads 1 to %d distinct relays.', self::MAX_RELAYS));
        }

        return $checked;
    }
}
