<?php

declare(strict_types=1);

namespace Tilttek\InitiativeCards;

use Random\Engine\Secure;
use Random\Randomizer;
use Tilttek\CardDeck\Card;
use Tilttek\CardDeck\CardId;
use Tilttek\CardDeck\Deck;
use Tilttek\CardDeck\Face;
use Tilttek\CardDeck\Shuffler;
use Tilttek\InitiativeCards\Contract\CardRanking;
use Tilttek\InitiativeCards\Contract\EncounterStore;
use Tilttek\InitiativeCards\Contract\InitiativeObserver;
use Tilttek\InitiativeCards\Event\CombatantDefeated;
use Tilttek\InitiativeCards\Event\CombatantHidden;
use Tilttek\InitiativeCards\Event\CombatantJoined;
use Tilttek\InitiativeCards\Event\CombatantLeft;
use Tilttek\InitiativeCards\Event\CombatantRevealed;
use Tilttek\InitiativeCards\Event\CombatantRevived;
use Tilttek\InitiativeCards\Event\DeckReshuffled;
use Tilttek\InitiativeCards\Event\EncounterEnded;
use Tilttek\InitiativeCards\Event\EncounterStarted;
use Tilttek\InitiativeCards\Event\InitiativeEvent;
use Tilttek\InitiativeCards\Event\RoundDealt;
use Tilttek\InitiativeCards\Event\RoundEnded;
use Tilttek\InitiativeCards\Event\TurnEnded;
use Tilttek\InitiativeCards\Event\TurnStarted;
use Tilttek\InitiativeCards\Exception\EncounterConflict;
use Tilttek\InitiativeCards\Exception\EncounterNotFound;
use Tilttek\InitiativeCards\Exception\InitiativeRefused;
use Tilttek\InitiativeCards\Exception\InvalidEncounter;
use Tilttek\InitiativeCards\Internal\Identifier;
use Tilttek\InitiativeCards\View\InitiativeView;
use Tilttek\InitiativeCards\View\Slot;

/**
 * The service of the initiative: it starts an encounter on a shuffled deck, deals a card to every combatant standing at
 * each round and orders their turns by the cards, lets the controller of the current combatant end its turn — and name
 * the next one — and lets the game master add, remove, defeat, revive, hide and reveal combatants. It reads from the
 * store, changes, keeps, then tells the observer what happened, each fact once. It keeps no state between calls.
 *
 * The game master changes the encounter; a player only ends the turns of the combatants they control.
 */
