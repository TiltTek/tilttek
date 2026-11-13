<?php

declare(strict_types=1);

namespace Tilttek\DeckBuilder;

use DateTimeInterface;
use Tilttek\DeckBuilder\Contract\DeckStore;
use Tilttek\DeckBuilder\Exception\DeckConflict;
use Tilttek\DeckBuilder\Exception\InvalidDeck;
use Tilttek\DeckBuilder\Exception\UnknownDeck;

/**
 * The history of the decks: each change saved as a new version, never rewritten, the differences between two
 * versions, and copies that link to the version they came from. It keeps nothing between two calls.
 */
final readonly class Decks
{
    public function __construct(private DeckStore $store)
    {
    }

    /**
     * Saves the deck as its next version; a deck that has not changed since its latest version keeps that version.
     *
     * @throws InvalidDeck  when the note is refused
     * @throws DeckConflict when another request saved the same version first
     */
    public function save(Deck $deck, DateTimeInterface $at, ?string $note = null): DeckVersion
    {
        $latest = $this->store->latest($deck->id);

        if ($latest instanceof DeckVersion && $latest->deck->equals($deck)) {
            return $latest;
        }

        $version = new DeckVersion($deck, $latest instanceof DeckVersion ? $latest->version + 1 : 1, $at, $note);

        if (!$this->store->add($version)) {
            throw DeckConflict::version($deck->id, $version->version);
        }

        return $version;
    }

    /**
     * @throws UnknownDeck when the deck has no saved version
     */
    public function latest(string $deck): DeckVersion
    {
        return $this->store->latest($deck) ?? throw UnknownDeck::named($deck);
    }

    /**
     * @throws UnknownDeck when the deck has no such version
     */
    public function version(string $deck, int $version): DeckVersion
    {
        return $this->store->version($deck, $version) ?? throw UnknownDeck::version($deck, $version);
    }

    /**
     * Every version of the deck, from the first to the latest.
     *
     * @return non-empty-list<DeckVersion>
     *
     * @throws UnknownDeck when the deck has no saved version
     */
    public function history(string $deck): array
    {
        $versions = $this->store->versions($deck);

        return [] === $versions ? throw UnknownDeck::named($deck) : $versions;
    }

    /**
     * The differences from one version of a deck to another.
     *
     * @throws UnknownDeck when the deck has no such version
     */
    public function diff(string $deck, int $from, int $to): DeckDiff
    {
        return DeckDiff::between($this->version($deck, $from)->deck, $this->version($deck, $to)->deck);
    }

    /**
     * Copies a version of a deck — its latest by default — as the first version of a new deck that links to it.
     *
     * @throws UnknownDeck  when the deck has no such version
     * @throws InvalidDeck  when the identifier or the name is refused
     * @throws DeckConflict when a deck of the new identifier exists
     */
    public function copy(string $deck, string $as, string $name, DateTimeInterface $at, ?int $version = null): DeckVersion
    {
        $original = null === $version ? $this->latest($deck) : $this->version($deck, $version);
        $copy = $original->deck->copiedAs($as, $name, $original->reference());

        if ($this->store->latest($copy->id) instanceof DeckVersion) {
            throw DeckConflict::exists($copy->id);
        }

        $first = new DeckVersion($copy, 1, $at);

        if (!$this->store->add($first)) {
            throw DeckConflict::exists($copy->id);
        }

        return $first;
    }
}
