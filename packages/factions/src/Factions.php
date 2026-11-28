<?php

declare(strict_types=1);

namespace Tilttek\Factions;

use Psr\Clock\ClockInterface;
use Tilttek\Factions\Contract\RosterObserver;
use Tilttek\Factions\Contract\RosterStore;
use Tilttek\Factions\Event\FactionAdded;
use Tilttek\Factions\Event\FactionChanged;
use Tilttek\Factions\Event\FactionRemoved;
use Tilttek\Factions\Event\RankChanged;
use Tilttek\Factions\Event\RelationChanged;
use Tilttek\Factions\Event\RosterCreated;
use Tilttek\Factions\Event\RosterDeleted;
use Tilttek\Factions\Event\RosterEvent;
use Tilttek\Factions\Event\RosterHandedOver;
use Tilttek\Factions\Event\ScaleChanged;
use Tilttek\Factions\Event\StandingChanged;
use Tilttek\Factions\Exception\InvalidRoster;
use Tilttek\Factions\Exception\RosterConflict;
use Tilttek\Factions\Exception\RosterNotFound;
use Tilttek\Factions\Exception\RosterRefused;
use Tilttek\Factions\History\Entry;
use Tilttek\Factions\History\RankEntry;
use Tilttek\Factions\History\RelationEntry;
use Tilttek\Factions\History\StandingEntry;
use Tilttek\Factions\Internal\Identifier;
use Tilttek\Factions\View\FactionView;
use Tilttek\Factions\View\RosterView;
use Tilttek\Factions\View\StandingView;

/**
 * The service of the rosters: it creates the roster of a campaign, adds, changes and removes its factions, changes its
 * scale, moves standings, ranks and relations — each change with its reason, kept in the history in the same write —,
 * hands the roster over, deletes it, and shows it to the game master and to the players. It reads from the store,
 * changes, keeps, then tells the observer what happened, each fact once. It keeps no state between calls.
 *
 * Only a roster's game master changes it, hands it over, deletes it, reads its history and sees its secrets through
 * this service. What a player sees is given by `playerView()`; the application checks that the reader plays in the
 * campaign.
 */
