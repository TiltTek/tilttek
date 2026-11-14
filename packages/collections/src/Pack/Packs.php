<?php

declare(strict_types=1);

namespace Tilttek\Collections\Pack;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Random\Engine\Xoshiro256StarStar;
use Random\Randomizer;
use Tilttek\Collections\CollectionKey;
use Tilttek\Collections\Collections;
use Tilttek\Collections\Exception\CollectionRefused;
use Tilttek\Collections\Exception\InvalidCollection;
use Tilttek\Collections\Exception\NotFound;
use Tilttek\Collections\Odds;
use Tilttek\Collections\Pack;
use Tilttek\Collections\Source;

/**
 * Packs opened at random, with published odds and a draw anyone can check, in two steps: `commit()` draws a secret seed
 * from the injected source and gives its commitment, which the site shows the member; the member then gives a client
 * seed of their own, and `open()` draws the items from both and the key. Since the site's seed was fixed before the
 * member's was known, and the member's chosen without knowing the site's, neither could choose the draw; the opening
 * keeps both seeds so that it can be replayed.
 */
final readonly class Packs
{
    public const int MIN_SEED_LENGTH = 16;

    public const int MAX_SEED_LENGTH = 64;

    /** The longest client seed, in bytes. */
    public const int MAX_CLIENT_SEED = 256;

    public function __construct(
        private Collections $collections,
        private OpeningStore $openings,
        private SeedSource $seeds,
        private ClockInterface $clock,
    ) {
    }

    /**
     * The odds a pack publishes: of each rarity and of each item.
     *
     * @throws NotFound when the catalog has no such pack
     */
    public function odds(string $pack): Odds
    {
        return $this->oddsOf($this->collections->catalog->catalog()->pack($pack));
    }

    /**
     * The commitment of a seed: its SHA-256 in hexadecimal, which a site shows before the opening so that the member
     * can check afterwards the seed was not chosen after the fact.
     */
    public static function commitment(string $seed): string
    {
        return hash('sha256', $seed);
    }

    /**
     * Checks a client seed: 1 to 256 bytes of UTF-8 text, so that an opening writes out as JSON.
     *
     * @throws InvalidCollection
     */
    public static function checkClientSeed(string $clientSeed): void
    {
        if ('' === $clientSeed || \strlen($clientSeed) > self::MAX_CLIENT_SEED || 1 !== preg_match('//u', $clientSeed)) {
            throw InvalidCollection::because(\sprintf('A client seed is 1 to %d bytes of UTF-8 text.', self::MAX_CLIENT_SEED));
        }
    }

    /**
     * Commits the opening of a pack for a member under a key: a seed drawn from the source, kept secret by the site,
     * and its commitment, shown to the member before they give their client seed. Nothing is given yet.
     *
     * @throws NotFound          when the catalog has no such pack
     * @throws InvalidCollection when the key does not read, or the source gives a seed of a wrong length
     */
    public function commit(ResourceReference $member, string $pack, string $key): PendingOpening
    {
        CollectionKey::fromString($key);
        $this->collections->catalog->catalog()->pack($pack);
        $seed = $this->seeds->seed();
        $this->checkSeed($seed);

        return new PendingOpening($key, $member, $pack, sodium_bin2hex($seed));
    }

    /**
     * Opens a committed pack with the client seed the member gave after seeing the commitment: draws its items, reserves
     * the key by adding the opening with no copies, then gives a copy of each item, under the key followed by `#` and
     * the place of the item in the pack. The same pending opening and client seed opened again return the opening they
     * made — and give the copies of one whose giving was interrupted. An opening that loses the race for its key to
     * another at the same moment draws nothing of its own: it returns the winner complete — giving, under the same
     * keys, the copies the winner may not have given yet —, or refuses as a later opening would.
     *
     * @throws NotFound          when the catalog has no such pack
     * @throws InvalidCollection when the client seed is empty, longer than 256 bytes or not UTF-8 text
     * @throws CollectionRefused when the key opened another pack, for another member, or from another seed or client
     *                           seed
     */
    public function open(PendingOpening $pending, string $clientSeed, ?DateTimeImmutable $at = null): PackOpening
    {
        self::checkClientSeed($clientSeed);
        $collectionKey = CollectionKey::fromString($pending->key);
        $opened = $this->openings->find($pending->key);

        if ($opened instanceof PackOpening) {
            $this->checkSame($opened, $pending, $clientSeed);

            return [] === $opened->copies ? $this->give($opened, $collectionKey) : $opened;
        }

        $seed = sodium_hex2bin($pending->seed);
        $items = $this->draw($this->collections->catalog->catalog()->pack($pending->pack), $seed, $clientSeed, $pending->key);
        $reserved = new PackOpening($pending->key, $pending->member, $pending->pack, $pending->seed, self::commitment($seed), $clientSeed, $items, [], $at ?? $this->clock->now());

        if (!$this->openings->add($reserved)) {
            $winner = $this->openings->find($pending->key) ?? throw CollectionRefused::because(\sprintf('The key "%s" is being opened by another request.', $pending->key));
            $this->checkSame($winner, $pending, $clientSeed);

            // The winner may still be giving its copies, or have stopped: the copies are given under their keys, which
            // give each one once, so the opening returned is always complete.
            return [] === $winner->copies ? $this->give($winner, $collectionKey) : $winner;
        }

        return $this->give($reserved, $collectionKey);
    }

    /**
     * Whether an opening is the draw its seeds give: the commitment is the seed's, and the items are those the pack,
     * as the catalog holds it now, draws from the seed, the client seed and the key.
     */
    public function verify(PackOpening $opening): bool
    {
        $seed = sodium_hex2bin($opening->seed);

        return hash_equals(self::commitment($seed), $opening->commitment)
            && $this->draw($this->collections->catalog->catalog()->pack($opening->pack), $seed, $opening->clientSeed, $opening->key) === $opening->items;
    }

    /**
     * A member's openings.
     *
     * @return list<PackOpening>
     */
    public function openingsOf(ResourceReference $member): array
    {
        return $this->openings->ofMember($member);
    }

    /**
     * The codes of the items a pack draws from a seed, a client seed and a key, in order: `Xoshiro256**` seeded with
     * the HMAC-SHA256, under the seed, of the client seed, a line feed and the key.
     *
     * @return list<string>
     */
    private function draw(Pack $pack, string $seed, string $clientSeed, string $key): array
    {
        $odds = $this->oddsOf($pack);
        $randomizer = new Randomizer(new Xoshiro256StarStar(hash_hmac('sha256', $clientSeed."\n".$key, $seed, true)));

        return array_map(static fn (int $slot): string => $odds->draw($randomizer)->code, range(1, $pack->size));
    }

    /**
     * @throws CollectionRefused when the opening kept under the key is not the one the pending opening and the client
     *                           seed make
     */
    private function checkSame(PackOpening $opened, PendingOpening $pending, string $clientSeed): void
    {
        if ($opened->pack !== $pending->pack || !$opened->member->equals($pending->member)) {
            throw CollectionRefused::because(\sprintf('The key "%s" already opened another pack.', $pending->key));
        }

        if (!hash_equals($opened->seed, $pending->seed) || !hash_equals($opened->clientSeed, $clientSeed)) {
            throw CollectionRefused::because(\sprintf('The key "%s" was opened from another commitment or client seed.', $pending->key));
        }
    }

    /**
     * Gives a copy of each item of the opening reserved, at the moment it was opened, and records them.
     */
    private function give(PackOpening $opening, CollectionKey $key): PackOpening
    {
        $copies = [];

        foreach ($opening->items as $place => $item) {
            $copies[] = $this->collections->grant($opening->member, $item, Source::Pack, $key->derive(\sprintf('%d', $place + 1)), $opening->openedAt)->id;
        }

        $this->openings->complete($opening->key, $copies);

        return $opening->withCopies($copies);
    }

    /**
     * @throws InvalidCollection when the seed is not 16 to 64 bytes
     */
    private function checkSeed(string $seed): void
    {
        if (\strlen($seed) < self::MIN_SEED_LENGTH || \strlen($seed) > self::MAX_SEED_LENGTH) {
            throw InvalidCollection::because(\sprintf('A seed is %d to %d bytes; this one is %d.', self::MIN_SEED_LENGTH, self::MAX_SEED_LENGTH, \strlen($seed)));
        }
    }

    private function oddsOf(Pack $pack): Odds
    {
        return $this->collections->catalog->catalog()->odds($pack->sets, $pack->weights);
    }
}
