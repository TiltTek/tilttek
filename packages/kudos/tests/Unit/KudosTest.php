<?php

declare(strict_types=1);

namespace Tilttek\Kudos\Tests\Unit;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use ReflectionMethod;
use Symfony\Component\Clock\MockClock;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Exception\IdempotencyConflict;
use Tilttek\Activity\Filter;
use Tilttek\Activity\IdempotencyKey;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Verb;
use Tilttek\Kudos\Contract\KudosPreferences;
use Tilttek\Kudos\Exception\InvalidKudos;
use Tilttek\Kudos\Exception\KudosRefused;
use Tilttek\Kudos\Exception\UnknownMessage;
use Tilttek\Kudos\Given;
use Tilttek\Kudos\Kudos;
use Tilttek\Kudos\KudosCancelled;
use Tilttek\Kudos\KudosGiven;
use Tilttek\Kudos\Limits;
use Tilttek\Kudos\Message;
use Tilttek\Kudos\Messages;
use Tilttek\Kudos\Preferences\AcceptAll;
use Tilttek\Kudos\Refusal;
use Tilttek\Kudos\Tests\Unit\Fixture\Declines;
use Tilttek\Kudos\Tests\Unit\Fixture\ErasedBeforeCancel;
use Tilttek\Kudos\Tests\Unit\Fixture\Observed;
use Tilttek\Kudos\Thanks;
use Tilttek\Kudos\ThanksPage;
use Tilttek\Kudos\TimeZone\SiteTimeZone;

#[CoversClass(Kudos::class)]
#[CoversClass(Thanks::class)]
#[CoversClass(ThanksPage::class)]
#[CoversClass(Given::class)]
#[CoversClass(KudosGiven::class)]
#[CoversClass(KudosCancelled::class)]
#[CoversClass(KudosRefused::class)]
final class KudosTest extends TestCase
{
    private MockClock $clock;

    private Journal $journal;

    private Observed $observed;

    #[Override]
    protected function setUp(): void
    {
        $this->clock = new MockClock('2026-11-20 12:00:00', 'UTC');
        $this->journal = new Journal(new InMemoryActivityLog(), $this->clock);
        $this->observed = new Observed();
    }

    public function testThanksAreRecordedAsAnActivityAndTold(): void
    {
        $given = $this->kudos()->give(self::member('1'), self::member('2'), 'help', ResourceReference::of('comment', '4512'));

        self::assertTrue($given->new);
        self::assertStringStartsWith('kudos:', $given->thanks->key);
        self::assertSame(70, \strlen($given->thanks->key));
        self::assertSame('member:1', $given->thanks->giver->toString());
        self::assertSame('member:2', $given->thanks->receiver?->toString());
        self::assertSame('help', $given->thanks->message);
        self::assertSame('comment:4512', $given->thanks->object?->toString());
        self::assertSame('2026-11-20T12:00:00+00:00', $given->thanks->givenAt->format(DateTimeInterface::ATOM));

        $activity = $this->journal->find($given->thanks->key)?->activity;
        self::assertInstanceOf(Activity::class, $activity);
        self::assertSame(['key' => $given->thanks->key, 'member' => 'member:1', 'verb' => 'kudos.given', 'occurred_at' => '2026-11-20T12:00:00+00:00', 'object' => 'member:2', 'quantity' => 1, 'context' => ['message' => 'help', 'object' => 'comment:4512']], $activity->toArray());

        self::assertEquals([new KudosGiven($given->thanks->key, 'member:1', 'member:2', 'help', 'comment:4512', $given->thanks->givenAt)], $this->observed->given);
        self::assertSame([], $this->observed->cancelled);
    }

    public function testThanksWithoutAnObjectKeepNone(): void
    {
        $given = $this->kudos()->give(self::member('1'), self::member('2'), new Message('well_played', 'Any text'));

        self::assertNull($given->thanks->object);
        self::assertSame('well_played', $given->thanks->message);
        self::assertSame(['message' => 'well_played'], $this->journal->find($given->thanks->key)?->activity->context->values);
        self::assertNull($this->observed->given[0]->object);
    }

