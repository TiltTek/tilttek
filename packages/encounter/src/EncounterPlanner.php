<?php

declare(strict_types=1);

namespace Tilttek\Encounter;

use Tilttek\Encounter\Contract\EncounterObserver;
use Tilttek\Encounter\Contract\EncounterStore;
use Tilttek\Encounter\Contract\InitiativeHandOff;
use Tilttek\Encounter\Difficulty\Assessment;
use Tilttek\Encounter\Event\EncounterEvent;
use Tilttek\Encounter\Event\EncounterForgotten;
use Tilttek\Encounter\Event\EncounterLaunched;
use Tilttek\Encounter\Event\EncounterPrepared;
use Tilttek\Encounter\Event\EncounterRetitled;
use Tilttek\Encounter\Event\ParticipantAdded;
use Tilttek\Encounter\Event\ParticipantChanged;
use Tilttek\Encounter\Event\ParticipantMoved;
use Tilttek\Encounter\Event\ParticipantRemoved;
use Tilttek\Encounter\Exception\EncounterConflict;
use Tilttek\Encounter\Exception\EncounterNotFound;
use Tilttek\Encounter\Exception\EncounterRefused;
use Tilttek\Encounter\Exception\InvalidEncounter;
use Tilttek\Encounter\Exception\UnknownSystem;
use Tilttek\Encounter\Initiative\InitiativeRoster;
use Tilttek\Encounter\Internal\Identifier;

/**
 * The service of the encounters a game master prepares: it opens an encounter for a game system, adds, changes, moves
 * and removes its participants, assesses its difficulty by the rules the system declares, and launches it — hands its
 * combatants to an initiative tracker, after which it no longer changes. It reads from the store, changes, keeps, then
 * tells the observer what happened, each fact once. It keeps no state between calls: everything is in the store.
 *
 * Only the encounter's game master changes it; the service trusts its caller to say who is calling.
 */
