<?php

declare(strict_types=1);

namespace Tilttek\Party\Tests\Unit;

use Closure;
use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Party\Exception\InvalidParty;
use Tilttek\Party\Exception\PartyException;
use Tilttek\Party\Exception\PartyRefused;
use Tilttek\Party\Exception\UnknownParty;
use Tilttek\Party\Limits;
use Tilttek\Party\LootItem;
use Tilttek\Party\Member;
use Tilttek\Party\Note;
use Tilttek\Party\Party;
use Tilttek\Party\Purse;
use Tilttek\Party\Refusal;
use Tilttek\Party\Split;
use Tilttek\Party\Standing;
use Tilttek\Party\Text;

#[CoversClass(Party::class)]
#[CoversClass(Member::class)]
#[CoversClass(Purse::class)]
#[CoversClass(Split::class)]
#[CoversClass(LootItem::class)]
#[CoversClass(Note::class)]
#[CoversClass(Limits::class)]
#[CoversClass(Text::class)]
#[CoversClass(InvalidParty::class)]
#[CoversClass(PartyRefused::class)]
#[CoversClass(UnknownParty::class)]
final class ValuesTest extends TestCase
{
    private ResourceReference $party;

    private DateTimeImmutable $now;

    protected function setUp(): void
    {
        $this->party = ResourceReference::of('party', '7');
        $this->now = new DateTimeImmutable('2026-11-25 10:00:00');
    }