    public function testTheSameThanksClickedTwiceInADayAreGivenOnce(): void
    {
        $kudos = $this->kudos();
        $first = $kudos->give(self::member('1'), self::member('2'), 'help');
        $this->clock->sleep(3_600);
        $again = $kudos->give(self::member('1'), self::member('2'), 'help');

        self::assertFalse($again->new);
        self::assertSame($first->thanks->key, $again->thanks->key);
        self::assertSame('2026-11-20T12:00:00+00:00', $again->thanks->givenAt->format(DateTimeInterface::ATOM));
        self::assertCount(1, $this->observed->given);
        self::assertSame(1, $kudos->givenCount(self::member('1')));
        self::assertSame(4, $kudos->remainingToday(self::member('1')));
    }

    public function testTheKeyChangesWithTheReceiverTheMessageTheObjectAndTheDay(): void
    {
        $kudos = $this->kudos(new Limits(10, 10));
        $keys = [
            $kudos->give(self::member('1'), self::member('2'), 'help')->thanks->key,
            $kudos->give(self::member('1'), self::member('3'), 'help')->thanks->key,
            $kudos->give(self::member('1'), self::member('2'), 'great_idea')->thanks->key,
            $kudos->give(self::member('1'), self::member('2'), 'help', ResourceReference::of('comment', '1'))->thanks->key,
            $kudos->give(self::member('4'), self::member('2'), 'help')->thanks->key,
        ];
        $this->clock->modify('+1 day');
        $keys[] = $kudos->give(self::member('1'), self::member('2'), 'help')->thanks->key;

        self::assertCount(6, array_unique($keys));
        self::assertCount(6, $this->observed->given);
    }

    public function testTheKeyWritesEachPartAfterItsLength(): void
    {
        // Written end to end, both pairs of giver and receiver would read "member:1a:2x:1".
        $kudos = $this->kudos();
        $first = $kudos->give(ResourceReference::of('member', '1a:2'), ResourceReference::of('x', '1'), 'help');
        $second = $kudos->give(ResourceReference::of('member', '1'), ResourceReference::of('a', '2x:1'), 'help');

        self::assertTrue($second->new);
        self::assertNotSame($first->thanks->key, $second->thanks->key);
    }

    public function testTheKeyIsTheHashOfEachPartAfterItsLengthAndAColon(): void
    {
        // A key stored by an earlier version must still match the same thanks: its text is part of the contract.
        $given = $this->kudos()->give(self::member('1'), self::member('2'), 'help');

        self::assertSame('kudos:'.hash('sha256', '8:member:18:member:24:help0:10:2026-11-20'), $given->thanks->key);
    }

    public function testAnActivityWithoutAMessageIsNoThanks(): void
    {
        $this->expectException(InvalidKudos::class);
        $this->expectExceptionMessageIsOrContains('The activity "comment:1" holds no message: it records no thanks.');

        Thanks::fromActivity(Activity::of('comment:1', 'member:1', Kudos::VERB, $this->clock->now(), 'member:2', context: ['message' => 3]));
    }

    public function testTheApplicationMayGiveItsOwnKey(): void
    {
        $kudos = $this->kudos();

        self::assertSame('request:1', $kudos->give(self::member('1'), self::member('2'), 'help', key: 'request:1')->thanks->key);
        self::assertSame('request:2', $kudos->give(self::member('1'), self::member('2'), 'help', key: IdempotencyKey::fromString('request:2'))->thanks->key);
        self::assertSame(2, $kudos->givenCount(self::member('1')));
    }

    public function testThanksGivenAgainUnderTheirKeyAreNeverRefused(): void
    {
        $kudos = $this->kudos(new Limits(1));
        $kudos->give(self::member('1'), self::member('2'), 'help', key: 'request:1');

        $again = $kudos->give(self::member('1'), self::member('2'), 'help', key: 'request:1');

        self::assertFalse($again->new);
        self::assertSame(Refusal::DailyCap, $kudos->refusal(self::member('1'), self::member('3')));
    }

