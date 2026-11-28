<?php

declare(strict_types=1);

namespace Tilttek\Party\Store;

use DateTimeImmutable;
use Tilttek\Party\Contract\PartyStore;
use Tilttek\Party\LootItem;
use Tilttek\Party\Member;
use Tilttek\Party\Note;
use Tilttek\Party\Party;
use Tilttek\Party\Purse;

/**
 * The parties kept in memory, for tests and for an application that rebuilds them at each start.
 */
final class InMemoryPartyStore implements PartyStore
{
    /** @var array<string, Party> */
    private array $parties = [];

    /** @var array<string, array<string, Member>> by party, then member */
    private array $members = [];

    /** @var array<string, Purse> */
    private array $purses = [];

    /** @var array<string, array<string, LootItem>> by party, then key */
    private array $loot = [];

    /** @var array<string, array<string, Note>> by party, then key */
    private array $notes = [];

    public function party(string $party): ?Party
    {
        return $this->parties[$party] ?? null;
    }

    public function saveParty(Party $party): void
    {
        $this->parties[$party->reference->toString()] = $party;
    }

    public function removeParty(string $party): void
    {
        unset($this->parties[$party], $this->members[$party], $this->purses[$party], $this->loot[$party], $this->notes[$party]);
    }

    public function member(string $party, string $member): ?Member
    {
        return $this->members[$party][$member] ?? null;
    }

    public function members(string $party): array
    {
        return $this->sortMembers($this->members[$party] ?? []);
    }

    public function countMembers(string $party): int
    {
        return \count($this->members[$party] ?? []);
    }

    public function saveMember(Member $member): void
    {
        $this->members[$member->party->toString()][$member->member->toString()] = $member;
    }

    public function removeMember(string $party, string $member): void
    {
        unset($this->members[$party][$member]);
    }

    public function membershipsOf(string $member): array
    {
        $found = [];

        foreach ($this->members as $members) {
            if (isset($members[$member])) {
                $found[] = $members[$member];
            }
        }

        return $this->sortMembers($found);
    }

    public function purse(string $party): Purse
    {
        return $this->purses[$party] ?? new Purse();
    }

    public function savePurse(string $party, Purse $purse): void
    {
        $this->purses[$party] = $purse;
    }

    public function lootItem(string $party, string $key): ?LootItem
    {
        return $this->loot[$party][$key] ?? null;
    }

    public function loot(string $party): array
    {
        $loot = $this->loot[$party] ?? [];
        usort($loot, static fn (LootItem $a, LootItem $b): int => self::order($a->addedAt, $b->addedAt, $a->key, $b->key));

        return $loot;
    }

    public function countLoot(string $party): int
    {
        return \count($this->loot[$party] ?? []);
    }

    public function saveLoot(LootItem $item): void
    {
        $this->loot[$item->party->toString()][$item->key] = $item;
    }

    public function removeLoot(string $party, string $key): void
    {
        unset($this->loot[$party][$key]);
    }

    public function note(string $party, string $key): ?Note
    {
        return $this->notes[$party][$key] ?? null;
    }

    public function notes(string $party): array
    {
        return $this->sortNotes($this->notes[$party] ?? []);
    }

    public function countNotes(string $party): int
    {
        return \count($this->notes[$party] ?? []);
    }

    public function saveNote(Note $note): void
    {
        $this->notes[$note->party->toString()][$note->key] = $note;
    }

    public function removeNote(string $party, string $key): void
    {
        unset($this->notes[$party][$key]);
    }

    public function notesBy(string $author): array
    {
        $found = [];

        foreach ($this->notes as $notes) {
            foreach ($notes as $note) {
                if ($note->author?->toString() === $author) {
                    $found[] = $note;
                }
            }
        }

        return $this->sortNotes($found);
    }

    /**
     * @param array<Member> $members
     *
     * @return list<Member>
     */
    private function sortMembers(array $members): array
    {
        usort($members, static fn (Member $a, Member $b): int => self::order($a->since, $b->since, $a->party->toString().' '.$a->member->toString(), $b->party->toString().' '.$b->member->toString()));

        return $members;
    }

    /**
     * @param array<Note> $notes
     *
     * @return list<Note>
     */
    private function sortNotes(array $notes): array
    {
        usort($notes, static function (Note $a, Note $b): int {
            $byParty = self::order($a->writtenAt, $b->writtenAt, $a->party->toString(), $b->party->toString());

            return 0 !== $byParty ? $byParty : strcmp($a->key, $b->key);
        });

        return $notes;
    }

    /**
     * The earlier moment first, then the tie-breaks in byte order.
     */
    private static function order(DateTimeImmutable $a, DateTimeImmutable $b, string $tieA, string $tieB): int
    {
        $byTime = $a <=> $b;

        return 0 !== $byTime ? $byTime : strcmp($tieA, $tieB);
    }
}
