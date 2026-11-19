<?php

declare(strict_types=1);

namespace Tilttek\Raffle;

use Psr\Clock\ClockInterface;
use Psr\EventDispatcher\EventDispatcherInterface;
use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\Raffle\Beacon\BeaconChain;
use Tilttek\Raffle\Contract\RaffleStore;
use Tilttek\Raffle\Contract\RandomnessBeacon;
use Tilttek\Raffle\Event\RaffleDrawn;
use Tilttek\Raffle\Exception\BeaconRefused;
use Tilttek\Raffle\Exception\BeaconUnavailable;
use Tilttek\Raffle\Exception\InvalidRaffle;
use Tilttek\Raffle\Exception\RaffleConflict;
use Tilttek\Raffle\Exception\RaffleRefused;

/**
 * Verifiable raffles, without buying chances.
 *
 * `open()` opens a raffle — with a prize, it is seeded by a public beacon; without a stake, by a seed the organiser
 * commits to at once —; `enter()` adds an entrant, once; `seal()` closes the entries, hashes them and, for a beacon,
 * fixes the first round published after a margin, all of which the organiser announces; `draw()` takes the randomness
 * — the beacon round, or the seed revealed —, draws the winners and the alternates from it, keeps the raffle drawn and
 * dispatches `RaffleDrawn`; `proof()` gives what a player checks with `RaffleVerifier`.
 *
 * The service keeps nothing between calls: the store holds the state.
 */