    public function testAKeyGivenToOtherThanksConflicts(): void
    {
        $kudos = $this->kudos();
        $kudos->give(self::member('1'), self::member('2'), 'help', key: 'request:1');

        $this->expectException(IdempotencyConflict::class);

        $kudos->give(self::member('1'), self::member('3'), 'help', key: 'request:1');
    }

    public function testAMemberCannotThankThemselves(): void
    {
        $kudos = $this->kudos(preferences: new Declines('member:1', 'member:1'));

        self::assertSame(Refusal::Self, $kudos->refusal(self::member('1'), self::member('1')));
        $this->expectRefusal(Refusal::Self, static fn (): Given => $kudos->give(self::member('1'), self::member('1'), 'help'));
    }

    public function testAReceiverWhoDeclinesIsNotThanked(): void
    {
        $kudos = $this->kudos(preferences: new Declines('member:2', 'member:1'));

        self::assertSame(Refusal::Declined, $kudos->refusal(self::member('1'), self::member('2')));
        self::assertNull($kudos->refusal(self::member('3'), self::member('2')));
        self::assertTrue($kudos->give(self::member('3'), self::member('2'), 'help')->new);
        $this->expectRefusal(Refusal::Declined, static fn (): Given => $kudos->give(self::member('1'), self::member('2'), 'help'));
        self::assertSame(0, $kudos->givenCount(self::member('1')));
    }

    public function testADeclineComesBeforeTheCaps(): void
    {
        $kudos = $this->kudos(new Limits(1), new Declines('member:3', 'member:1'));
        $kudos->give(self::member('1'), self::member('2'), 'help');

        self::assertSame(Refusal::Declined, $kudos->refusal(self::member('1'), self::member('3')));
    }

    public function testTheDailyCapRefusesTheSixthThanks(): void
    {
        // The defaults: the library's messages, five thanks a day counted in UTC, every member taking thanks.
        $kudos = new Kudos($this->journal, $this->clock, observers: [$this->observed]);

        foreach (['2', '3', '4', '5', '6'] as $receiver) {
            self::assertNull($kudos->refusal(self::member('1'), self::member($receiver)));
            $kudos->give(self::member('1'), self::member($receiver), 'help');
        }

        self::assertSame(0, $kudos->remainingToday(self::member('1')));
        self::assertSame(5, $kudos->remainingToday(self::member('2')));
        self::assertSame(Refusal::DailyCap, $kudos->refusal(self::member('1'), self::member('7')));
        $this->expectRefusal(Refusal::DailyCap, static fn (): Given => $kudos->give(self::member('1'), self::member('7'), 'help'));
    }

    public function testTheDayTurnsAtMidnightInTheMembersTimeZone(): void
    {
        // 23:30 in Toronto, 04:30 the next day in UTC.
        $this->clock = new MockClock('2026-11-21 04:30:00', 'UTC');
        $this->journal = new Journal(new InMemoryActivityLog(), $this->clock);
        $kudos = $this->kudos(new Limits(2), timeZone: 'America/Toronto');
        $kudos->give(self::member('1'), self::member('2'), 'help');
        $kudos->give(self::member('1'), self::member('3'), 'help');

        self::assertSame(Refusal::DailyCap, $kudos->refusal(self::member('1'), self::member('4')));

        $this->clock->modify('+29 minutes 59 seconds');
        self::assertSame(0, $kudos->remainingToday(self::member('1')));

        $this->clock->modify('+1 second');
        self::assertSame(2, $kudos->remainingToday(self::member('1')));
        self::assertTrue($kudos->give(self::member('1'), self::member('4'), 'help')->new);
    }

    public function testTheDayStartsAtMidnightSharp(): void
    {
        $kudos = $this->kudos(new Limits(2));
        $this->clock->modify('2026-11-19 23:59:30');
        $kudos->give(self::member('1'), self::member('2'), 'help');
        $this->clock->modify('2026-11-20 00:00:30');
        $kudos->give(self::member('1'), self::member('3'), 'help');
        $this->clock->modify('2026-11-20 12:00:00');

        self::assertSame(1, $kudos->remainingToday(self::member('1')));
    }

