<?php

declare(strict_types=1);

namespace Tilttek\Party\Tests\Unit;

use Closure;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\Party\Change;
use Tilttek\Party\Composition\Coverage;
use Tilttek\Party\Composition\Role;
use Tilttek\Party\Composition\RoleCatalogue;
use Tilttek\Party\Exception\InvalidParty;
use Tilttek\Party\Exception\PartyRefused;
use Tilttek\Party\Exception\UnknownParty;
use Tilttek\Party\Limits;
use Tilttek\Party\LootItem;
use Tilttek\Party\Member;
use Tilttek\Party\Note;
use Tilttek\Party\Parties;
use Tilttek\Party\Party;
use Tilttek\Party\PartyChanged;
use Tilttek\Party\Purse;
use Tilttek\Party\Refusal;
use Tilttek\Party\Split;
use Tilttek\Party\Standing;
use Tilttek\Party\Store\InMemoryPartyStore;
use Tilttek\Party\Tests\Unit\Fixture\Recorder;

#[CoversClass(Parties::class)]
#[CoversClass(InMemoryPartyStore::class)]
#[CoversClass(PartyChanged::class)]
final class PartiesTest extends TestCase
{
    private MockClock $clock;

    private InMemoryPartyStore $store;

    private Recorder $recorder;

    private Parties $parties;

    private ResourceReference $party;

    private ResourceReference $campaign;