final readonly class Raffles
{
    /**
     * @param int $margin the seconds between the closing of the entries and the beacon round drawn from, 0 to 86 400:
     *                    room for the clocks of the server and of the beacon to differ
     */
    public function __construct(
        private RaffleStore $store,
        private ?RandomnessBeacon $beacon = null,
        private ?EventDispatcherInterface $events = null,
        private ClockInterface $clock = new SystemClock(),
        private Randomizer $secure = new Randomizer(new Secure()),
        private int $margin = 30,
    ) {
        if ($margin < 0 || $margin > 86_400) {
            throw InvalidRaffle::because('The margin before the beacon round is 0 to 86400 seconds.');
        }
    }

    /**
     * Opens a raffle. A raffle with a prize is seeded by the beacon; one without a stake by commit-reveal, unless
     * `$source` asks for the beacon.
     *
     * @throws InvalidRaffle  when the identifier or the numbers are out of range
     * @throws RaffleRefused  when a prize is asked to be seeded by commit-reveal, or no beacon is configured for it
     * @throws RaffleConflict when a raffle has the identifier already
     */
    public function open(string $id, Stake $stake, int $winners = 1, int $alternates = 0, ?SeedSource $source = null): Raffle
    {
        $source ??= Stake::Prize === $stake ? SeedSource::Beacon : SeedSource::CommitReveal;

        if (Stake::Prize === $stake && SeedSource::Beacon !== $source) {
            throw RaffleRefused::prizeNeedsBeacon();
        }

        if (SeedSource::Beacon === $source && !$this->beacon instanceof RandomnessBeacon) {
            throw RaffleRefused::noBeacon();
        }

        $seed = SeedSource::CommitReveal === $source ? sodium_bin2hex($this->secure->getBytes(32)) : null;
        $raffle = Raffle::open($id, $stake, $source, $winners, $alternates, $this->clock->now(), $seed);

        if ($this->store->find($raffle->id) instanceof Raffle) {
            throw RaffleRefused::exists($raffle->id);
        }

        $this->store->add($raffle);

        return $raffle;
    }

    /**
     * Adds an entrant: one chance, never more.
     *
     * @param string $entrant an opaque reference, published in the proof: a member or a ticket identifier, never an
     *                        address nor a name
     *
     * @throws InvalidRaffle  when the entrant is not an opaque reference
     * @throws RaffleRefused  when the raffle is unknown, its entries are closed, it is full, or the entrant is in
     * @throws RaffleConflict when another change came first
     */
    public function enter(string $id, string $entrant): void
    {
        $raffle = $this->raffle($id);
        Encoding::entrant($entrant);

        if (RaffleStatus::Open !== $raffle->status) {
            throw RaffleRefused::notOpen($id);
        }

        if ($raffle->entrantCount >= Raffle::MAX_ENTRANTS) {
            throw RaffleRefused::full($id, $raffle->entrantCount);
        }

        if (!$this->store->enter($raffle->entered(), $entrant)) {
            throw RaffleRefused::alreadyEntered($id, $entrant);
        }
    }

    /**
     * Closes the entries: they are hashed and, for a beacon, the first round published after the margin is fixed. The
     * organiser publishes the announcement before the round.
     *
     * @throws RaffleRefused     when the raffle is unknown, not open, or has no entrant
     * @throws RaffleConflict    when another change came first
     * @throws BeaconUnavailable when the beacon cannot be read
     * @throws BeaconRefused     when the beacon describes another chain
     */
    public function seal(string $id): Announcement
    {
        $raffle = $this->raffle($id);

        if (RaffleStatus::Open !== $raffle->status) {
            throw RaffleRefused::notOpen($id);
        }

        $entrants = Entrants::canonical($this->store->entrants($raffle->id));

        if ([] === $entrants) {
            throw RaffleRefused::noEntrants($id);
        }

        $now = $this->clock->now();
        $chain = SeedSource::Beacon === $raffle->source ? $this->beacon()->chain() : null;
        $round = $chain?->firstRoundAfter($now->modify(\sprintf('+%d seconds', $this->margin)));
        $sealed = $raffle->sealed(Entrants::hash($raffle->id, $entrants), $now, $chain, $round);
        $this->store->save($sealed);

        return $this->announce($sealed);
    }

    /**
     * Draws the winners and the alternates, keeps the raffle drawn and dispatches `RaffleDrawn`.
     *
     * @throws RaffleRefused     when the raffle is unknown, not sealed, drawn already, or its beacon round is to come
     * @throws RaffleConflict    when another change came first, or the entrants kept no longer hash to the hash announced
     * @throws BeaconUnavailable when the beacon round cannot be read yet
     * @throws BeaconRefused     when the beacon round does not check
     */
    public function draw(string $id): RaffleProof
    {
        $raffle = $this->raffle($id);

        if (RaffleStatus::Drawn === $raffle->status) {
            throw RaffleRefused::alreadyDrawn($id);
        }

        if (RaffleStatus::Sealed !== $raffle->status) {
            throw RaffleRefused::notSealed($id);
        }

        $now = $this->clock->now();
        $beacon = null;

        if ($raffle->chain instanceof BeaconChain && null !== $raffle->round) {
            if ($raffle->chain->firstRoundAfter($now) <= $raffle->round) {
                throw RaffleRefused::roundNotReached($id, $raffle->round, $raffle->chain->timeOf($raffle->round));
            }

            $beacon = $this->beacon()->fetch($raffle->round);

            if ($beacon->round !== $raffle->round) {
                throw BeaconRefused::because(\sprintf('The beacon gave round %d for round %d.', $beacon->round, $raffle->round));
            }
        }

        $drawn = $raffle->drawn($now, $beacon);
        $proof = $this->proofOf($drawn);
        $this->store->save($drawn);
        $this->events?->dispatch(new RaffleDrawn($drawn->id));

        return $proof;
    }

    /**
     * The proof of a drawn raffle.
     *
     * @throws RaffleRefused  when the raffle is unknown or not drawn
     * @throws RaffleConflict when the entrants kept no longer hash to the hash announced
     */
    public function proof(string $id): RaffleProof
    {
        $raffle = $this->raffle($id);

        if (RaffleStatus::Drawn !== $raffle->status) {
            throw RaffleRefused::notDrawn($id);
        }

        return $this->proofOf($raffle);
    }

    /**
     * What was announced when the entries closed.
     *
     * @throws RaffleRefused when the raffle is unknown or still open
     */
    public function announcement(string $id): Announcement
    {
        $raffle = $this->raffle($id);

        if (RaffleStatus::Open === $raffle->status) {
            throw RaffleRefused::notSealed($id);
        }

        return $this->announce($raffle);
    }

    /**
     * @throws RaffleRefused when no raffle has the identifier
     */
    public function raffle(string $id): Raffle
    {
        return $this->store->find($id) ?? throw RaffleRefused::unknown($id);
    }

    private function beacon(): RandomnessBeacon
    {
        return $this->beacon ?? throw RaffleRefused::noBeacon();
    }

    private function announce(Raffle $raffle): Announcement
    {
        return new Announcement($raffle->id, $raffle->stake, $raffle->source, $raffle->winners, $raffle->alternates, $raffle->entrantCount, (string) $raffle->entrantsHash, $raffle->sealedAt ?? $raffle->openedAt, $raffle->commitment, $raffle->chain, $raffle->round);
    }

    private function proofOf(Raffle $raffle): RaffleProof
    {
        $entrants = Entrants::canonical($this->store->entrants($raffle->id));
        $hash = (string) $raffle->entrantsHash;

        if (!hash_equals($hash, Entrants::hash($raffle->id, $entrants))) {
            throw RaffleConflict::entrantsChanged($raffle->id);
        }

        $seed = Entrants::seed($raffle->id, $hash, $raffle->beacon->randomness ?? (string) $raffle->seed);

        return new RaffleProof(
            $raffle->id,
            $raffle->stake,
            $raffle->winners,
            $raffle->alternates,
            $entrants,
            $hash,
            $raffle->source,
            $raffle->commitment,
            $raffle->seed,
            $raffle->chain,
            $raffle->beacon,
            $seed,
            Selection::draw($seed, $entrants, $raffle->draws()),
            $raffle->sealedAt ?? $raffle->openedAt,
            $raffle->drawnAt ?? $raffle->openedAt,
        );
    }
}
