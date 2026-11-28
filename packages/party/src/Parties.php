<?php

declare(strict_types=1);

namespace Tilttek\Party;

use Phil\Resource\ResourceReference;
use Psr\Clock\ClockInterface;
use Tilttek\Party\Composition\Composition;
use Tilttek\Party\Composition\CompositionAdvisor;
use Tilttek\Party\Composition\RoleCatalogue;
use Tilttek\Party\Contract\PartyObserver;
use Tilttek\Party\Contract\PartyStore;
use Tilttek\Party\Exception\InvalidParty;
use Tilttek\Party\Exception\PartyRefused;
use Tilttek\Party\Exception\UnknownParty;

/**
 * The parties of the application's campaigns: formed for a campaign, joined by characters, with the purse, the loot
 * and the notes their members share, and their composition read against the roles of their system.
 *
 * ```php
 * $parties->form(ResourceReference::of('party', '7'), ResourceReference::of('campaign', '3'), 'The Lantern Bearers', 'srd-5.2');
 * $parties->join($party, $character, ['role.healer']);
 * $parties->deposit($party, 'gp', 250);
 * $parties->distribute($party, 'gp');                   // each active member's share, the remainder kept
 * $parties->composition($party, RoleCatalogue::fromSystem($system))->missing();
 * ```
 *
 * The library decides no permission: the application checks who may act before it calls, and gives the acting
 * reference as `$by`, which the events carry. Only a note's author must belong to the party.
 */