final readonly class InitiativeTracker
{
    private Randomizer $randomizer;

    /**
     * @param ?Randomizer $randomizer the randomizer of the shuffles; by default one on the `Secure` engine, which keeps no
     *                                state between calls
     */
    public function __construct(
        private EncounterStore $store,
        private CardRanking $ranking = new StandardRanking(),
        private ?InitiativeObserver $observer = null,
        ?Randomizer $randomizer = null,
    ) {
        $this->randomizer = $randomizer ?? new Randomizer(new Secure());
    }

    /**
     * @throws InvalidEncounter  when the identifier is not one
     * @throws EncounterNotFound when no encounter has it
     */
    public function encounter(string $id): Encounter
    {
        return $this->store->encounter(Identifier::check($id, 'an encounter')) ?? throw EncounterNotFound::encounter($id);
    }

    /**
     * Starts an encounter: the deck shuffled into the draw pile, the combatants in the order given, no card dealt yet.
     *
     * @param list<Combatant> $combatants up to 100, each without a card
     *
     * @throws InvalidEncounter  when an identifier is not one, a combatant comes twice or holds a card, there are too
     *                           many, or the ranking does not know a face of the deck
     * @throws InitiativeRefused when an encounter has the identifier
     * @throws EncounterConflict when an encounter of the identifier was kept meanwhile
     */
    public function start(string $id, string $gameMaster, Deck $deck, array $combatants = []): Encounter
    {
        foreach ($deck->cards() as $card) {
            foreach ($card->faces as $face) {
                if (!$this->ranking->ranks($face)) {
                    throw InvalidEncounter::because(\sprintf('The ranking does not know the face %s.', $face->key));
                }
            }
        }

        foreach ($combatants as $combatant) {
            if (null !== $combatant->card || $combatant->acted) {
                throw InvalidEncounter::because('A combatant joins without a card.');
            }
        }

        $pile = Shuffler::shuffle(array_map(static fn (Card $card): string => $card->id->value, $deck->cards()), $this->randomizer);
        $encounter = new Encounter($id, $gameMaster, $deck, $combatants, $pile);
        if ($this->store->encounter($id) instanceof Encounter) {
            throw InitiativeRefused::because(\sprintf('An encounter has the identifier %s.', $id));
        }

        $kept = $this->store->save($encounter);
        $this->tell(new EncounterStarted($kept->id));

        return $kept;
    }

    /**
     * Deals the next round, ending the current one: the cards held go to the discard pile — which is shuffled back into
     * the draw pile when a card that reshuffles was dealt, or when the draw pile is too short — then every combatant not
     * defeated draws its cards from the top and keeps the best. The turns follow the cards; the combatants who tie keep
     * their previous order, and the defeated, who hold no card, come last.
     *
     * @throws EncounterNotFound when no encounter has the identifier
     * @throws InitiativeRefused when the member is not the game master, the encounter has ended, or the deck holds too
     *                           few cards for the combatants
     * @throws EncounterConflict when the encounter changed since it was read
     */
    public function deal(string $id, string $by): Encounter
    {
        $before = $this->open($id, $by);
        $events = [];
        $discard = $before->discard;
        $combatants = [];
        foreach ($before->combatants as $combatant) {
            if (null !== $combatant->card) {
                $discard[] = $combatant->card;
            }

            $combatants[] = $combatant->withCard(null);
        }

        $needed = array_sum(array_map(static fn (Combatant $one): int => $one->defeated ? 0 : $one->draws, $combatants));
        $pile = $before->drawPile;
        if ($before->reshuffle || \count($pile) < $needed) {
            $pile = Shuffler::shuffle([...$pile, ...$discard], $this->randomizer);
            $discard = [];
            $events[] = new DeckReshuffled($before->id);
        }

        if (\count($pile) < $needed) {
            throw InitiativeRefused::because(\sprintf('The deck holds %d cards, and the combatants draw %d.', \count($pile), $needed));
        }

        $reshuffle = false;
        $dealt = [];
        foreach ($combatants as $combatant) {
            if ($combatant->defeated) {
                $dealt[] = $combatant;
                continue;
            }

            [$card, $pile, $discard] = $this->draw($before->deck, $combatant->draws, $pile, $discard);
            $reshuffle = $reshuffle || $this->ranking->reshuffles($this->face($before->deck, $card));
            $dealt[] = $combatant->withCard($card);
        }

        $after = $before->with($this->ordered($before->deck, $dealt), $pile, $discard, $before->round + 1, $reshuffle);
        $kept = $this->store->save($after);
        $this->tellAll([...$events, new RoundDealt($kept->id, $kept->round), $this->turnOf($kept)]);

        return $kept;
    }

    /**
     * Ends the turn of the current combatant, by its controller or the game master. The next turn is the next card's,
     * or that of the combatant named, who has not acted yet in the round: the order of the others stays.
     *
     * @throws EncounterNotFound when no encounter or combatant has the identifier
     * @throws InitiativeRefused when the encounter has ended, the combatant does not hold the turn, the member controls
     *                           it not, or the combatant named is not waiting for its turn
     * @throws EncounterConflict when the encounter changed since it was read
     */
    public function endTurn(string $id, string $combatant, string $by, ?string $next = null): Encounter
    {
        $before = $this->running($id);
        $current = $before->current();
        if (!$current instanceof Combatant || $current->id !== $combatant) {
            throw InitiativeRefused::because(\sprintf('The combatant %s does not hold the turn.', $combatant));
        }

        if ($by !== $current->controller && $by !== $before->gameMaster) {
            throw InitiativeRefused::because('Only the controller of a combatant or the game master ends its turn.');
        }

        $combatants = $this->replace($before->combatants, $current->withActed(true));
        if (null !== $next) {
            $named = $before->combatant($next) ?? throw EncounterNotFound::combatant($before->id, $next);
            if (!$named->isPending() || $named->id === $current->id) {
                throw InitiativeRefused::because(\sprintf('The combatant %s is not waiting for its turn.', $next));
            }

            $combatants = $this->moveAfter($combatants, $named, $current->id);
        }

        $kept = $this->store->save($before->with($combatants));
        $this->tellAll([new TurnEnded($kept->id, $current->id), $this->turnOf($kept)]);

        return $kept;
    }

    /**
     * Adds a combatant. In a round, it draws its cards at once and waits among the combatants who have not acted, after
     * the one holding the turn, in the order of the cards.
     *
     * @throws EncounterNotFound when no encounter has the identifier
     * @throws InvalidEncounter  when the combatant holds a card, or the encounter would hold too many
     * @throws InitiativeRefused when the member is not the game master, the encounter has ended, a combatant has the
     *                           identifier, or the draw pile and discard pile hold too few cards
     * @throws EncounterConflict when the encounter changed since it was read
     */
    public function join(string $id, Combatant $combatant, string $by): Encounter
    {
        $before = $this->open($id, $by);
        if (null !== $combatant->card || $combatant->acted) {
            throw InvalidEncounter::because('A combatant joins without a card.');
        }

        if ($before->combatant($combatant->id) instanceof Combatant) {
            throw InitiativeRefused::because(\sprintf('The encounter has a combatant %s.', $combatant->id));
        }

        $events = [];
        if (0 === $before->round || $combatant->defeated) {
            $after = $before->with([...$before->combatants, $combatant]);
        } else {
            $pile = $before->drawPile;
            $discard = $before->discard;
            if (\count($pile) < $combatant->draws) {
                $pile = Shuffler::shuffle([...$pile, ...$discard], $this->randomizer);
                $discard = [];
                $events[] = new DeckReshuffled($before->id);
            }

            if (\count($pile) < $combatant->draws) {
                throw InitiativeRefused::because(\sprintf('The deck holds %d cards to draw, and the combatant draws %d.', \count($pile), $combatant->draws));
            }

            [$card, $pile, $discard] = $this->draw($before->deck, $combatant->draws, $pile, $discard);
            $reshuffle = $before->reshuffle || $this->ranking->reshuffles($this->face($before->deck, $card));
            $after = $before->with($this->insert($before, $combatant->withCard($card)), $pile, $discard, null, $reshuffle);
        }

        $current = $before->current();
        $kept = $this->store->save($after);
        $events[] = new CombatantJoined($kept->id, $combatant->id, $combatant->hidden);
        if (!$current instanceof Combatant && $kept->current() instanceof Combatant) {
            $events[] = $this->turnOf($kept);
        }

        $this->tellAll($events);

        return $kept;
    }

    /**
     * Removes a combatant; its card goes to the discard pile. When it held the turn, the turn passes on.
     *
     * @throws EncounterNotFound when no encounter or combatant has the identifier
     * @throws InitiativeRefused when the member is not the game master, or the encounter has ended
     * @throws EncounterConflict when the encounter changed since it was read
     */
    public function leave(string $id, string $combatant, string $by): Encounter
    {
        $before = $this->open($id, $by);
        $leaving = $before->combatant($combatant) ?? throw EncounterNotFound::combatant($before->id, $combatant);
        $discard = null === $leaving->card ? $before->discard : [...$before->discard, $leaving->card];
        $combatants = array_values(array_filter($before->combatants, static fn (Combatant $one): bool => $one->id !== $leaving->id));
        $kept = $this->store->save($before->with($combatants, null, $discard));
        $this->tellAll([new CombatantLeft($kept->id, $leaving->id), ...$this->passedOn($before, $kept, $leaving)]);

        return $kept;
    }

    /**
     * Marks a combatant defeated: it stays in the order, faded, keeps its card for the round, and its turns are skipped.
     * When it held the turn, the turn passes on. Defeating it again changes nothing.
     *
     * @throws EncounterNotFound when no encounter or combatant has the identifier
     * @throws InitiativeRefused when the member is not the game master, or the encounter has ended
     * @throws EncounterConflict when the encounter changed since it was read
     */
    public function defeat(string $id, string $combatant, string $by): Encounter
    {
        $before = $this->open($id, $by);
        $target = $before->combatant($combatant) ?? throw EncounterNotFound::combatant($before->id, $combatant);
        if ($target->defeated) {
            return $before;
        }

        $kept = $this->store->save($before->with($this->replace($before->combatants, $target->withDefeated(true))));
        $this->tellAll([new CombatantDefeated($kept->id, $target->id), ...$this->passedOn($before, $kept, $target)]);

        return $kept;
    }

    /**
     * Stands a defeated combatant again. Its card, when it holds one, keeps its place: a place the turn has passed waits
     * for the next round, and a place still to come gives it its turn in this one.
     *
     * @throws EncounterNotFound when no encounter or combatant has the identifier
     * @throws InitiativeRefused when the member is not the game master, or the encounter has ended
     * @throws EncounterConflict when the encounter changed since it was read
     */
    public function revive(string $id, string $combatant, string $by): Encounter
    {
        $before = $this->open($id, $by);
        $target = $before->combatant($combatant) ?? throw EncounterNotFound::combatant($before->id, $combatant);
        if (!$target->defeated) {
            return $before;
        }

        $revived = $target->withDefeated(false)->withActed($target->acted || $this->passed($before, $target));
        $kept = $this->store->save($before->with($this->replace($before->combatants, $revived)));
        $events = [new CombatantRevived($kept->id, $target->id)];
        if (!$before->current() instanceof Combatant && $kept->current() instanceof Combatant) {
            $events[] = $this->turnOf($kept);
        }

        $this->tellAll($events);

        return $kept;
    }

    /**
     * Shows a hidden combatant to the players, or hides one from them. Asking for the state it is in changes nothing.
     *
     * @throws EncounterNotFound when no encounter or combatant has the identifier
     * @throws InitiativeRefused when the member is not the game master, or the encounter has ended
     * @throws EncounterConflict when the encounter changed since it was read
     */
    public function reveal(string $id, string $combatant, string $by, bool $revealed = true): Encounter
    {
        $before = $this->open($id, $by);
        $target = $before->combatant($combatant) ?? throw EncounterNotFound::combatant($before->id, $combatant);
        if ($target->hidden !== $revealed) {
            return $before;
        }

        $kept = $this->store->save($before->with($this->replace($before->combatants, $target->withHidden(!$revealed))));
        $this->tell($revealed ? new CombatantRevealed($kept->id, $target->id) : new CombatantHidden($kept->id, $target->id));

        return $kept;
    }

    /**
     * Ends the encounter: it changes no more.
     *
     * @throws EncounterNotFound when no encounter has the identifier
     * @throws InitiativeRefused when the member is not the game master, or the encounter has ended
     * @throws EncounterConflict when the encounter changed since it was read
     */
    public function end(string $id, string $by): Encounter
    {
        $before = $this->open($id, $by);
        $kept = $this->store->save($before->with($before->combatants, null, null, null, null, true));
        $this->tell(new EncounterEnded($kept->id));

        return $kept;
    }

    /**
     * What the viewer may see: every combatant for the game master, the combatants not hidden for anyone else.
     *
     * @param ?string $viewer the member who looks, or null for a spectator
     *
     * @throws EncounterNotFound when no encounter has the identifier
     */
    public function view(string $id, ?string $viewer = null): InitiativeView
    {
        $encounter = $this->encounter($id);
        $all = $viewer === $encounter->gameMaster;
        $current = $encounter->current();
        $slots = [];
        foreach ($encounter->combatants as $combatant) {
            if ($combatant->hidden && !$all) {
                continue;
            }

            $slots[] = new Slot(
                $combatant->id,
                null === $combatant->card ? null : $this->face($encounter->deck, $combatant->card)->key,
                $combatant->acted,
                $combatant->defeated,
                $current === $combatant,
                $combatant->hidden,
            );
        }

        return new InitiativeView($encounter->id, $encounter->round, $slots, \count($encounter->drawPile), $encounter->ended, $encounter->version);
    }

    /**
     * @throws EncounterNotFound when no encounter has the identifier
     * @throws InitiativeRefused when the encounter has ended
     */
    private function running(string $id): Encounter
    {
        $encounter = $this->encounter($id);
        if ($encounter->ended) {
            throw InitiativeRefused::because(\sprintf('The encounter %s has ended.', $encounter->id));
        }

        return $encounter;
    }

    /**
     * @throws EncounterNotFound when no encounter has the identifier
     * @throws InitiativeRefused when the member is not the game master, or the encounter has ended
     */
    private function open(string $id, string $by): Encounter
    {
        $encounter = $this->running($id);
        if ($by !== $encounter->gameMaster) {
            throw InitiativeRefused::because('Only the game master changes the encounter.');
        }

        return $encounter;
    }

    /**
     * Draws so many cards from the top of the pile and keeps the best; the others go to the discard pile.
     *
     * @param list<string> $pile
     * @param list<string> $discard
     *
     * @return array{string, list<string>, list<string>}
     */
    private function draw(Deck $deck, int $draws, array $pile, array $discard): array
    {
        $drawn = \array_slice($pile, 0, $draws);
        usort($drawn, fn (string $one, string $other): int => $this->ranking->compare($this->face($deck, $one), $this->face($deck, $other)));
        $best = array_shift($drawn) ?? throw InitiativeRefused::because('The draw pile is empty.');

        return [$best, \array_slice($pile, $draws), [...$discard, ...$drawn]];
    }

    /**
     * The combatants by their cards, the ties in their order, those without a card last.
     *
     * @param list<Combatant> $combatants
     *
     * @return list<Combatant>
     */
    private function ordered(Deck $deck, array $combatants): array
    {
        usort($combatants, fn (Combatant $one, Combatant $other): int => $this->compare($deck, $one, $other));

        return $combatants;
    }

    private function compare(Deck $deck, Combatant $one, Combatant $other): int
    {
        if (null === $one->card || null === $other->card) {
            return (null === $one->card) <=> (null === $other->card);
        }

        return $this->ranking->compare($this->face($deck, $one->card), $this->face($deck, $other->card));
    }

    /**
     * The combatants with one who joined in a round: after those who acted and the one holding the turn, among the
     * others in the order of the cards.
     *
     * @return list<Combatant>
     */
    private function insert(Encounter $encounter, Combatant $joining): array
    {
        $current = $encounter->current();
        $combatants = $encounter->combatants;
        $place = \count($combatants);
        foreach ($combatants as $index => $combatant) {
            if ($combatant->isPending() && $combatant !== $current && $this->compare($encounter->deck, $joining, $combatant) < 0) {
                $place = $index;
                break;
            }
        }

        array_splice($combatants, $place, 0, [$joining]);

        return $combatants;
    }

    /**
     * @param list<Combatant> $combatants
     *
     * @return list<Combatant>
     */
    private function replace(array $combatants, Combatant $changed): array
    {
        return array_map(static fn (Combatant $one): Combatant => $one->id === $changed->id ? $changed : $one, $combatants);
    }

    /**
     * The combatants with the one named moved right after another.
     *
     * @param list<Combatant> $combatants
     *
     * @return list<Combatant>
     */
    private function moveAfter(array $combatants, Combatant $moved, string $after): array
    {
        $result = [];
        foreach ($combatants as $one) {
            if ($one->id === $moved->id) {
                continue;
            }

            $result[] = $one;
            if ($one->id === $after) {
                $result[] = $moved;
            }
        }

        return $result;
    }

    /**
     * Whether the turn has passed the place of the combatant's card: a combatant after it has acted, or holds the turn.
     */
    private function passed(Encounter $encounter, Combatant $target): bool
    {
        if (null === $target->card) {
            return false;
        }

        $current = $encounter->current();
        $after = false;
        foreach ($encounter->combatants as $combatant) {
            if ($after && ($combatant->acted || $combatant === $current)) {
                return true;
            }

            $after = $after || $combatant->id === $target->id;
        }

        return false;
    }

    /**
     * The events of the turn passing on, when the combatant changed held it.
     *
     * @return list<InitiativeEvent>
     */
    private function passedOn(Encounter $before, Encounter $kept, Combatant $changed): array
    {
        return $before->current()?->id === $changed->id ? [$this->turnOf($kept)] : [];
    }

    /**
     * The turn that starts, or the end of the round when every combatant standing has acted. It is told once a round
     * was dealt, never before the first.
     */
    private function turnOf(Encounter $encounter): InitiativeEvent
    {
        $current = $encounter->current();

        return $current instanceof Combatant ? new TurnStarted($encounter->id, $current->id, $current->hidden) : new RoundEnded($encounter->id, $encounter->round);
    }

    private function face(Deck $deck, string $card): Face
    {
        return $deck->card(new CardId($card))->faces[0];
    }

    /**
     * @param list<InitiativeEvent> $events
     */
    private function tellAll(array $events): void
    {
        foreach ($events as $event) {
            $this->tell($event);
        }
    }

    private function tell(InitiativeEvent $event): void
    {
        $this->observer?->notify($event);
    }
}
