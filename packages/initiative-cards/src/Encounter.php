<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards;

use Tilttek\CardDeck\Deck;
use Tilttek\InitiativeCards\Exception\InvalidEncounter;
use Tilttek\InitiativeCards\Internal\Identifier;

/**
 * An encounter: its game master, its deck, the draw and discard piles, its combatants in the order of their turns, the
 * round and whether a card that reshuffles was dealt in it, whether it has ended, and its version in the store.
 *
 * The combatants are in turn order: before the first round, in the order they joined; after a deal, by their cards,
 * the defeated who hold none last. The current combatant is the first one whose turn may come.
 */
final readonly class Encounter
{
    /** The most combatants of an encounter. */
    public const int MAX_COMBATANTS = 100;

    /** @var list<Combatant> */
    public array $combatants;

    /**
     * @param list<Combatant> $combatants in turn order, each identifier once
     * @param list<string>    $drawPile   the identifiers of the cards to draw, the top first
     * @param list<string>    $discard    the identifiers of the discarded cards
     * @param int             $round      the round dealt, 0 before the first
     * @param bool            $reshuffle  whether a card that reshuffles was dealt in the round
     *
     * @throws InvalidEncounter when an identifier is not one, a combatant comes twice, there are too many, or the round
     *                          or version is below 0
     */
    public function __construct(
        public string $id,
        public string $gameMaster,
        public Deck $deck,
        array $combatants,
        public array $drawPile,
        public array $discard = [],
        public int $round = 0,
        public bool $reshuffle = false,
        public bool $ended = false,
        public int $version = 0,
    ) {
        Identifier::check($id, 'an encounter');
        Identifier::check($gameMaster, 'a member');
        if (\count($combatants) > self::MAX_COMBATANTS) {
            throw InvalidEncounter::because(\sprintf('An encounter holds at most %d combatants.', self::MAX_COMBATANTS));
        }

        $seen = [];
        foreach ($combatants as $combatant) {
            if (isset($seen[$combatant->id])) {
                throw InvalidEncounter::because(\sprintf('The combatant %s comes twice in the encounter.', $combatant->id));
            }

            $seen[$combatant->id] = $combatant->id;
        }

        if ($round < 0 || $version < 0) {
            throw InvalidEncounter::because('The round and the version of an encounter are 0 or more.');
        }

        $this->combatants = $combatants;
    }

    /**
     * The combatant, or null when the encounter has none of the identifier.
     */
    public function combatant(string $id): ?Combatant
    {
        foreach ($this->combatants as $combatant) {
            if ($combatant->id === $id) {
                return $combatant;
            }
        }

        return null;
    }

    /**
     * The combatant whose turn it is, or null before the first round, once every one has acted, or once ended.
     */
    public function current(): ?Combatant
    {
        if ($this->ended) {
            return null;
        }

        foreach ($this->combatants as $combatant) {
            if ($combatant->isPending()) {
                return $combatant;
            }
        }

        return null;
    }

    /**
     * @param list<Combatant> $combatants
     * @param list<string>    $drawPile
     * @param list<string>    $discard
     */
    public function with(array $combatants, ?array $drawPile = null, ?array $discard = null, ?int $round = null, ?bool $reshuffle = null, ?bool $ended = null): self
    {
        return new self(
            $this->id,
            $this->gameMaster,
            $this->deck,
            $combatants,
            $drawPile ?? $this->drawPile,
            $discard ?? $this->discard,
            $round ?? $this->round,
            $reshuffle ?? $this->reshuffle,
            $ended ?? $this->ended,
            $this->version,
        );
    }

    public function withVersion(int $version): self
    {
        return new self($this->id, $this->gameMaster, $this->deck, $this->combatants, $this->drawPile, $this->discard, $this->round, $this->reshuffle, $this->ended, $version);
    }
}