    private ResourceReference $gm;

    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-25 10:00:00', 'UTC');
        $this->store = new InMemoryPartyStore();
        $this->recorder = new Recorder();
        $this->parties = new Parties($this->store, $this->clock, new Limits(), [$this->recorder]);
        $this->party = ResourceReference::of('party', '7');
        $this->campaign = ResourceReference::of('campaign', '3');
        $this->gm = ResourceReference::of('player', '1');
    }

    public function testAPartyIsFormedForACampaign(): void
    {
        $party = $this->parties->form($this->party, $this->campaign, 'The Lantern Bearers', 'srd-5.2', $this->gm);

        self::assertSame(['The Lantern Bearers', 'srd-5.2', 'campaign:3'], [$party->name, $party->system, $party->campaign->toString()]);
        self::assertSame('2026-11-25 10:00:00', $party->formedAt->format('Y-m-d H:i:s'));
        self::assertSame($party, $this->parties->party($this->party));
        self::assertSame(['party:7 formed - - player:1'], $this->recorder->summary());
        self::assertSame('2026-11-25 10:00:00', $this->recorder->changes[0]->at->format('Y-m-d H:i:s'));
    }

    public function testAReferenceIsFormedOnce(): void
    {
        $this->form();

        $this->assertRefused(Refusal::AlreadyFormed, fn (): Party => $this->parties->form($this->party, $this->campaign, 'Other'));
        self::assertSame('Lanterns', $this->parties->party($this->party)->name);
    }

    public function testAnInvalidPartyIsRefusedBeforeTheStoreIsRead(): void
    {
        $this->form();

        $this->expectException(InvalidParty::class);

        $this->parties->form($this->party, $this->campaign, '');
    }

    public function testAnUnknownPartyIsReported(): void
    {
        $this->expectException(UnknownParty::class);
        $this->expectExceptionMessageIsOrContains('No party is known as "party:7".');

        $this->parties->party($this->party);
    }

    public function testAPartyIsRenamedAndChangesSystem(): void
    {
        $this->form();

        self::assertSame('Lamplighters', $this->parties->rename($this->party, 'Lamplighters', $this->gm)->name);
        self::assertSame('Lamplighters', $this->parties->party($this->party)->name);
        self::assertSame('srd-5.2', $this->parties->useSystem($this->party, 'srd-5.2')->system);
        self::assertSame('srd-5.2', $this->parties->party($this->party)->system);
        self::assertNull($this->parties->useSystem($this->party, null)->system);
        self::assertSame(['party:7 updated - - player:1', 'party:7 updated - - -', 'party:7 updated - - -'], \array_slice($this->recorder->summary(), 1));
    }

    public function testAPartyIsDisbandedWithEverythingItHolds(): void
    {
        $this->form();
        $ada = $this->join('1');
        $this->parties->deposit($this->party, 'gp', 10);
        $this->parties->addLoot($this->party, 'rope', 'Rope');
        $this->parties->writeNote($this->party, 'lead', $ada, 'A lead');

        $this->parties->disband($this->party, $this->gm);

        self::assertNull($this->store->party('party:7'));
        self::assertSame([], $this->store->members('party:7'));
        self::assertTrue($this->store->purse('party:7')->isEmpty());
        self::assertSame([], $this->store->loot('party:7'));
        self::assertSame([], $this->store->notes('party:7'));
        self::assertSame([], $this->parties->partiesOf($ada));
        self::assertSame('party:7 disbanded - - player:1', $this->lastChange());
    }

    public function testDisbandingAnUnknownPartyIsReported(): void
    {
        $this->expectException(UnknownParty::class);

        $this->parties->disband($this->party);
    }

    public function testCharactersJoinActiveWithTheirRoles(): void
    {
        $this->form();
        $member = $this->parties->join($this->party, $this->character('1'), ['role.healer'], $this->gm);
        $this->clock->modify('+1 minute');
        $this->join('2');

        self::assertSame([['role.healer'], Standing::Active, '2026-11-25 10:00:00'], [$member->roles, $member->standing, $member->since->format('Y-m-d H:i:s')]);
        self::assertSame($member, $this->parties->member($this->party, $this->character('1')));
        self::assertNull($this->parties->member($this->party, $this->character('9')));
        self::assertSame(['character:1', 'character:2'], $this->references($this->parties->members($this->party)));
        self::assertSame(['party:7 joined character:1 - player:1', 'party:7 joined character:2 - -'], \array_slice($this->recorder->summary(), 1));
    }

    public function testMembersArrivingTogetherAreReadByReference(): void
    {
        $this->form();
        $this->join('b');
        $this->join('a');

        self::assertSame(['character:a', 'character:b'], $this->references($this->parties->members($this->party)));
    }

    public function testJoiningAnUnknownPartyIsReported(): void
    {
        $this->expectException(UnknownParty::class);

        $this->join('1');
    }

    public function testACharacterJoinsAPartyOnce(): void
    {
        $this->form();
        $this->join('1');

        $this->assertRefused(Refusal::AlreadyMember, fn (): ResourceReference => $this->join('1'));
    }

    public function testAFullPartyRefusesWhoeverJoins(): void
    {
        $parties = new Parties($this->store, $this->clock, new Limits(membersPerParty: 2));
        $parties->form($this->party, $this->campaign, 'Lanterns');
        $parties->join($this->party, $this->character('1'));
        $parties->join($this->party, $this->character('2'));

        $this->assertRefused(Refusal::Full, fn (): Member => $parties->join($this->party, $this->character('3')));
        self::assertSame(2, $this->store->countMembers('party:7'));
    }

    public function testACharacterBelongsToABoundedNumberOfParties(): void
    {
        $parties = new Parties($this->store, $this->clock, new Limits(partiesPerMember: 2));
        $ada = $this->character('1');

        foreach (['1', '2', '3'] as $id) {
            $parties->form(ResourceReference::of('party', $id), $this->campaign, 'Party '.$id);
        }

        $parties->join(ResourceReference::of('party', '1'), $ada);
        $parties->join(ResourceReference::of('party', '2'), $ada);

        $this->assertRefused(Refusal::TooManyParties, static fn (): Member => $parties->join(ResourceReference::of('party', '3'), $ada));
        self::assertSame(['Party 1', 'Party 2'], array_map(static fn (Party $party): string => $party->name, $parties->partiesOf($ada)));
    }

    public function testAMemberLeavesAndItsLootGoesBackToTheStash(): void
    {
        $this->form();
        $ada = $this->join('1');
        $bo = $this->join('2');
        $this->parties->addLoot($this->party, 'rope', 'Rope', 1, $ada);
        $this->parties->addLoot($this->party, 'torch', 'Torch', 3, $bo);
        $this->parties->addLoot($this->party, 'gem', 'Gem');
        $this->parties->writeNote($this->party, 'lead', $ada, 'A lead');

        $this->parties->leave($this->party, $ada, $this->gm);

        self::assertNull($this->parties->member($this->party, $ada));
        self::assertNull($this->parties->lootItem($this->party, 'rope')->holder);
        self::assertSame($bo->toString(), $this->parties->lootItem($this->party, 'torch')->holder?->toString());
        self::assertTrue($this->parties->lootItem($this->party, 'gem')->isInStash());
        self::assertSame($ada->toString(), $this->parties->note($this->party, 'lead')->author?->toString());
        self::assertSame('party:7 left character:1 - player:1', $this->lastChange());
    }

    public function testOnlyAMemberLeaves(): void
    {
        $this->form();

        $this->assertRefused(Refusal::NotMember, fn () => $this->parties->leave($this->party, $this->character('1')));
    }

    public function testAMemberChangesRolesAndStanding(): void
    {
        $this->form();
        $ada = $this->join('1');

        $assigned = $this->parties->assignRoles($this->party, $ada, ['role.tank', 'role.healer'], $this->gm);
        self::assertSame(['role.tank', 'role.healer'], $assigned->roles);
        self::assertSame(['role.tank', 'role.healer'], $this->parties->member($this->party, $ada)?->roles);

        $away = $this->parties->setStanding($this->party, $ada, Standing::Away);
        self::assertSame([Standing::Away, ['role.tank', 'role.healer']], [$away->standing, $away->roles]);
        self::assertSame(Standing::Away, $this->parties->member($this->party, $ada)?->standing);
        self::assertSame(['party:7 roles_assigned character:1 - player:1', 'party:7 standing_changed character:1 - -'], \array_slice($this->recorder->summary(), 2));
    }

    public function testOnlyAMemberIsGivenRolesOrStanding(): void
    {
        $this->form();

        $this->assertRefused(Refusal::NotMember, fn (): Member => $this->parties->assignRoles($this->party, $this->character('1'), []));
        $this->assertRefused(Refusal::NotMember, fn (): Member => $this->parties->setStanding($this->party, $this->character('1'), Standing::Away));
    }

    public function testMembersOfAnUnknownPartyAreNotRead(): void
    {
        $this->expectException(UnknownParty::class);

        $this->parties->members($this->party);
    }

    public function testThePurseTakesDepositsAndWithdrawals(): void
    {
        $this->form();

        self::assertTrue($this->parties->purse($this->party)->isEmpty());
        self::assertSame(['gp' => 250], $this->parties->deposit($this->party, 'gp', 250, $this->gm)->amounts());
        self::assertSame(['gp' => 200], $this->parties->withdraw($this->party, 'gp', 50)->amounts());
        self::assertSame(['gp' => 200], $this->parties->purse($this->party)->amounts());
        self::assertSame(['party:7 deposited - gp player:1', 'party:7 withdrawn - gp -'], \array_slice($this->recorder->summary(), 1));
    }

    public function testThePurseNeverSpendsWhatItLacks(): void
    {
        $this->form();
        $this->parties->deposit($this->party, 'gp', 5);

        $this->assertRefused(Refusal::NotEnoughFunds, fn (): Purse => $this->parties->withdraw($this->party, 'gp', 6));
        self::assertSame(5, $this->parties->purse($this->party)->amount('gp'));
        self::assertCount(2, $this->recorder->changes);
    }

    public function testThePurseOfAnUnknownPartyIsNotRead(): void
    {
        $this->expectException(UnknownParty::class);

        $this->parties->deposit($this->party, 'gp', 1);
    }

    public function testSharesGoToActiveMembersAndTheRemainderStays(): void
    {
        $this->form();
        $ada = $this->join('1');
        $bo = $this->join('2');
        $cy = $this->join('3');
        $dee = $this->join('4');
        $this->parties->setStanding($this->party, $bo, Standing::Away);
        $this->parties->deposit($this->party, 'gp', 100);

        $shares = $this->parties->shares($this->party, 'gp');
        self::assertSame([33, 1, 99], [$shares->share, $shares->remainder, $shares->distributed()]);
        self::assertSame([$ada, $cy, $dee], $shares->members);
        self::assertSame(100, $this->parties->purse($this->party)->amount('gp'));

        $split = $this->parties->distribute($this->party, 'gp', null, $this->gm);
        self::assertSame(['character:1' => 33, 'character:3' => 33, 'character:4' => 33], $split->shares());
        self::assertSame(1, $this->parties->purse($this->party)->amount('gp'));
        self::assertSame('party:7 distributed - gp player:1', $this->lastChange());
    }

    public function testSharesGoAmongTheMembersGivenEachOnce(): void
    {
        $this->form();
        $ada = $this->join('1');
        $this->join('2');
        $cy = $this->join('3');
        $this->parties->setStanding($this->party, $cy, Standing::Away);
        $this->parties->deposit($this->party, 'gp', 10);

        $split = $this->parties->distribute($this->party, 'gp', [$cy, $ada, $cy]);

        self::assertSame(['character:3' => 5, 'character:1' => 5], $split->shares());
        self::assertSame([$cy, $ada], $split->members);
        self::assertSame([0, 10], [$split->remainder, $split->distributed()]);
        self::assertTrue($this->parties->purse($this->party)->isEmpty());
    }

    public function testSharesGoOnlyToMembers(): void
    {
        $this->form();
        $this->join('1');
        $this->parties->deposit($this->party, 'gp', 10);

        $this->assertRefused(Refusal::NotMember, fn (): Split => $this->parties->shares($this->party, 'gp', [$this->character('9')]));
    }

    public function testSharesNeedSomeoneToReceiveThem(): void
    {
        $this->form();
        $ada = $this->join('1');
        $this->parties->setStanding($this->party, $ada, Standing::Away);
        $this->parties->deposit($this->party, 'gp', 10);

        $this->assertRefused(Refusal::NoOneToShare, fn (): Split => $this->parties->distribute($this->party, 'gp'));
        $this->assertRefused(Refusal::NoOneToShare, fn (): Split => $this->parties->shares($this->party, 'gp', []));
        self::assertSame(10, $this->parties->purse($this->party)->amount('gp'));
    }

    public function testAShareSmallerThanACoinLeavesThePurseAlone(): void
    {
        $this->form();
        $this->join('1');
        $this->join('2');
        $this->join('3');
        $this->parties->deposit($this->party, 'cp', 2);

        $split = $this->parties->distribute($this->party, 'cp');

        self::assertSame([0, 2, 0], [$split->share, $split->remainder, $split->distributed()]);
        self::assertSame(2, $this->parties->purse($this->party)->amount('cp'));
        self::assertSame('party:7 distributed - cp -', $this->lastChange());
        self::assertSame(0, $this->parties->shares($this->party, 'gp')->share);
    }

    public function testSharesCheckTheCurrencyKey(): void
    {
        $this->form();
        $this->join('1');

        $this->expectException(InvalidParty::class);

        $this->parties->shares($this->party, 'GP');
    }

    public function testLootIsAddedGivenAndRemoved(): void
    {
        $this->form();
        $ada = $this->join('1');

        $rope = $this->parties->addLoot($this->party, 'rope', 'Silk rope', 3, null, $this->gm);
        self::assertSame(['Silk rope', 3, null, '2026-11-25 10:00:00'], [$rope->name, $rope->quantity, $rope->holder, $rope->addedAt->format('Y-m-d H:i:s')]);

        $given = $this->parties->giveLoot($this->party, 'rope', $ada, $this->gm);
        self::assertSame($ada->toString(), $given->holder?->toString());
        self::assertSame($ada->toString(), $this->parties->lootItem($this->party, 'rope')->holder?->toString());

        $left = $this->parties->removeLoot($this->party, 'rope', 2);
        self::assertSame(1, $left?->quantity);
        self::assertSame(1, $this->parties->lootItem($this->party, 'rope')->quantity);

        self::assertTrue($this->parties->giveLoot($this->party, 'rope', null)->isInStash());
        self::assertNull($this->parties->removeLoot($this->party, 'rope', 1));
        self::assertSame([], $this->parties->loot($this->party));
        self::assertSame([
            'party:7 loot_added - rope player:1',
            'party:7 loot_given character:1 rope player:1',
            'party:7 loot_removed character:1 rope -',
            'party:7 loot_given - rope -',
            'party:7 loot_removed - rope -',
        ], \array_slice($this->recorder->summary(), 2));
    }

    public function testLootIsCarriedOnlyByMembers(): void
    {
        $this->form();
        $this->parties->addLoot($this->party, 'rope', 'Rope');

        $this->assertRefused(Refusal::NotMember, fn (): LootItem => $this->parties->addLoot($this->party, 'torch', 'Torch', 1, $this->character('9')));
        $this->assertRefused(Refusal::NotMember, fn (): LootItem => $this->parties->giveLoot($this->party, 'rope', $this->character('9')));
        self::assertNull($this->store->lootItem('party:7', 'torch'));
    }

    public function testLootIsAddedOncePerKeyAndBounded(): void
    {
        $parties = new Parties($this->store, $this->clock, new Limits(lootPerParty: 2));
        $parties->form($this->party, $this->campaign, 'Lanterns');

        $ada = $parties->join($this->party, $this->character('1'))->member;
        $parties->addLoot($this->party, 'rope', 'Rope', 1, $ada);

        $this->assertRefused(Refusal::LootExists, fn (): LootItem => $parties->addLoot($this->party, 'rope', 'Other'));
        self::assertSame('Rope', $parties->lootItem($this->party, 'rope')->name);

        $parties->addLoot($this->party, 'torch', 'Torch');
        $this->assertRefused(Refusal::TooMuchLoot, fn (): LootItem => $parties->addLoot($this->party, 'gem', 'Gem'));
    }

    public function testLootIsReadInTheOrderItWasAdded(): void
    {
        $this->form();
        $this->parties->addLoot($this->party, 'torch', 'Torch');
        $this->parties->addLoot($this->party, 'gem', 'Gem');

        $this->clock->modify('-1 minute');
        $this->parties->addLoot($this->party, 'zither', 'Zither');

        self::assertSame(['zither', 'gem', 'torch'], array_map(static fn (LootItem $item): string => $item->key, $this->parties->loot($this->party)));
        self::assertSame(3, $this->store->countLoot('party:7'));
    }

    public function testRemovingLootIsBounded(): void
    {
        $this->form();
        $this->parties->addLoot($this->party, 'rope', 'Rope', 2);

        $this->assertRefused(Refusal::NotEnoughLoot, fn (): ?LootItem => $this->parties->removeLoot($this->party, 'rope', 3));

        try {
            $this->parties->removeLoot($this->party, 'rope', 0);
            self::fail('A quantity of zero was removed.');
        } catch (InvalidParty $invalidParty) {
            self::assertSame('A quantity removed is at least 1; 0 is not.', $invalidParty->getMessage());
        }

        self::assertSame(2, $this->parties->lootItem($this->party, 'rope')->quantity);
    }

    public function testUnknownLootIsReported(): void
    {
        $this->form();

        $this->expectException(UnknownParty::class);
        $this->expectExceptionMessageIsOrContains('The party "party:7" holds no loot under the key "rope".');

        $this->parties->giveLoot($this->party, 'rope', null);
    }

    public function testLootOfAnUnknownPartyIsNotRead(): void
    {
        $this->expectException(UnknownParty::class);
        $this->expectExceptionMessageIsOrContains('No party is known as "party:7".');

        $this->parties->loot($this->party);
    }

    public function testAddingLootToAnUnknownPartyIsReported(): void
    {
        $this->expectException(UnknownParty::class);

        $this->parties->addLoot($this->party, 'rope', 'Rope');
    }

    public function testLootAndNotesOfAnUnknownPartyAreReportedAsAnUnknownParty(): void
    {
        foreach ([
            fn (): mixed => $this->parties->lootItem($this->party, 'rope'),
            fn (): mixed => $this->parties->loot($this->party),
            fn (): mixed => $this->parties->note($this->party, 'lead'),
            fn (): mixed => $this->parties->notes($this->party),
        ] as $read) {
            try {
                $read();
                self::fail('An unknown party is reported.');
            } catch (UnknownParty $e) {
                self::assertSame('No party is known as "party:7".', $e->getMessage());
            }
        }
    }

    public function testAMemberOfAnUnknownPartyIsReportedAsAnUnknownParty(): void
    {
        foreach ([
            function (): void {
                $this->parties->leave($this->party, $this->character('1'));
            },
            fn (): mixed => $this->parties->writeNote($this->party, 'Not a key', $this->character('1'), 'A lead'),
        ] as $call) {
            try {
                $call();
                self::fail('An unknown party is reported.');
            } catch (UnknownParty $e) {
                self::assertSame('No party is known as "party:7".', $e->getMessage());
            }
        }
    }

    public function testLootIsAddedOneAtATimeByDefault(): void
    {
        $this->form();

        self::assertSame(1, $this->parties->addLoot($this->party, 'rope', 'Rope')->quantity);
    }

    public function testNotesWrittenTogetherAreReadByPartyThenKey(): void
    {
        $other = ResourceReference::of('party', '8');
        $this->form();
        $this->parties->form($other, $this->campaign, 'Others');
        $ada = $this->join('1');
        $this->parties->join($other, $ada);
        $this->parties->writeNote($other, 'a', $ada, 'A');
        $this->parties->writeNote($this->party, 'b', $ada, 'B');
        $this->parties->writeNote($this->party, 'a', $ada, 'C');

        self::assertSame(['party:7 a', 'party:7 b', 'party:8 a'], array_map(
            static fn (Note $note): string => $note->party->toString().' '.$note->key,
            $this->store->notesBy($ada->toString()),
        ));
    }

    public function testMembersWriteEditAndRemoveNotes(): void
    {
        $this->form();
        $ada = $this->join('1');

        $note = $this->parties->writeNote($this->party, 'lead', $ada, 'The miller lies.');
        self::assertSame(['The miller lies.', $ada->toString(), null], [$note->text, $note->author?->toString(), $note->editedAt]);

        $this->clock->modify('+1 hour');
        $edited = $this->parties->editNote($this->party, 'lead', 'The miller is innocent.', $this->gm);
        self::assertSame(['The miller is innocent.', $ada->toString(), '2026-11-25 10:00:00', '2026-11-25 11:00:00'], [$edited->text, $edited->author?->toString(), $edited->writtenAt->format('Y-m-d H:i:s'), $edited->editedAt?->format('Y-m-d H:i:s')]);
        self::assertSame('The miller is innocent.', $this->parties->note($this->party, 'lead')->text);

        $this->parties->removeNote($this->party, 'lead', $this->gm);
        self::assertSame([], $this->parties->notes($this->party));
        self::assertSame([
            'party:7 note_written character:1 lead -',
            'party:7 note_edited character:1 lead player:1',
            'party:7 note_removed character:1 lead player:1',
        ], \array_slice($this->recorder->summary(), 2));
    }

    public function testOnlyAMemberWritesANote(): void
    {
        $this->form();

        $this->assertRefused(Refusal::NotMember, fn (): Note => $this->parties->writeNote($this->party, 'lead', $this->character('9'), 'A lead'));
        self::assertSame(0, $this->store->countNotes('party:7'));
    }

    public function testNotesAreWrittenOncePerKeyAndBounded(): void
    {
        $parties = new Parties($this->store, $this->clock, new Limits(notesPerParty: 2));
        $parties->form($this->party, $this->campaign, 'Lanterns');

        $ada = $parties->join($this->party, $this->character('1'))->member;
        $parties->writeNote($this->party, 'lead', $ada, 'A lead');

        $this->assertRefused(Refusal::NoteExists, fn (): Note => $parties->writeNote($this->party, 'lead', $ada, 'Other'));
        self::assertSame('A lead', $parties->note($this->party, 'lead')->text);

        $parties->writeNote($this->party, 'plan', $ada, 'A plan');
        $this->assertRefused(Refusal::TooManyNotes, fn (): Note => $parties->writeNote($this->party, 'name', $ada, 'A name'));
    }

    public function testNotesAreReadInTheOrderTheyWereWritten(): void
    {
        $this->form();
        $ada = $this->join('1');
        $this->parties->writeNote($this->party, 'plan', $ada, 'A plan');
        $this->parties->writeNote($this->party, 'lead', $ada, 'A lead');

        $this->clock->modify('-1 minute');
        $this->parties->writeNote($this->party, 'zone', $ada, 'A zone');

        self::assertSame(['zone', 'lead', 'plan'], array_map(static fn (Note $note): string => $note->key, $this->parties->notes($this->party)));
    }

    public function testUnknownNotesAreReported(): void
    {
        $this->form();

        $this->expectException(UnknownParty::class);
        $this->expectExceptionMessageIsOrContains('The party "party:7" holds no note under the key "lead".');

        $this->parties->removeNote($this->party, 'lead');
    }

    public function testNotesOfAnUnknownPartyAreNotRead(): void
    {
        $this->expectException(UnknownParty::class);

        $this->parties->notes($this->party);
    }

    public function testWritingANoteInAnUnknownPartyIsReported(): void
    {
        $this->expectException(UnknownParty::class);

        $this->parties->writeNote($this->party, 'lead', $this->character('1'), 'A lead');
    }

    public function testTheCompositionIsReadAgainstTheRolesOfASystem(): void
    {
        $this->form();
        $this->parties->join($this->party, $this->character('1'), ['healer']);
        $this->parties->join($this->party, $this->character('2'), ['healer']);
        $this->parties->join($this->party, $this->character('3'), ['healer']);

        $composition = $this->parties->composition($this->party, new RoleCatalogue([new Role('healer', 'Healer'), new Role('tank', 'Tank')]));

        self::assertSame(Coverage::Crowded, $composition->coverageOf('healer')?->coverage);
        self::assertSame(Coverage::Missing, $composition->coverageOf('tank')?->coverage);
        self::assertFalse($composition->isBalanced());
    }

    public function testAPartyOfAGameWithoutRolesWorksWithoutAny(): void
    {
        $this->form();
        $this->parties->join($this->party, $this->character('1'));
        $this->parties->join($this->party, $this->character('2'));

        self::assertSame([[], []], array_map(static fn (Member $member): array => $member->roles, $this->parties->members($this->party)));

        $composition = $this->parties->composition($this->party, RoleCatalogue::none());

        self::assertFalse($composition->hasAdvice());
        self::assertTrue($composition->isBalanced());
        self::assertSame(2, $composition->active);
    }

    public function testAForgottenMemberLeavesEveryPartyAndItsNotesLoseTheirAuthor(): void
    {
        $ada = $this->character('1');
        $bo = $this->character('2');
        $other = ResourceReference::of('party', '8');
        $this->form();
        $this->parties->form($other, $this->campaign, 'Others');
        $this->parties->join($this->party, $ada);
        $this->parties->join($this->party, $bo);
        $this->parties->join($other, $ada);
        $this->parties->addLoot($this->party, 'rope', 'Rope', 1, $ada);
        $this->parties->addLoot($this->party, 'torch', 'Torch', 1, $bo);
        $this->parties->writeNote($this->party, 'lead', $ada, 'A lead');
        $this->parties->writeNote($this->party, 'plan', $bo, 'A plan');
        $this->parties->writeNote($other, 'old', $ada, 'An old note');
        $this->parties->leave($other, $ada);
        $this->parties->join($other, $ada);

        $this->parties->forget($ada);

        self::assertSame([], $this->parties->partiesOf($ada));
        self::assertSame(['character:2'], $this->references($this->parties->members($this->party)));
        self::assertNull($this->parties->lootItem($this->party, 'rope')->holder);
        self::assertSame('character:2', $this->parties->lootItem($this->party, 'torch')->holder?->toString());
        self::assertNull($this->parties->note($this->party, 'lead')->author);
        self::assertSame('A lead', $this->parties->note($this->party, 'lead')->text);
        self::assertSame('character:2', $this->parties->note($this->party, 'plan')->author?->toString());
        self::assertNull($this->parties->note($other, 'old')->author);
        self::assertSame([], $this->store->notesBy('character:1'));
        self::assertSame(['party:7 forgotten character:1 - -', 'party:8 forgotten character:1 - -'], \array_slice($this->recorder->summary(), -2));
    }

    public function testTheMembershipsOfAMemberAreReadTheEarliestFirst(): void
    {
        $ada = $this->character('1');
        $this->parties->form(ResourceReference::of('party', 'b'), $this->campaign, 'B');
        $this->parties->form(ResourceReference::of('party', 'a'), $this->campaign, 'A');
        $this->parties->form(ResourceReference::of('party', 'c'), $this->campaign, 'C');
        $this->parties->join(ResourceReference::of('party', 'b'), $ada);
        $this->parties->join(ResourceReference::of('party', 'a'), $ada);

        $this->clock->modify('-1 minute');
        $this->parties->join(ResourceReference::of('party', 'c'), $ada);

        self::assertSame(['C', 'A', 'B'], array_map(static fn (Party $party): string => $party->name, $this->parties->partiesOf($ada)));
    }

    public function testPartiesOfAMemberSkipAPartyTheStoreNoLongerHolds(): void
    {
        $ada = $this->character('1');
        $this->form();
        $this->join('1');
        $this->store->saveMember(new Member(ResourceReference::of('party', 'gone'), $ada, [], Standing::Active, $this->clock->now()));

        self::assertSame(['Lanterns'], array_map(static fn (Party $party): string => $party->name, $this->parties->partiesOf($ada)));
    }

    public function testObserversAreReadOnceFromAnyIterable(): void
    {
        $recorder = new Recorder();
        $parties = new Parties($this->store, $this->clock, new Limits(), (static function () use ($recorder): iterable {
            yield $recorder;
        })());

        $parties->form($this->party, $this->campaign, 'Lanterns');
        $parties->rename($this->party, 'Lamps');

        self::assertSame(['party:7 formed - - -', 'party:7 updated - - -'], $recorder->summary());
    }

    public function testEveryChangeIsAKnownCase(): void
    {
        self::assertSame(Change::Forgotten, Change::from('forgotten'));
        self::assertCount(17, Change::cases());
    }

    private function form(): Party
    {
        return $this->parties->form($this->party, $this->campaign, 'Lanterns');
    }

    private function character(string $id): ResourceReference
    {
        return ResourceReference::of('character', $id);
    }

    private function join(string $id): ResourceReference
    {
        return $this->parties->join($this->party, $this->character($id))->member;
    }

    private function lastChange(): string
    {
        $summary = $this->recorder->summary();

        return (string) end($summary);
    }

    /**
     * @param list<Member> $members
     *
     * @return list<string>
     */
    private function references(array $members): array
    {
        return array_map(static fn (Member $member): string => $member->member->toString(), $members);
    }

    /**
     * @param Closure(): mixed $act
     */
    private function assertRefused(Refusal $refusal, Closure $act): void
    {
        try {
            $act();
            self::fail(\sprintf('The act was not refused with "%s".', $refusal->value));
        } catch (PartyRefused $partyRefused) {
            self::assertSame($refusal, $partyRefused->refusal);
        }
    }
}