    public function testTheDayOfAnotherTimeZoneCountsOtherThanks(): void
    {
        // 01:00 on the 20th in Toronto, then 23:30 on the 20th in Toronto: one day there, two days in UTC.
        $this->clock = new MockClock('2026-11-20 06:00:00', 'UTC');
        $this->journal = new Journal(new InMemoryActivityLog(), $this->clock);
        $toronto = $this->kudos(new Limits(2), timeZone: 'America/Toronto');
        $utc = $this->kudos(new Limits(2));
        $toronto->give(self::member('1'), self::member('2'), 'help');
        $this->clock->modify('2026-11-21 04:30:00');
        $toronto->give(self::member('1'), self::member('3'), 'help');

        self::assertSame(0, $toronto->remainingToday(self::member('1')));
        self::assertSame(1, $utc->remainingToday(self::member('1')));
    }

    public function testThanksOfALaterDayDoNotCountToday(): void
    {
        $kudos = $this->kudos(new Limits(2));
        $this->clock->modify('+1 day');
        $kudos->give(self::member('1'), self::member('2'), 'help');
        $this->clock->modify('-1 day');

        self::assertSame(2, $kudos->remainingToday(self::member('1')));
    }

    public function testWithdrawnThanksStillCountTowardsTheCaps(): void
    {
        $kudos = $this->kudos(new Limits(3, 2));
        $kudos->give(self::member('1'), self::member('2'), 'help', key: 'a');
        $kudos->withdraw(self::member('1'), 'a');
        $kudos->give(self::member('1'), self::member('2'), 'help', key: 'b');
        $kudos->withdraw(self::member('1'), 'b');

        self::assertSame(Refusal::PairLimit, $kudos->refusal(self::member('1'), self::member('2')));

        $kudos->give(self::member('1'), self::member('3'), 'help', key: 'c');
        $kudos->withdraw(self::member('1'), 'c');

        self::assertSame(0, $kudos->remainingToday(self::member('1')));
        self::assertSame(Refusal::DailyCap, $kudos->refusal(self::member('1'), self::member('4')));
        self::assertSame(0, $kudos->givenCount(self::member('1')));
    }

    public function testThanksRecordedBeyondTheCapLeaveNothingToGive(): void
    {
        $kudos = $this->kudos(new Limits(2));

        foreach (['a', 'b', 'c'] as $key) {
            $this->journal->record(new Activity(IdempotencyKey::fromString($key), self::member('1'), Verb::fromString(Kudos::VERB), $this->clock->now(), self::member('2')));
        }

        self::assertSame(0, $kudos->remainingToday(self::member('1')));
    }

    public function testOtherActivitiesOfTheGiverDoNotCount(): void
    {
        $kudos = $this->kudos(new Limits(1, 1));
        $this->journal->record(Activity::of('comment:1', 'member:1', 'news.commented', $this->clock->now(), 'member:2'));

        self::assertSame(1, $kudos->remainingToday(self::member('1')));
        self::assertNull($kudos->refusal(self::member('1'), self::member('2')));
    }

    public function testTheSameReceiverIsThankedAtMostThreeTimesInSevenDays(): void
    {
        $kudos = $this->kudos(new Limits(10));
        $kudos->give(self::member('1'), self::member('2'), 'help');

        $this->clock->sleep(3_600);
        $kudos->give(self::member('1'), self::member('2'), 'well_played');
        $kudos->give(self::member('1'), self::member('2'), 'great_idea');

        self::assertSame(Refusal::PairLimit, $kudos->refusal(self::member('1'), self::member('2')));
        self::assertNull($kudos->refusal(self::member('1'), self::member('3')));
        self::assertNull($kudos->refusal(self::member('3'), self::member('2')));
        $this->expectRefusal(Refusal::PairLimit, static fn (): Given => $kudos->give(self::member('1'), self::member('2'), 'welcome'));

        // Seven days after the first thanks, it still counts; a second later, it no longer does.
        $this->clock->modify('2026-11-27 12:00:00');
        self::assertSame(Refusal::PairLimit, $kudos->refusal(self::member('1'), self::member('2')));
        $this->clock->sleep(1);
        self::assertNull($kudos->refusal(self::member('1'), self::member('2')));
    }