final readonly class Factions
{
    /** The most entries one read of the history gives. */
    public const int MAX_READ = 500;

    /** The most steps one shift moves a standing. */
    public const int MAX_STEPS = Scale::HIGHEST - Scale::LOWEST;

    public function __construct(
        private RosterStore $store,
        private ClockInterface $clock,
        private ?RosterObserver $observer = null,
    ) {
    }

    /**
     * @throws InvalidRoster  when the identifier is not one
     * @throws RosterNotFound when no roster is kept for the campaign
     */
    public function roster(string $campaign): Roster
    {
        return $this->store->roster(Identifier::check($campaign, 'a campaign')) ?? throw RosterNotFound::forCampaign($campaign);
    }

    /**
     * Creates the roster of a campaign, at version 0, with the standings, the members and the relations it starts
     * with: they are where the campaign begins, and leave no history.
     *
     * @throws RosterRefused  when the roster is not at version 0, or one is kept for the campaign
     * @throws RosterConflict when one was kept for the campaign meanwhile
     */
    public function create(Roster $roster): Roster
    {
        if (0 !== $roster->version) {
            throw RosterRefused::because('A new roster is at version 0.');
        }

        if ($this->store->roster($roster->campaign) instanceof Roster) {
            throw RosterRefused::because(\sprintf('A roster is kept for the campaign %s.', $roster->campaign));
        }

        $kept = $this->store->save($roster);
        $this->tell(new RosterCreated($kept->campaign));

        return $kept;
    }

    /**
     * Adds a faction to the roster, after the others. It joins without standings or members: `shift()`,
     * `setStanding()` and `setRank()` give them, each with its reason.
     *
     * @throws RosterNotFound when no roster is kept for the campaign
     * @throws RosterRefused  when the member is not its game master, or the faction holds standings or members
     * @throws InvalidRoster  when a faction has its key, or the roster is full
     * @throws RosterConflict when the roster changed meanwhile
     */
    public function addFaction(string $campaign, Faction $faction, string $by): Roster
    {
        $before = $this->owned($campaign, $by);

        if ([] !== $faction->standings || [] !== $faction->members) {
            throw RosterRefused::because('A faction joins without standings or members: they are set with a reason.');
        }

        if ($before->faction($faction->key) instanceof Faction) {
            throw InvalidRoster::because(\sprintf('A faction of the roster has the key %s.', $faction->key));
        }

        return $this->keep($before->withFaction($faction), [], new FactionAdded($campaign, $faction->key));
    }

    /**
     * Changes the name, the summary, the ranks and the secrecy of a faction. Its standings and its members stay as they
     * are kept, whatever the faction given holds.
     *
     * @throws RosterNotFound when no roster is kept for the campaign
     * @throws RosterRefused  when the member is not its game master
     * @throws InvalidRoster  when no faction has its key, or a member holds a rank the change removes
     * @throws RosterConflict when the roster changed meanwhile
     */
    public function changeFaction(string $campaign, Faction $faction, string $by): Roster
    {
        $before = $this->owned($campaign, $by);
        $kept = $before->get($faction->key);

        if ($kept->hasDetailsOf($faction)) {
            return $before;
        }

        return $this->keep($before->withFaction($kept->withDetailsOf($faction)), [], new FactionChanged($campaign, $faction->key));
    }

    /**
     * Removes a faction from the roster, with its relations. Its history stays in the store until the roster is
     * deleted.
     *
     * @throws RosterNotFound when no roster is kept for the campaign
     * @throws RosterRefused  when the member is not its game master
     * @throws InvalidRoster  when no faction has the key
     * @throws RosterConflict when the roster changed meanwhile
     */
    public function removeFaction(string $campaign, string $faction, string $by): Roster
    {
        $before = $this->owned($campaign, $by);

        return $this->keep($before->withoutFaction($faction), [], new FactionRemoved($campaign, $faction));
    }

    /**
     * Changes the scale of the roster. Every standing set lies within the new one, or the change is refused: move them
     * first, each with its reason.
     *
     * @throws RosterNotFound when no roster is kept for the campaign
     * @throws RosterRefused  when the member is not its game master
     * @throws InvalidRoster  when a standing is off the new scale
     * @throws RosterConflict when the roster changed meanwhile
     */
    public function changeScale(string $campaign, Scale $scale, string $by): Roster
    {
        $before = $this->owned($campaign, $by);

        if ($scale->equals($before->scale)) {
            return $before;
        }

        return $this->keep($before->withScale($scale), [], new ScaleChanged($campaign));
    }

    /**
     * Moves where a subject stands with a faction by so many steps, up when positive, down when negative; a move past
     * an end of the scale stops there. A move that leaves the standing where it was keeps and tells nothing.
     *
     * @param int $steps -`MAX_STEPS` to `MAX_STEPS`, never 0
     *
     * @throws RosterNotFound when no roster is kept for the campaign
     * @throws RosterRefused  when the member is not its game master
     * @throws InvalidRoster  when the steps are out of their bounds, no faction has the key, or the faction holds as
     *                        many standings as it may
     * @throws RosterConflict when the roster changed meanwhile
     */
    public function shift(string $campaign, string $faction, Subject $subject, int $steps, Reason $reason, string $by): Roster
    {
        if (0 === $steps || abs($steps) > self::MAX_STEPS) {
            throw InvalidRoster::because(\sprintf('A shift moves a standing by 1 to %d steps, up or down.', self::MAX_STEPS));
        }

        $before = $this->owned($campaign, $by);

        return $this->stand($before, $faction, $subject, $before->scale->clamp($before->standingOf($faction, $subject) + $steps), $reason, $by);
    }

    /**
     * Sets where a subject stands with a faction. A standing already there keeps and tells nothing.
     *
     * @throws RosterNotFound when no roster is kept for the campaign
     * @throws RosterRefused  when the member is not its game master
     * @throws InvalidRoster  when the standing is off the scale, no faction has the key, or the faction holds as many
     *                        standings as it may
     * @throws RosterConflict when the roster changed meanwhile
     */
    public function setStanding(string $campaign, string $faction, Subject $subject, int $standing, Reason $reason, string $by): Roster
    {
        $before = $this->owned($campaign, $by);

        if (!$before->scale->contains($standing)) {
            throw InvalidRoster::because(\sprintf('The standing %d is off the scale, from %d to %d.', $standing, $before->scale->min, $before->scale->max));
        }

        return $this->stand($before, $faction, $subject, $standing, $reason, $by);
    }

    /**
     * Gives a character a rank within a faction, by its key, or takes them out of it when the rank is null. A rank
     * already held keeps and tells nothing.
     *
     * @throws RosterNotFound when no roster is kept for the campaign
     * @throws RosterRefused  when the member is not its game master
     * @throws InvalidRoster  when the identifier is not one, no faction has the key, the faction lacks the rank, or it
     *                        holds as many members as it may
     * @throws RosterConflict when the roster changed meanwhile
     */
    public function setRank(string $campaign, string $faction, string $character, ?string $rank, Reason $reason, string $by): Roster
    {
        Identifier::check($character, 'a character');
        $before = $this->owned($campaign, $by);
        $kept = $before->get($faction);
        $held = $kept->membership($character)?->rank;

        if ($held === $rank) {
            return $before;
        }

        return $this->keep(
            $before->withFaction($kept->withMember($character, $rank)),
            [new RankEntry($campaign, $faction, $character, $held, $rank, $reason, $by, $this->clock->now())],
            new RankChanged($campaign, $faction, $character),
        );
    }

    /**
     * Sets the relation between two factions of the roster, in place of the one they had. A relation already so keeps
     * and tells nothing.
     *
     * @throws RosterNotFound when no roster is kept for the campaign
     * @throws RosterRefused  when the member is not its game master
     * @throws InvalidRoster  when it names a faction the roster lacks, or the roster holds as many relations as it may
     * @throws RosterConflict when the roster changed meanwhile
     */
    public function relate(string $campaign, Relation $relation, Reason $reason, string $by): Roster
    {
        $before = $this->owned($campaign, $by);
        $held = $before->relation($relation->first, $relation->second);

        if ($held?->equals($relation) ?? false) {
            return $before;
        }

        return $this->keep(
            $before->withRelation($relation),
            [new RelationEntry($campaign, $relation->first, $relation->second, $held, $relation, $reason, $by, $this->clock->now())],
            new RelationChanged($campaign, $relation->first, $relation->second),
        );
    }

    /**
     * Ends the relation between two factions, in either order.
     *
     * @throws RosterNotFound when no roster is kept for the campaign
     * @throws RosterRefused  when the member is not its game master
     * @throws InvalidRoster  when the two factions have no relation
     * @throws RosterConflict when the roster changed meanwhile
     */
    public function endRelation(string $campaign, string $faction, string $other, Reason $reason, string $by): Roster
    {
        $before = $this->owned($campaign, $by);
        $held = $before->relation($faction, $other) ?? throw InvalidRoster::because(\sprintf('The factions %s and %s have no relation.', $faction, $other));

        return $this->keep(
            $before->withoutRelation($faction, $other),
            [new RelationEntry($campaign, $held->first, $held->second, $held, null, $reason, $by, $this->clock->now())],
            new RelationChanged($campaign, $held->first, $held->second),
        );
    }

    /**
     * Hands the roster over to another game master, by their identifier.
     *
     * @throws RosterNotFound when no roster is kept for the campaign
     * @throws RosterRefused  when the member is not its game master
     * @throws InvalidRoster  when the new game master's identifier is not one
     * @throws RosterConflict when the roster changed meanwhile
     */
    public function handOver(string $campaign, string $to, string $by): Roster
    {
        $before = $this->owned($campaign, $by);

        if ($to === $before->gameMaster) {
            return $before;
        }

        return $this->keep($before->withGameMaster($to), [], new RosterHandedOver($campaign, $to));
    }

    /**
     * Deletes the roster and its history.
     *
     * @throws RosterNotFound when no roster is kept for the campaign
     * @throws RosterRefused  when the member is not its game master
     * @throws RosterConflict when the roster changed meanwhile
     */
    public function delete(string $campaign, string $by): void
    {
        $before = $this->owned($campaign, $by);
        $this->store->delete($before);
        $this->tell(new RosterDeleted($campaign));
    }

    /**
     * The changes that touched a faction, the newest first, also once the faction was removed.
     *
     * @param int $limit 1 to `MAX_READ`
     *
     * @return iterable<Entry>
     *
     * @throws RosterNotFound when no roster is kept for the campaign
     * @throws RosterRefused  when the member is not its game master
     * @throws InvalidRoster  when the key is not one, or the limit is out of its bounds
     */
    public function history(string $campaign, string $faction, string $by, int $limit = 100): iterable
    {
        $this->owned($campaign, $by);
        Identifier::key($faction, 'a faction');

        if ($limit < 1 || $limit > self::MAX_READ) {
            throw InvalidRoster::because(\sprintf('A read of the history is of 1 to %d entries.', self::MAX_READ));
        }

        return $this->store->history($campaign, $faction, $limit);
    }

    /**
     * The roster as its game master sees it: every faction, the standing of the party then every standing set with a
     * character, every member and every relation, secret or not.
     *
     * @throws RosterNotFound when no roster is kept for the campaign
     * @throws RosterRefused  when the member is not its game master
     */
    public function view(string $campaign, string $by): RosterView
    {
        $roster = $this->owned($campaign, $by);
        $factions = [];

        foreach ($roster->factions as $faction) {
            $standings = [$this->standing($roster, $faction, Subject::party())];

            foreach ($faction->standings as $standing) {
                if (!$standing->subject->isParty()) {
                    $standings[] = new StandingView($standing->subject->key, $standing->value, $roster->scale->tier($standing->value));
                }
            }

            $factions[] = new FactionView($faction->key, $faction->name, $faction->summary, $faction->secret, $faction->ranks, $standings, $faction->members);
        }

        return new RosterView($roster->campaign, $roster->version, $roster->scale, $factions, $roster->relations);
    }

    /**
     * The roster as a player sees it: the factions not secret, each with the standing of the party and, when a
     * character is named, their own standing and their own rank; the relations not secret between those factions.
     *
     * @throws InvalidRoster  when an identifier is not one
     * @throws RosterNotFound when no roster is kept for the campaign
     */
    public function playerView(string $campaign, ?string $character = null): RosterView
    {
        $roster = $this->roster($campaign);
        $subject = null === $character ? null : Subject::character($character);
        $factions = [];
        $shown = [];

        foreach ($roster->factions as $faction) {
            if ($faction->secret) {
                continue;
            }

            $shown[$faction->key] = true;
            $standings = [$this->standing($roster, $faction, Subject::party())];
            $members = [];

            if ($subject instanceof Subject) {
                $standings[] = $this->standing($roster, $faction, $subject);
                $member = $faction->membership($subject->character ?? '');

                if ($member instanceof Membership) {
                    $members[] = $member;
                }
            }

            $factions[] = new FactionView($faction->key, $faction->name, $faction->summary, false, $faction->ranks, $standings, $members);
        }

        $relations = array_values(array_filter(
            $roster->relations,
            static fn (Relation $relation): bool => !$relation->secret && isset($shown[$relation->first], $shown[$relation->second]),
        ));

        return new RosterView($roster->campaign, $roster->version, $roster->scale, $factions, $relations);
    }

    /**
     * @throws InvalidRoster
     * @throws RosterConflict
     */
    private function stand(Roster $before, string $faction, Subject $subject, int $standing, Reason $reason, string $by): Roster
    {
        $held = $before->standingOf($faction, $subject);

        if ($held === $standing) {
            return $before;
        }

        return $this->keep(
            $before->withFaction($before->get($faction)->withStanding($subject, $standing)),
            [new StandingEntry($before->campaign, $faction, $subject, $held, $standing, $reason, $by, $this->clock->now())],
            new StandingChanged($before->campaign, $faction, $subject->key),
        );
    }

    private function standing(Roster $roster, Faction $faction, Subject $subject): StandingView
    {
        $value = $faction->standing($subject)->value ?? $roster->scale->start;

        return new StandingView($subject->key, $value, $roster->scale->tier($value));
    }

    /**
     * @throws RosterNotFound
     * @throws RosterRefused
     */
    private function owned(string $campaign, string $by): Roster
    {
        $roster = $this->roster($campaign);

        if ($by !== $roster->gameMaster) {
            throw RosterRefused::because(\sprintf('Only the game master of the campaign %s changes its factions and sees their secrets.', $roster->campaign));
        }

        return $roster;
    }

    /**
     * @param list<Entry> $entries
     *
     * @throws RosterConflict
     */
    private function keep(Roster $roster, array $entries, RosterEvent $event): Roster
    {
        $kept = $this->store->save($roster, $entries);
        $this->tell($event);

        return $kept;
    }

    private function tell(RosterEvent $event): void
    {
        $this->observer?->notify($event);
    }
}