final readonly class EncounterPlanner
{
    public function __construct(
        private EncounterStore $store,
        private ?Rulebooks $rulebooks = null,
        private ?EncounterObserver $observer = null,
        private ?InitiativeHandOff $handOff = null,
    ) {
    }

    /**
     * The encounter as kept.
     *
     * @throws InvalidEncounter  when the identifier is not one
     * @throws EncounterNotFound when no encounter of the identifier is kept
     */
    public function encounter(string $id): Encounter
    {
        return $this->store->encounter(Identifier::check($id, 'an encounter')) ?? throw EncounterNotFound::of($id);
    }

    /**
     * Opens a new encounter, with participants or none yet.
     *
     * @param string            $gameMaster   the member who prepares it, and alone changes it
     * @param string            $system       the key of its game system
     * @param list<Participant> $participants in order
     *
     * @throws InvalidEncounter  when a value is out of its shape or its bounds
     * @throws UnknownSystem     when rulebooks are given and none is declared for the system
     * @throws EncounterConflict when an encounter of the identifier already exists
     */
    public function prepare(string $id, string $gameMaster, string $system, ?string $title = null, array $participants = []): Encounter
    {
        $encounter = new Encounter($id, $gameMaster, $system, $participants, $title);
        if ($this->rulebooks instanceof Rulebooks && !$this->rulebooks->has($system)) {
            throw UnknownSystem::of($system);
        }

        return $this->keep($encounter, new EncounterPrepared($id, $gameMaster));
    }

    /**
     * Gives the encounter another title, or takes its title away with null. The same title changes nothing.
     *
     * @throws InvalidEncounter|EncounterNotFound|EncounterRefused|EncounterConflict
     */
    public function retitle(string $id, string $by, ?string $title): Encounter
    {
        $before = $this->changeable($id, $by);
        $after = $before->withTitle($title);
        if ($after->title === $before->title) {
            return $before;
        }

        return $this->keep($after, new EncounterRetitled($id));
    }

    /**
     * Adds a participant: last, or right before or after another one.
     *
     * @param ?string $before the participant it comes right before, or null
     * @param ?string $after  the participant it comes right after, or null
     *
     * @throws InvalidEncounter  when a value is out of its bounds, both places are given, or the identifier comes twice
     * @throws EncounterNotFound when no encounter of the identifier is kept
     * @throws EncounterRefused  when the caller is not the game master, the encounter was launched, or the participant
     *                           named as a place is not in the encounter
     * @throws EncounterConflict when the encounter changed since it was read
     */
    public function add(string $id, string $by, Participant $participant, ?string $before = null, ?string $after = null): Encounter
    {
        $encounter = $this->changeable($id, $by);
        $participants = $this->place($encounter, $encounter->participants, $participant, $before, $after);

        return $this->keep($encounter->with($participants), new ParticipantAdded($id, $participant->id));
    }

    /**
     * Replaces a participant by the one of the same identifier, in its place. The same participant changes nothing.
     *
     * @throws InvalidEncounter|EncounterNotFound|EncounterRefused|EncounterConflict
     */
    public function change(string $id, string $by, Participant $participant): Encounter
    {
        $encounter = $this->changeable($id, $by);
        $current = $this->held($encounter, $participant->id);
        if ($current->sameAs($participant)) {
            return $encounter;
        }

        $participants = array_map(static fn (Participant $held): Participant => $held->id === $participant->id ? $participant : $held, $encounter->participants);

        return $this->keep($encounter->with($participants), new ParticipantChanged($id, $participant->id));
    }

    /**
     * Removes a participant.
     *
     * @throws InvalidEncounter|EncounterNotFound|EncounterRefused|EncounterConflict
     */
    public function remove(string $id, string $by, string $participant): Encounter
    {
        $encounter = $this->changeable($id, $by);
        $this->held($encounter, $participant);

        return $this->keep($encounter->with($this->without($encounter, $participant)), new ParticipantRemoved($id, $participant));
    }

    /**
     * Moves a participant right before or right after another one; exactly one of the two is given. A move that leaves
     * the order as it is changes nothing.
     *
     * @throws InvalidEncounter|EncounterNotFound|EncounterRefused|EncounterConflict
     */
    public function move(string $id, string $by, string $participant, ?string $before = null, ?string $after = null): Encounter
    {
        if (null === $before && null === $after) {
            throw InvalidEncounter::because('A participant moves before or after another one.');
        }

        $encounter = $this->changeable($id, $by);
        $moved = $this->held($encounter, $participant);
        if ($participant === ($before ?? $after)) {
            throw EncounterRefused::because(\sprintf('The participant %s moves before or after another one.', $participant));
        }

        $participants = $this->place($encounter, $this->without($encounter, $participant), $moved, $before, $after);
        if ($participants === $encounter->participants) {
            return $encounter;
        }

        return $this->keep($encounter->with($participants), new ParticipantMoved($id, $participant));
    }

    /**
     * The difficulty of the encounter, by the rules its game system declares.
     *
     * @throws InvalidEncounter  when the identifier is not one, or a participant has a rating the rules do not know
     * @throws EncounterNotFound when no encounter of the identifier is kept
     * @throws UnknownSystem     when no rules are declared for its game system, or no rulebooks are given
     * @throws EncounterRefused  when the rules cannot assess its participants
     */
    public function assess(string $id): Assessment
    {
        $encounter = $this->encounter($id);
        if (!$this->rulebooks instanceof Rulebooks) {
            throw UnknownSystem::of($encounter->system);
        }

        return $this->rulebooks->for($encounter->system)->assess($encounter->participants);
    }

    /**
     * What an initiative tracker receives of the encounter, launched or not: to show it, or to hand it again when the
     * tracker was not reached.
     *
     * @throws InvalidEncounter|EncounterNotFound
     */
    public function roster(string $id): InitiativeRoster
    {
        return InitiativeRoster::of($this->encounter($id));
    }

    /**
     * Launches the encounter: keeps it launched, so that it no longer changes, then hands its roster to the initiative
     * tracker when one is given, and returns the roster.
     *
     * @throws InvalidEncounter  when the identifier is not one
     * @throws EncounterNotFound when no encounter of the identifier is kept
     * @throws EncounterRefused  when the caller is not the game master, the encounter was launched, or it has no participant
     * @throws EncounterConflict when the encounter changed since it was read
     */
    public function launch(string $id, string $by): InitiativeRoster
    {
        $encounter = $this->changeable($id, $by);
        if ([] === $encounter->participants) {
            throw EncounterRefused::because(\sprintf('The encounter %s has no participant to launch.', $id));
        }

        $launched = $this->store->save($encounter->launch());
        $roster = InitiativeRoster::of($launched);
        $this->handOff?->handOff($roster);
        $this->observer?->notify(new EncounterLaunched($id, \count($roster->entries)));

        return $roster;
    }

    /**
     * Removes the encounter from the store, launched or not.
     *
     * @throws InvalidEncounter|EncounterNotFound|EncounterConflict
     * @throws EncounterRefused                                     when the caller is not the game master
     */
    public function forget(string $id, string $by): void
    {
        $encounter = $this->owned($id, $by);
        $this->store->remove($encounter);
        $this->observer?->notify(new EncounterForgotten($id));
    }

    /**
     * @throws InvalidEncounter|EncounterNotFound|EncounterRefused
     */
    private function owned(string $id, string $by): Encounter
    {
        $encounter = $this->encounter($id);
        if (Identifier::check($by, 'a member') !== $encounter->gameMaster) {
            throw EncounterRefused::because(\sprintf('Only the game master changes the encounter %s.', $id));
        }

        return $encounter;
    }

    /**
     * @throws InvalidEncounter|EncounterNotFound|EncounterRefused
     */
    private function changeable(string $id, string $by): Encounter
    {
        $encounter = $this->owned($id, $by);
        if ($encounter->launched) {
            throw EncounterRefused::because(\sprintf('The encounter %s was launched and no longer changes.', $id));
        }

        return $encounter;
    }

    /**
     * @throws EncounterRefused
     */
    private function held(Encounter $encounter, string $participant): Participant
    {
        return $encounter->participant($participant) ?? throw EncounterRefused::because(\sprintf('The encounter %s has no participant %s.', $encounter->id, $participant));
    }

    /**
     * @return list<Participant>
     */
    private function without(Encounter $encounter, string $participant): array
    {
        return array_values(array_filter($encounter->participants, static fn (Participant $held): bool => $held->id !== $participant));
    }

    /**
     * @param list<Participant> $participants
     *
     * @return list<Participant>
     *
     * @throws InvalidEncounter|EncounterRefused
     */
    private function place(Encounter $encounter, array $participants, Participant $placed, ?string $before, ?string $after): array
    {
        if (null !== $before && null !== $after) {
            throw InvalidEncounter::because('A participant comes before another one or after it, not both.');
        }

        $anchor = $before ?? $after;
        if (null === $anchor) {
            return [...$participants, $placed];
        }

        $position = array_find_key($participants, static fn (Participant $held): bool => $held->id === $anchor);
        if (null === $position) {
            throw EncounterRefused::because(\sprintf('The encounter %s has no participant %s.', $encounter->id, $anchor));
        }

        $position += null === $before ? 1 : 0;

        return [...\array_slice($participants, 0, $position), $placed, ...\array_slice($participants, $position)];
    }

    /**
     * @throws EncounterConflict
     */
    private function keep(Encounter $encounter, EncounterEvent $event): Encounter
    {
        $kept = $this->store->save($encounter);
        $this->observer?->notify($event);

        return $kept;
    }
}