    public function testAMessageOutsideTheListIsRefused(): void
    {
        $kudos = $this->kudos(messages: Messages::defaults()->without('help'));

        $this->expectException(UnknownMessage::class);

        $kudos->give(self::member('1'), self::member('2'), new Message('help', 'Thanks for your help'));
    }

    public function testTheApplicationsMessagesAreUsed(): void
    {
        $kudos = $this->kudos(messages: new Messages(new Message('nice_map', 'Nice map')));

        self::assertSame('nice_map', $kudos->give(self::member('1'), self::member('2'), 'nice_map')->thanks->message);
        $this->expectException(UnknownMessage::class);
        $kudos->give(self::member('1'), self::member('3'), 'help');
    }

    public function testTheObjectsReferenceIsAtMostOneHundredBytes(): void
    {
        $kudos = $this->kudos();
        $longest = ResourceReference::of('comment', str_repeat('1', 92));

        self::assertSame($longest->toString(), $kudos->give(self::member('1'), self::member('2'), 'help', $longest)->thanks->object?->toString());

        $this->expectException(InvalidKudos::class);
        $this->expectExceptionMessageIsOrContains('The reference of the object thanked for is at most 100 bytes; "comment:'.str_repeat('1', 93).'" is 101.');

        $kudos->give(self::member('1'), self::member('3'), 'help', ResourceReference::of('comment', str_repeat('1', 93)));
    }

    public function testTheGiverWithdrawsTheirThanks(): void
    {
        $kudos = $this->kudos();
        $key = $kudos->give(self::member('1'), self::member('2'), 'help')->thanks->key;

        self::assertFalse($kudos->withdraw(self::member('2'), $key));
        self::assertFalse($this->journal->isCancelled($key));
        self::assertSame(0, $this->cancellations());

        self::assertTrue($kudos->withdraw(self::member('1'), IdempotencyKey::fromString($key)));
        self::assertTrue($this->journal->isCancelled($key));
        self::assertEquals([new KudosCancelled($key, 'member:1', 'member:2', true)], $this->observed->cancelled);

        self::assertTrue($kudos->withdraw(self::member('1'), $key));
        self::assertSame(1, $this->cancellations());
        self::assertSame([], $kudos->received(self::member('2'))->thanks);
        self::assertSame([], $kudos->given(self::member('1'))->thanks);
    }

    public function testOnlyThanksCanBeWithdrawnOrCancelled(): void
    {
        $kudos = $this->kudos();
        $this->journal->record(Activity::of('comment:1', 'member:1', 'news.commented', $this->clock->now(), 'member:2'));

        self::assertFalse($kudos->withdraw(self::member('1'), 'comment:1'));
        self::assertFalse($kudos->cancel('comment:1'));
        self::assertFalse($kudos->withdraw(self::member('1'), 'unknown'));
        self::assertFalse($kudos->cancel('unknown'));
        self::assertFalse($this->journal->isCancelled('comment:1'));
    }

    public function testThanksErasedBeforeTheirCancellationAreGoneAndTellNobody(): void
    {
        $this->journal = new Journal(new ErasedBeforeCancel(), $this->clock);
        $kudos = $this->kudos();
        $key = $kudos->give(self::member('1'), self::member('2'), 'help')->thanks->key;

        self::assertTrue($kudos->withdraw(self::member('1'), $key));
        self::assertTrue($kudos->cancel($key));
        self::assertSame(0, $this->cancellations());
    }

    public function testAModeratorCancelsThanks(): void
    {
        $kudos = $this->kudos();
        $key = $kudos->give(self::member('1'), self::member('2'), 'help')->thanks->key;

        self::assertTrue($kudos->cancel($key));
        self::assertTrue($kudos->cancel(IdempotencyKey::fromString($key)));
        self::assertEquals([new KudosCancelled($key, 'member:1', 'member:2', false)], $this->observed->cancelled);
        self::assertSame(0, $kudos->givenCount(self::member('1')));
    }