final readonly class Parties
{
    /** @var list<PartyObserver> */
    private array $observers;

    /**
     * @param iterable<PartyObserver> $observers
     */
    public function __construct(
        private PartyStore $store,
        private ClockInterface $clock,
        private Limits $limits = new Limits(),
        iterable $observers = [],
    ) {
        $list = [];

        foreach ($observers as $observer) {
            $list[] = $observer;
        }

        $this->observers = $list;
    }

    /**
     * Forms a party for a campaign, under the reference the application gives, with the key of its game system or
     * none.
     */
    public function form(ResourceReference $party, ResourceReference $campaign, string $name, ?string $system = null, ?ResourceReference $by = null): Party
    {
        $formed = new Party($party, $campaign, $name, $system, $this->clock->now());

        if ($this->store->party($party->toString()) instanceof Party) {
            throw PartyRefused::because(Refusal::AlreadyFormed);
        }

        $this->store->saveParty($formed);
        $this->tell($party, Change::Formed, null, null, $by);

        return $formed;
    }

    /**
     * @throws UnknownParty
     */
    public function party(ResourceReference $party): Party
    {
        return $this->store->party($party->toString()) ?? throw UnknownParty::named($party->toString());
    }

    public function rename(ResourceReference $party, string $name, ?ResourceReference $by = null): Party
    {
        $renamed = $this->party($party)->renamed($name);
        $this->store->saveParty($renamed);
        $this->tell($party, Change::Updated, null, null, $by);

        return $renamed;
    }

    /**
     * Gives the party another game system, or none: its members keep their roles, which the composition reads against
     * the new system's.
     */
    public function useSystem(ResourceReference $party, ?string $system, ?ResourceReference $by = null): Party
    {
        $changed = $this->party($party)->withSystem($system);
        $this->store->saveParty($changed);
        $this->tell($party, Change::Updated, null, null, $by);

        return $changed;
    }

    /**
     * Disbands the party with its members, purse, loot and notes.
     */
    public function disband(ResourceReference $party, ?ResourceReference $by = null): void
    {
        $this->party($party);
        $this->store->removeParty($party->toString());
        $this->tell($party, Change::Disbanded, null, null, $by);
    }

    /**
     * A character joins the party, active, with the keys of the roles it fills.
     *
     * @param list<string> $roles
     */
    public function join(ResourceReference $party, ResourceReference $member, array $roles = [], ?ResourceReference $by = null): Member
    {
        $this->party($party);
        $joined = new Member($party, $member, $roles, Standing::Active, $this->clock->now());

        if ($this->store->member($party->toString(), $member->toString()) instanceof Member) {
            throw PartyRefused::because(Refusal::AlreadyMember);
        }

        if ($this->store->countMembers($party->toString()) >= $this->limits->membersPerParty) {
            throw PartyRefused::because(Refusal::Full);
        }

        if (\count($this->store->membershipsOf($member->toString())) >= $this->limits->partiesPerMember) {
            throw PartyRefused::because(Refusal::TooManyParties);
        }

        $this->store->saveMember($joined);
        $this->tell($party, Change::Joined, $member, null, $by);

        return $joined;
    }

    /**
     * The member leaves the party; the loot it carried goes back to the stash, the notes it wrote stay.
     */
    public function leave(ResourceReference $party, ResourceReference $member, ?ResourceReference $by = null): void
    {
        $this->belonging($party, $member);
        $this->store->removeMember($party->toString(), $member->toString());
        $this->stashLootOf($party, $member);
        $this->tell($party, Change::Left, $member, null, $by);
    }

    /**
     * Replaces the roles the member fills.
     *
     * @param list<string> $roles
     */
    public function assignRoles(ResourceReference $party, ResourceReference $member, array $roles, ?ResourceReference $by = null): Member
    {
        $assigned = $this->belonging($party, $member)->withRoles($roles);
        $this->store->saveMember($assigned);
        $this->tell($party, Change::RolesAssigned, $member, null, $by);

        return $assigned;
    }

    /**
     * Sets the member active, or away for a while: an away member keeps its place, its roles and its loot.
     */
    public function setStanding(ResourceReference $party, ResourceReference $member, Standing $standing, ?ResourceReference $by = null): Member
    {
        $changed = $this->belonging($party, $member)->withStanding($standing);
        $this->store->saveMember($changed);
        $this->tell($party, Change::StandingChanged, $member, null, $by);

        return $changed;
    }

    public function member(ResourceReference $party, ResourceReference $member): ?Member
    {
        return $this->store->member($party->toString(), $member->toString());
    }

    /**
     * The party's members, the earliest arrived first.
     *
     * @return list<Member>
     */
    public function members(ResourceReference $party): array
    {
        $this->party($party);

        return $this->store->members($party->toString());
    }

    /**
     * The parties the member belongs to, the earliest joined first.
     *
     * @return list<Party>
     */
    public function partiesOf(ResourceReference $member): array
    {
        $parties = [];

        foreach ($this->store->membershipsOf($member->toString()) as $membership) {
            $party = $this->store->party($membership->party->toString());

            if ($party instanceof Party) {
                $parties[] = $party;
            }
        }

        return $parties;
    }

    public function purse(ResourceReference $party): Purse
    {
        $this->party($party);

        return $this->store->purse($party->toString());
    }

    /**
     * Adds coins of a currency to the party's purse.
     */
    public function deposit(ResourceReference $party, string $currency, int $amount, ?ResourceReference $by = null): Purse
    {
        $purse = $this->purse($party)->deposit($currency, $amount);
        $this->store->savePurse($party->toString(), $purse);
        $this->tell($party, Change::Deposited, null, $currency, $by);

        return $purse;
    }

    /**
     * Takes coins of a currency out of the party's purse, never more than it holds.
     */
    public function withdraw(ResourceReference $party, string $currency, int $amount, ?ResourceReference $by = null): Purse
    {
        $purse = $this->purse($party)->withdraw($currency, $amount);
        $this->store->savePurse($party->toString(), $purse);
        $this->tell($party, Change::Withdrawn, null, $currency, $by);

        return $purse;
    }

    /**
     * Each member's share of a currency, without taking it out: the purse's amount divided evenly in whole coins among
     * the given members, or among the active members when none are given.
     *
     * @param ?list<ResourceReference> $among members of the party, each counted once
     */
    public function shares(ResourceReference $party, string $currency, ?array $among = null): Split
    {
        $amount = $this->purse($party)->amount(Text::currency($currency));
        $receivers = $this->receivers($party, $among);

        if ([] === $receivers) {
            throw PartyRefused::because(Refusal::NoOneToShare);
        }

        return new Split($currency, $receivers, intdiv($amount, \count($receivers)), $amount % \count($receivers));
    }

    /**
     * Takes each member's share of a currency out of the purse, as `shares()` computes it; the remainder stays. The
     * application credits each member with its share where it keeps their own coins.
     *
     * @param ?list<ResourceReference> $among
     */
    public function distribute(ResourceReference $party, string $currency, ?array $among = null, ?ResourceReference $by = null): Split
    {
        $split = $this->shares($party, $currency, $among);

        if ($split->distributed() > 0) {
            $this->store->savePurse($party->toString(), $this->store->purse($party->toString())->withdraw($currency, $split->distributed()));
        }

        $this->tell($party, Change::Distributed, null, $currency, $by);

        return $split;
    }

    /**
     * Adds loot under a new key, in the stash or carried by a member.
     */
    public function addLoot(ResourceReference $party, string $key, string $name, int $quantity = 1, ?ResourceReference $holder = null, ?ResourceReference $by = null): LootItem
    {
        $this->party($party);
        $item = new LootItem($party, $key, $name, $quantity, $holder, $this->clock->now());

        if ($holder instanceof ResourceReference) {
            $this->belonging($party, $holder);
        }

        if ($this->store->lootItem($party->toString(), $key) instanceof LootItem) {
            throw PartyRefused::because(Refusal::LootExists);
        }

        if ($this->store->countLoot($party->toString()) >= $this->limits->lootPerParty) {
            throw PartyRefused::because(Refusal::TooMuchLoot);
        }

        $this->store->saveLoot($item);
        $this->tell($party, Change::LootAdded, $holder, $key, $by);

        return $item;
    }

    /**
     * Gives loot to a member to carry, or puts it back in the stash with a null holder.
     */
    public function giveLoot(ResourceReference $party, string $key, ?ResourceReference $holder, ?ResourceReference $by = null): LootItem
    {
        $item = $this->lootItem($party, $key);

        if ($holder instanceof ResourceReference) {
            $this->belonging($party, $holder);
        }

        $given = $item->heldBy($holder);
        $this->store->saveLoot($given);
        $this->tell($party, Change::LootGiven, $holder, $key, $by);

        return $given;
    }

    /**
     * Takes some of the loot away — used, sold, lost —; the entry goes when none is left, and null is returned.
     */
    public function removeLoot(ResourceReference $party, string $key, int $quantity, ?ResourceReference $by = null): ?LootItem
    {
        $item = $this->lootItem($party, $key);

        if ($quantity < 1) {
            throw InvalidParty::because(\sprintf('A quantity removed is at least 1; %d is not.', $quantity));
        }

        if ($quantity > $item->quantity) {
            throw PartyRefused::because(Refusal::NotEnoughLoot);
        }

        $left = $quantity === $item->quantity ? null : $item->withQuantity($item->quantity - $quantity);

        if ($left instanceof LootItem) {
            $this->store->saveLoot($left);
        } else {
            $this->store->removeLoot($party->toString(), $key);
        }

        $this->tell($party, Change::LootRemoved, $item->holder, $key, $by);

        return $left;
    }

    /**
     * @throws UnknownParty
     */
    public function lootItem(ResourceReference $party, string $key): LootItem
    {
        $this->party($party);

        return $this->store->lootItem($party->toString(), $key) ?? throw UnknownParty::loot($party->toString(), $key);
    }

    /**
     * The party's loot, the earliest added first.
     *
     * @return list<LootItem>
     */
    public function loot(ResourceReference $party): array
    {
        $this->party($party);

        return $this->store->loot($party->toString());
    }

    /**
     * A member of the party writes a note under a new key.
     */
    public function writeNote(ResourceReference $party, string $key, ResourceReference $author, string $text): Note
    {
        $this->party($party);
        $note = new Note($party, $key, $text, $author, $this->clock->now());
        $this->belonging($party, $author);

        if ($this->store->note($party->toString(), $key) instanceof Note) {
            throw PartyRefused::because(Refusal::NoteExists);
        }

        if ($this->store->countNotes($party->toString()) >= $this->limits->notesPerParty) {
            throw PartyRefused::because(Refusal::TooManyNotes);
        }

        $this->store->saveNote($note);
        $this->tell($party, Change::NoteWritten, $author, $key, null);

        return $note;
    }

    /**
     * Replaces the text of a note; its author stays.
     */
    public function editNote(ResourceReference $party, string $key, string $text, ?ResourceReference $by = null): Note
    {
        $edited = $this->note($party, $key)->edited($text, $this->clock->now());
        $this->store->saveNote($edited);
        $this->tell($party, Change::NoteEdited, $edited->author, $key, $by);

        return $edited;
    }

    public function removeNote(ResourceReference $party, string $key, ?ResourceReference $by = null): void
    {
        $note = $this->note($party, $key);
        $this->store->removeNote($party->toString(), $key);
        $this->tell($party, Change::NoteRemoved, $note->author, $key, $by);
    }

    /**
     * @throws UnknownParty
     */
    public function note(ResourceReference $party, string $key): Note
    {
        $this->party($party);

        return $this->store->note($party->toString(), $key) ?? throw UnknownParty::note($party->toString(), $key);
    }

    /**
     * The party's notes, the earliest written first.
     *
     * @return list<Note>
     */
    public function notes(ResourceReference $party): array
    {
        $this->party($party);

        return $this->store->notes($party->toString());
    }

    /**
     * The party's composition against the roles of a system: advice, never a refusal.
     */
    public function composition(ResourceReference $party, RoleCatalogue $roles): Composition
    {
        return new CompositionAdvisor()->advise($this->members($party), $roles);
    }

    /**
     * Erases a member from every party: it leaves each, the loot it carried goes back to the stash, and the notes it
     * wrote, in any party, lose their author and keep their text.
     */
    public function forget(ResourceReference $member): void
    {
        foreach ($this->store->membershipsOf($member->toString()) as $membership) {
            $this->store->removeMember($membership->party->toString(), $member->toString());
            $this->stashLootOf($membership->party, $member);
            $this->tell($membership->party, Change::Forgotten, $member, null, null);
        }

        foreach ($this->store->notesBy($member->toString()) as $note) {
            $this->store->saveNote($note->withoutAuthor());
        }
    }

    /**
     * @throws UnknownParty
     * @throws PartyRefused when the member does not belong to the party
     */
    private function belonging(ResourceReference $party, ResourceReference $member): Member
    {
        $this->party($party);

        return $this->store->member($party->toString(), $member->toString()) ?? throw PartyRefused::because(Refusal::NotMember);
    }

    /**
     * @param ?list<ResourceReference> $among
     *
     * @return list<ResourceReference>
     */
    private function receivers(ResourceReference $party, ?array $among): array
    {
        if (null === $among) {
            return array_values(array_map(
                static fn (Member $member): ResourceReference => $member->member,
                array_filter($this->store->members($party->toString()), static fn (Member $member): bool => $member->isActive()),
            ));
        }

        $receivers = [];

        foreach ($among as $member) {
            $receivers[$member->toString()] = $this->belonging($party, $member)->member;
        }

        return array_values($receivers);
    }

    private function stashLootOf(ResourceReference $party, ResourceReference $member): void
    {
        foreach ($this->store->loot($party->toString()) as $item) {
            if ($item->holder instanceof ResourceReference && $item->holder->equals($member)) {
                $this->store->saveLoot($item->heldBy(null));
            }
        }
    }

    private function tell(ResourceReference $party, Change $change, ?ResourceReference $member, ?string $subject, ?ResourceReference $by): void
    {
        $event = new PartyChanged($party->toString(), $change, $member?->toString(), $subject, $by?->toString(), $this->clock->now());

        foreach ($this->observers as $observer) {
            $observer->changed($event);
        }
    }
}