    public function testAPartyKeepsItsCampaignNameAndSystem(): void
    {
        $party = new Party($this->party, ResourceReference::of('campaign', '3'), 'The Lantern Bearers', 'srd-5.2', $this->now);

        self::assertSame(['campaign:3', 'The Lantern Bearers', 'srd-5.2'], [$party->campaign->toString(), $party->name, $party->system]);

        $renamed = $party->renamed('Lanterns');
        self::assertSame(['Lanterns', 'srd-5.2', $this->now, 'party:7'], [$renamed->name, $renamed->system, $renamed->formedAt, $renamed->reference->toString()]);

        $other = $party->withSystem('homebrew.lanterns');
        self::assertSame(['The Lantern Bearers', 'homebrew.lanterns', 'campaign:3'], [$other->name, $other->system, $other->campaign->toString()]);
        self::assertNull($party->withSystem(null)->system);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badNames(): iterable
    {
        yield 'empty' => [''];
        yield 'leading space' => [' Lanterns'];
        yield 'trailing space' => ['Lanterns '];
        yield 'control character' => ["Lan\nterns"];
        yield 'too long' => [str_repeat('é', 101)];
        yield 'invalid UTF-8' => ["\xC3"];
    }

    #[DataProvider('badNames')]
    public function testAPartyNameIsBounded(string $name): void
    {
        $this->expectException(InvalidParty::class);
        $this->expectExceptionMessageIsOrContains('A party name is 1 to 100 characters of UTF-8 without control characters nor surrounding spaces.');

        new Party($this->party, ResourceReference::of('campaign', '3'), $name, null, $this->now);
    }

    public function testAPartyNameOfOneHundredCharactersIsKept(): void
    {
        $name = str_repeat('é', 100);

        self::assertSame($name, new Party($this->party, ResourceReference::of('campaign', '3'), $name, null, $this->now)->name);
        self::assertSame('L', new Party($this->party, ResourceReference::of('campaign', '3'), 'L', null, $this->now)->name);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badKeys(): iterable
    {
        yield 'empty' => [''];
        yield 'upper case' => ['Healer'];
        yield 'space' => ['role healer'];
        yield 'leading dot' => ['.healer'];
        yield 'trailing dot' => ['healer.'];
        yield 'double dot' => ['role..healer'];
        yield 'too long' => [str_repeat('a', 97)];
        yield 'trailing line break' => ["healer\n"];
    }

    #[DataProvider('badKeys')]
    public function testAKeyIsBounded(string $key): void
    {
        $this->expectException(InvalidParty::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The key of a game system must be 1 to 96 lowercase letters, digits, "_", "-" or single dots between them; "%s" is not.', $key));

        new Party($this->party, ResourceReference::of('campaign', '3'), 'Lanterns', $key, $this->now);
    }

    public function testAKeyOfNinetySixCharactersWithDotsDashesAndUnderscoresIsKept(): void
    {
        $key = 'a_b-c.'.str_repeat('d', 90);

        self::assertSame($key, new Party($this->party, ResourceReference::of('campaign', '3'), 'Lanterns', $key, $this->now)->system);
        self::assertSame('7', new Party($this->party, ResourceReference::of('campaign', '3'), 'Lanterns', '7', $this->now)->system);
    }

    public function testAMemberKeepsItsDistinctRolesInOrder(): void
    {
        $member = new Member($this->party, ResourceReference::of('character', '12'), ['role.healer', 'role.scout', 'role.healer'], Standing::Active, $this->now);
        self::assertSame(['7', 'role.tank'], new Member($this->party, ResourceReference::of('character', '12'), ['7', 'role.tank'], Standing::Active, $this->now)->roles);

        self::assertSame(['role.healer', 'role.scout'], $member->roles);
        self::assertTrue($member->fills('role.scout'));
        self::assertFalse($member->fills('role.tank'));
        self::assertTrue($member->isActive());

        $away = $member->withStanding(Standing::Away);
        self::assertFalse($away->isActive());
        self::assertSame([['role.healer', 'role.scout'], $this->now, 'character:12', 'party:7'], [$away->roles, $away->since, $away->member->toString(), $away->party->toString()]);

        $changed = $away->withRoles(['role.tank']);
        self::assertSame([['role.tank'], Standing::Away, $this->now, 'character:12', 'party:7'], [$changed->roles, $changed->standing, $changed->since, $changed->member->toString(), $changed->party->toString()]);
    }

    public function testAMemberFillsAtMostFiveRoles(): void
    {
        $five = new Member($this->party, ResourceReference::of('character', '12'), ['a', 'b', 'c', 'd', 'e', 'a'], Standing::Active, $this->now);
        self::assertCount(5, $five->roles);

        $this->expectException(InvalidParty::class);
        $this->expectExceptionMessageIsOrContains('A member fills at most 5 roles; 6 were given.');

        new Member($this->party, ResourceReference::of('character', '12'), ['a', 'b', 'c', 'd', 'e', 'f'], Standing::Active, $this->now);
    }

    public function testARoleKeyIsChecked(): void
    {
        $this->expectException(InvalidParty::class);
        $this->expectExceptionMessageIsOrContains('The key of a role must be');

        new Member($this->party, ResourceReference::of('character', '12'), ['Healer'], Standing::Active, $this->now);
    }

    public function testAPurseAddsAndTakesCoins(): void
    {
        $purse = new Purse();
        self::assertTrue($purse->isEmpty());
        self::assertSame(0, $purse->amount('gp'));

        $purse = $purse->deposit('gp', 250)->deposit('sp', 30)->deposit('gp', 50);
        self::assertSame(['gp' => 300, 'sp' => 30], $purse->amounts());
        self::assertFalse($purse->isEmpty());

        $purse = $purse->withdraw('gp', 300);
        self::assertSame(['sp' => 30], $purse->amounts());
        self::assertSame(0, $purse->amount('gp'));
        self::assertSame(29, $purse->withdraw('sp', 1)->amount('sp'));
    }

    public function testAPurseDropsCurrenciesAtZero(): void
    {
        self::assertSame(['gp' => 1, 'g7' => 2], new Purse(['cp' => 0, 'gp' => 1, 'g7' => 2])->amounts());
    }

    public function testAPurseRefusesToSpendWhatItLacks(): void
    {
        try {
            new Purse(['gp' => 10])->withdraw('gp', 11);
            self::fail('The withdrawal went through.');
        } catch (PartyRefused $partyRefused) {
            self::assertSame(Refusal::NotEnoughFunds, $partyRefused->refusal);
            self::assertSame('The purse holds less of this currency than asked.', $partyRefused->getMessage());
        }

        self::assertTrue(new Purse(['gp' => 10])->withdraw('gp', 10)->isEmpty());
    }

    /**
     * @return iterable<string, array{Closure(): mixed, string}>
     */
    public static function badPurses(): iterable
    {
        yield 'negative' => [static fn (): Purse => new Purse(['gp' => -1]), 'A purse holds 0 to 1000000000000 of a currency; -1 of "gp" is out of bounds.'];
        yield 'too much' => [static fn (): Purse => new Purse(['gp' => Purse::MAX_AMOUNT + 1]), 'A purse holds 0 to 1000000000000 of a currency; 1000000000001 of "gp" is out of bounds.'];
        yield 'bad currency' => [static fn (): Purse => new Purse(['GP' => 1]), 'The key of a currency must be'];
        yield 'currency starting with a digit' => [static fn (): Purse => new Purse(['7gp' => 1]), 'A currency key starts with a letter; "7gp" does not.'];
        yield 'deposit of a currency starting with a digit' => [static fn (): Purse => new Purse()->deposit('7gp', 1), 'A currency key starts with a letter; "7gp" does not.'];
        yield 'deposit of zero' => [static fn (): Purse => new Purse()->deposit('gp', 0), 'An amount is 1 to 1000000000000 coins; 0 is not.'];
        yield 'deposit too large' => [static fn (): Purse => new Purse()->deposit('gp', Purse::MAX_AMOUNT + 1), 'An amount is 1 to 1000000000000 coins; 1000000000001 is not.'];
        yield 'deposit overflow' => [static fn (): Purse => new Purse(['gp' => Purse::MAX_AMOUNT])->deposit('gp', 1), 'A purse holds at most 1000000000000 of a currency; the deposit would make 1000000000001 of "gp".'];
        yield 'withdrawal of zero' => [static fn (): Purse => new Purse(['gp' => 1])->withdraw('gp', 0), 'An amount is 1 to 1000000000000 coins; 0 is not.'];
        yield 'withdrawal of a bad currency' => [static fn (): Purse => new Purse()->withdraw('GP', 1), 'The key of a currency must be'];
        yield 'deposit of a bad currency' => [static fn (): Purse => new Purse()->deposit('GP', 1), 'The key of a currency must be'];
    }

    /**
     * @param Closure(): mixed $build
     */
    #[DataProvider('badPurses')]
    public function testAPurseIsBounded(Closure $build, string $message): void
    {
        $this->expectException(InvalidParty::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testAPurseHoldsTheLargestAmounts(): void
    {
        self::assertSame(Purse::MAX_AMOUNT, new Purse(['gp' => Purse::MAX_AMOUNT - 1])->deposit('gp', 1)->amount('gp'));
        self::assertSame(Purse::MAX_AMOUNT, new Purse()->deposit('gp', Purse::MAX_AMOUNT)->amount('gp'));
        self::assertTrue(new Purse(['gp' => Purse::MAX_AMOUNT])->withdraw('gp', Purse::MAX_AMOUNT)->isEmpty());
    }

    public function testAPurseHoldsAtMostTwentyCurrencies(): void
    {
        $twenty = [];

        for ($i = 1; $i <= 20; ++$i) {
            $twenty['c'.$i] = 1;
        }

        self::assertCount(20, new Purse($twenty)->amounts());

        $this->expectException(InvalidParty::class);
        $this->expectExceptionMessageIsOrContains('A purse holds at most 20 currencies.');

        new Purse($twenty)->deposit('c21', 1);
    }

    public function testASplitTellsEachShare(): void
    {
        $ada = ResourceReference::of('character', '1');
        $bo = ResourceReference::of('character', '2');
        $split = new Split('gp', [$ada, $bo], 12, 1);

        self::assertSame(24, $split->distributed());
        self::assertSame(12, $split->shareOf(ResourceReference::of('character', '2')));
        self::assertSame(0, $split->shareOf(ResourceReference::of('character', '3')));
        self::assertSame(['character:1' => 12, 'character:2' => 12], $split->shares());
        self::assertSame(['gp', 1, [$ada, $bo]], [$split->currency, $split->remainder, $split->members]);
    }

    public function testLootIsCarriedOrStashed(): void
    {
        $holder = ResourceReference::of('character', '12');
        $item = new LootItem($this->party, 'loot.rope', 'Silk rope', 2, null, $this->now);

        self::assertTrue($item->isInStash());

        $carried = $item->heldBy($holder);
        self::assertFalse($carried->isInStash());
        self::assertSame(['loot.rope', 'Silk rope', 2, $this->now, 'party:7'], [$carried->key, $carried->name, $carried->quantity, $carried->addedAt, $carried->party->toString()]);

        $fewer = $carried->withQuantity(1);
        self::assertSame([1, $holder, 'Silk rope', 'loot.rope', $this->now], [$fewer->quantity, $fewer->holder, $fewer->name, $fewer->key, $fewer->addedAt]);
        self::assertSame(LootItem::MAX_QUANTITY, $item->withQuantity(LootItem::MAX_QUANTITY)->quantity);
    }

    /**
     * @return iterable<string, array{Closure(ResourceReference, DateTimeImmutable): mixed, string}>
     */
    public static function badLoot(): iterable
    {
        yield 'no quantity' => [static fn (ResourceReference $party, DateTimeImmutable $now): LootItem => new LootItem($party, 'rope', 'Rope', 0, null, $now), 'A loot quantity is 1 to 1000000; 0 is not.'];
        yield 'too many' => [static fn (ResourceReference $party, DateTimeImmutable $now): LootItem => new LootItem($party, 'rope', 'Rope', 1_000_001, null, $now), 'A loot quantity is 1 to 1000000; 1000001 is not.'];
        yield 'bad key' => [static fn (ResourceReference $party, DateTimeImmutable $now): LootItem => new LootItem($party, 'Rope', 'Rope', 1, null, $now), 'The key of a loot entry must be'];
        yield 'bad name' => [static fn (ResourceReference $party, DateTimeImmutable $now): LootItem => new LootItem($party, 'rope', ' Rope', 1, null, $now), 'A loot name is 1 to 100 characters'];
    }

    /**
     * @param Closure(ResourceReference, DateTimeImmutable): mixed $build
     */
    #[DataProvider('badLoot')]
    public function testLootIsBounded(Closure $build, string $message): void
    {
        $this->expectException(InvalidParty::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build($this->party, $this->now);
    }

    public function testANoteKeepsItsTextAuthorAndMoments(): void
    {
        $author = ResourceReference::of('character', '12');
        $note = new Note($this->party, 'lead.mill', "The miller lies.\n\tAsk the daughter.", $author, $this->now);

        self::assertNull($note->editedAt);

        $later = new DateTimeImmutable('2026-11-26 10:00:00');
        $edited = $note->edited('The miller is innocent.', $later);
        self::assertSame(['The miller is innocent.', $author, $this->now, $later, 'lead.mill', 'party:7'], [$edited->text, $edited->author, $edited->writtenAt, $edited->editedAt, $edited->key, $edited->party->toString()]);

        $anonymous = $edited->withoutAuthor();
        self::assertSame([null, 'The miller is innocent.', $this->now, $later, 'lead.mill', 'party:7'], [$anonymous->author, $anonymous->text, $anonymous->writtenAt, $anonymous->editedAt, $anonymous->key, $anonymous->party->toString()]);
        self::assertSame(str_repeat('é', 5000), new Note($this->party, 'long', str_repeat('é', 5000), null, $this->now)->text);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function badTexts(): iterable
    {
        yield 'empty' => [''];
        yield 'blank' => [" \n\t "];
        yield 'carriage return' => ["One\r\nTwo"];
        yield 'null byte' => ["One\0Two"];
        yield 'too long' => [str_repeat('é', 5001)];
        yield 'invalid UTF-8' => ["\xC3"];
    }

    #[DataProvider('badTexts')]
    public function testANoteTextIsBounded(string $text): void
    {
        $this->expectException(InvalidParty::class);
        $this->expectExceptionMessageIsOrContains('A note is 1 to 5000 characters of UTF-8, not blank, with line breaks and tabs the only control characters.');

        new Note($this->party, 'lead', $text, null, $this->now);
    }

    public function testANoteKeyIsChecked(): void
    {
        $this->expectException(InvalidParty::class);
        $this->expectExceptionMessageIsOrContains('The key of a note must be');

        new Note($this->party, 'Lead', 'Text', null, $this->now);
    }

    public function testLimitsHaveDefaultsAndBounds(): void
    {
        $limits = new Limits();
        self::assertSame([12, 10, 500, 200], [$limits->membersPerParty, $limits->partiesPerMember, $limits->lootPerParty, $limits->notesPerParty]);

        $edges = new Limits(100, 100, 10_000, 5000);
        self::assertSame([100, 100, 10_000, 5000], [$edges->membersPerParty, $edges->partiesPerMember, $edges->lootPerParty, $edges->notesPerParty]);

        $low = new Limits(1, 1, 1, 1);
        self::assertSame([1, 1, 1, 1], [$low->membersPerParty, $low->partiesPerMember, $low->lootPerParty, $low->notesPerParty]);
    }

    /**
     * @return iterable<string, array{Closure(): Limits, string}>
     */
    public static function badLimits(): iterable
    {
        yield 'no member' => [static fn (): Limits => new Limits(membersPerParty: 0), 'The limit of members per party is 1 to 100; it is 0.'];
        yield 'too many members' => [static fn (): Limits => new Limits(membersPerParty: 101), 'The limit of members per party is 1 to 100; it is 101.'];
        yield 'no party' => [static fn (): Limits => new Limits(partiesPerMember: 0), 'The limit of parties per member is 1 to 100; it is 0.'];
        yield 'too many parties' => [static fn (): Limits => new Limits(partiesPerMember: 101), 'The limit of parties per member is 1 to 100; it is 101.'];
        yield 'no loot' => [static fn (): Limits => new Limits(lootPerParty: 0), 'The limit of loot entries per party is 1 to 10000; it is 0.'];
        yield 'too much loot' => [static fn (): Limits => new Limits(lootPerParty: 10_001), 'The limit of loot entries per party is 1 to 10000; it is 10001.'];
        yield 'no note' => [static fn (): Limits => new Limits(notesPerParty: 0), 'The limit of notes per party is 1 to 5000; it is 0.'];
        yield 'too many notes' => [static fn (): Limits => new Limits(notesPerParty: 5001), 'The limit of notes per party is 1 to 5000; it is 5001.'];
    }

    /**
     * @param Closure(): Limits $build
     */
    #[DataProvider('badLimits')]
    public function testLimitsAreBounded(Closure $build, string $message): void
    {
        $this->expectException(InvalidParty::class);
        $this->expectExceptionMessageIsOrContains($message);

        $build();
    }

    public function testEveryExceptionIsAPartyException(): void
    {
        self::assertInstanceOf(PartyException::class, InvalidParty::because('x'));
        self::assertInstanceOf(PartyException::class, PartyRefused::because(Refusal::Full));
        self::assertSame('No party is known as "party:7".', UnknownParty::named('party:7')->getMessage());
        self::assertSame('The party "party:7" holds no loot under the key "rope".', UnknownParty::loot('party:7', 'rope')->getMessage());
        self::assertSame('The party "party:7" holds no note under the key "lead".', UnknownParty::note('party:7', 'lead')->getMessage());
    }

    /**
     * @return iterable<string, array{Refusal, string}>
     */
    public static function refusals(): iterable
    {
        yield 'formed' => [Refusal::AlreadyFormed, 'A party already holds this reference.'];
        yield 'member' => [Refusal::AlreadyMember, 'This member already belongs to the party.'];
        yield 'not member' => [Refusal::NotMember, 'This member does not belong to the party.'];
        yield 'full' => [Refusal::Full, 'The party holds as many members as allowed.'];
        yield 'parties' => [Refusal::TooManyParties, 'This member belongs to as many parties as allowed.'];
        yield 'loot exists' => [Refusal::LootExists, 'The party already holds loot under this key.'];
        yield 'too much loot' => [Refusal::TooMuchLoot, 'The party holds as many loot entries as allowed.'];
        yield 'not enough loot' => [Refusal::NotEnoughLoot, 'The party holds fewer of this loot than asked.'];
        yield 'note exists' => [Refusal::NoteExists, 'The party already holds a note under this key.'];
        yield 'notes' => [Refusal::TooManyNotes, 'The party holds as many notes as allowed.'];
        yield 'funds' => [Refusal::NotEnoughFunds, 'The purse holds less of this currency than asked.'];
        yield 'no one' => [Refusal::NoOneToShare, 'No member is there to receive a share.'];
    }

    #[DataProvider('refusals')]
    public function testEachRefusalHasItsMessage(Refusal $refusal, string $message): void
    {
        $refused = PartyRefused::because($refusal);

        self::assertSame([$refusal, $message], [$refused->refusal, $refused->getMessage()]);
    }
}