    public function testTheReceiverReadsWhatTheyReceivedPageByPage(): void
    {
        $kudos = $this->kudos(new Limits(10, 10));
        $first = $kudos->give(self::member('1'), self::member('2'), 'help')->thanks->key;
        $kudos->give(self::member('1'), self::member('3'), 'help');
        $withdrawn = $kudos->give(self::member('4'), self::member('2'), 'help')->thanks->key;
        $kudos->withdraw(self::member('4'), $withdrawn);
        $third = $kudos->give(self::member('5'), self::member('2'), 'good_game', ResourceReference::of('game', '9'))->thanks->key;

        $all = $kudos->received(self::member('2'));
        self::assertSame([$first, $third], $this->keys($all));
        self::assertNull($all->next);
        self::assertSame('game:9', $all->thanks[1]->object?->toString());

        $page = $kudos->received(self::member('2'), limit: 2);
        self::assertSame([$first], $this->keys($page));
        self::assertSame($this->journal->find($withdrawn)?->position, $page->next);

        $next = $kudos->received(self::member('2'), (int) $page->next, 2);
        self::assertSame([$third], $this->keys($next));
        self::assertNotNull($next->next);
        self::assertSame([], $kudos->received(self::member('2'), $next->next, 2)->thanks);
        self::assertNull($kudos->received(self::member('2'), $next->next, 2)->next);
    }

    public function testTheGiverReadsWhatTheyGave(): void
    {
        $kudos = $this->kudos();
        $first = $kudos->give(self::member('1'), self::member('2'), 'help')->thanks->key;
        $kudos->give(self::member('3'), self::member('1'), 'help');
        $second = $kudos->give(self::member('1'), self::member('3'), 'welcome')->thanks->key;
        $this->journal->record(Activity::of('comment:1', 'member:1', 'news.commented', $this->clock->now(), 'member:2'));

        self::assertSame([$first, $second], $this->keys($kudos->given(self::member('1'))));
        self::assertSame([$first], $this->keys($kudos->given(self::member('1'), limit: 1)));
        self::assertSame([$second], $this->keys($kudos->given(self::member('1'), 1)));
    }

    public function testAPageHoldsFiftyThanksByDefault(): void
    {
        $kudos = $this->kudos(new Limits(100));

        foreach (range(100, 150) as $id) {
            $kudos->give(self::member('1'), self::member((string) $id), 'help');
            $kudos->give(self::member((string) $id), self::member('2'), 'help');
        }

        $given = $kudos->given(self::member('1'));
        $received = $kudos->received(self::member('2'));

        self::assertCount(50, $given->thanks);
        self::assertNotNull($given->next);
        self::assertCount(50, $received->thanks);
        self::assertNotNull($received->next);
    }

    public function testAPageHoldsOneToOneHundredThanks(): void
    {
        $kudos = $this->kudos();

        self::assertSame([], $kudos->given(self::member('1'), limit: 100)->thanks);
        self::assertSame([], $kudos->received(self::member('1'), limit: 1)->thanks);

        foreach ([[0, 0, 'A page holds 1 to 100 thanks; 0 is not a limit.'], [0, 101, 'A page holds 1 to 100 thanks; 101 is not a limit.'], [-1, 50, 'A page starts after a position of 0 or more; -1 is not one.']] as [$after, $limit, $error]) {
            try {
                $kudos->received(self::member('1'), $after, $limit);
                self::fail('The page should be refused.');
            } catch (InvalidKudos $invalidKudos) {
                self::assertSame($error, $invalidKudos->getMessage());
            }

            try {
                $kudos->given(self::member('1'), $after, $limit);
                self::fail('The page should be refused.');
            } catch (InvalidKudos $invalidKudos) {
                self::assertSame($error, $invalidKudos->getMessage());
            }
        }
    }

