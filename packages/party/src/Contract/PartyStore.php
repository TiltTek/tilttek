<?php

declare(strict_types=1);

namespace Tilttek\Party\Contract;

use Tilttek\Party\LootItem;
use Tilttek\Party\Member;
use Tilttek\Party\Note;
use Tilttek\Party\Party;
use Tilttek\Party\Purse;

/**
 * Where the parties, their members, purses, loot and notes are kept. Parties and members are named by their references
 * written `kind:identifier`; loot and notes by their keys inside their party.
 *
 * The library checks a party's limits before it writes, and a purse's balance before it withdraws: an application that
 * serves concurrent requests runs each act of `Parties` in a transaction, or under a lock on the party, so that two
 * withdrawals never spend the same coins. An implementation keeps no state between two requests beside its storage.
 */
interface PartyStore
{
    public function party(string $party): ?Party;

    public function saveParty(Party $party): void;

    /**
     * Removes the party with its members, its purse, its loot and its notes.
     */
    public function removeParty(string $party): void;

    public function member(string $party, string $member): ?Member;

    /**
     * The party's members, the earliest arrived first, ties in the order of their references.
     *
     * @return list<Member>
     */
    public function members(string $party): array;

    public function countMembers(string $party): int;

    /**
     * Writes the member, replacing its earlier entry in that party.
     */
    public function saveMember(Member $member): void;

    public function removeMember(string $party, string $member): void;

    /**
     * The entries of the member in every party, the earliest arrived first.
     *
     * @return list<Member>
     */
    public function membershipsOf(string $member): array;

    /**
     * The party's purse, an empty one when nothing was saved.
     */
    public function purse(string $party): Purse;

    public function savePurse(string $party, Purse $purse): void;

    public function lootItem(string $party, string $key): ?LootItem;

    /**
     * The party's loot, the earliest added first, ties in the order of their keys.
     *
     * @return list<LootItem>
     */
    public function loot(string $party): array;

    public function countLoot(string $party): int;

    /**
     * Writes the loot, replacing the entry of the same key in that party.
     */
    public function saveLoot(LootItem $item): void;

    public function removeLoot(string $party, string $key): void;

    public function note(string $party, string $key): ?Note;

    /**
     * The party's notes, the earliest written first, ties in the order of their keys.
     *
     * @return list<Note>
     */
    public function notes(string $party): array;

    public function countNotes(string $party): int;

    /**
     * Writes the note, replacing the note of the same key in that party.
     */
    public function saveNote(Note $note): void;

    public function removeNote(string $party, string $key): void;

    /**
     * The notes the member wrote, in every party, the earliest written first.
     *
     * @return list<Note>
     */
    public function notesBy(string $author): array;
}