    public function testNothingReadsWhatAnotherMemberReceived(): void
    {
        $methods = array_map(static fn (ReflectionMethod $method): string => $method->getName(), new ReflectionClass(Kudos::class)->getMethods(ReflectionMethod::IS_PUBLIC));
        sort($methods);

        self::assertSame(['__construct', 'cancel', 'give', 'given', 'givenCount', 'received', 'refusal', 'remainingToday', 'withdraw'], $methods);

        $kudos = $this->kudos();
        $kudos->give(self::member('1'), self::member('2'), 'help');
        $kudos->give(self::member('3'), self::member('2'), 'help');

        self::assertSame(0, $kudos->givenCount(self::member('2')));
        self::assertSame([], $kudos->given(self::member('2'))->thanks);
    }

    public function testTheThanksGivenAreTheMeasureOfABadge(): void
    {
        $kudos = $this->kudos();
        $kudos->give(self::member('1'), self::member('2'), 'help');

        $this->clock->modify('+1 day');
        $kudos->give(self::member('1'), self::member('2'), 'help');
        $this->clock->modify('+1 day');
        $kudos->give(self::member('1'), self::member('3'), 'help');

        self::assertSame(3, $kudos->givenCount(self::member('1')));
        self::assertSame(2, $kudos->givenCount(self::member('1'), new DateTimeImmutable('2026-11-21 12:00:00 UTC')));
        self::assertSame(1, $kudos->givenCount(self::member('1'), until: new DateTimeImmutable('2026-11-21 12:00:00 UTC')));
        self::assertSame(1, $kudos->givenCount(self::member('1'), new DateTimeImmutable('2026-11-21 12:00:00 UTC'), new DateTimeImmutable('2026-11-22 12:00:00 UTC')));
        self::assertSame(3, $this->journal->totals(Filter::all()->member(self::member('1'))->verbs(Kudos::VERB))->count);
    }

    public function testErasingAMemberThroughTheJournalErasesTheirThanks(): void
    {
        $kudos = $this->kudos();
        $kudos->give(self::member('1'), self::member('2'), 'help');
        $kudos->give(self::member('3'), self::member('1'), 'help');

        $this->journal->erase(self::member('2'));
        $given = $kudos->given(self::member('1'))->thanks;
        self::assertCount(1, $given);
        self::assertNull($given[0]->receiver);
        self::assertSame([], $kudos->received(self::member('2'))->thanks);

        $this->journal->erase(self::member('3'));
        self::assertSame([], $kudos->received(self::member('1'))->thanks);
    }

    public function testThanksWithdrawnAfterTheReceiverIsErasedTellNoReceiver(): void
    {
        $kudos = $this->kudos();
        $key = $kudos->give(self::member('1'), self::member('2'), 'help')->thanks->key;
        $this->journal->erase(self::member('2'));

        self::assertTrue($kudos->withdraw(self::member('1'), $key));
        self::assertNull($this->observed->cancelled[0]->receiver);
    }

    private function cancellations(): int
    {
        return \count($this->observed->cancelled);
    }

    private function kudos(Limits $limits = new Limits(), KudosPreferences $preferences = new AcceptAll(), ?Messages $messages = null, string $timeZone = 'UTC'): Kudos
    {
        return new Kudos($this->journal, $this->clock, $messages, $limits, $preferences, new SiteTimeZone(new DateTimeZone($timeZone)), [$this->observed]);
    }

    /**
     * @param callable(): mixed $give
     */
    private function expectRefusal(Refusal $refusal, callable $give): void
    {
        $told = \count($this->observed->given);

        try {
            $give();
            self::fail('The thanks should be refused.');
        } catch (KudosRefused $kudosRefused) {
            self::assertSame($refusal, $kudosRefused->refusal);
        }

        self::assertCount($told, $this->observed->given);
    }

    /**
     * @return list<string>
     */
    private function keys(ThanksPage $page): array
    {
        return array_map(static fn (Thanks $thanks): string => $thanks->key, $page->thanks);
    }

    private static function member(string $id): ResourceReference
    {
        return ResourceReference::of('member', $id);
    }
}
