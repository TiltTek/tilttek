<?php

declare(strict_types=1);

namespace Tilttek\Quests\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Quests\Admission;
use Tilttek\Quests\Completion;
use Tilttek\Quests\Contribution;
use Tilttek\Quests\Duel\Duel;
use Tilttek\Quests\Duel\DuelStatus;
use Tilttek\Quests\Enrolment;
use Tilttek\Quests\Exception\InvalidQuest;
use Tilttek\Quests\Names;
use Tilttek\Quests\Quest;
use Tilttek\Quests\QuestCompleted;
use Tilttek\Quests\QuestKind;
use Tilttek\Quests\Reward;
use Tilttek\Quests\Schedule\Dated;
use Tilttek\Quests\Schedule\Occurrence;
use Tilttek\Quests\Schedule\Permanent;
use Tilttek\Quests\Scope;
use Tilttek\Quests\Step;
use Tilttek\Quests\StepMeasure;
use Tilttek\Quests\Window;

#[CoversClass(Names::class)]
#[CoversClass(Scope::class)]
#[CoversClass(Step::class)]
#[CoversClass(Reward::class)]
#[CoversClass(Quest::class)]
#[CoversClass(Window::class)]
#[CoversClass(Occurrence::class)]
#[CoversClass(Permanent::class)]
#[CoversClass(Dated::class)]
#[CoversClass(Enrolment::class)]
#[CoversClass(Completion::class)]
#[CoversClass(Contribution::class)]
#[CoversClass(Duel::class)]
#[CoversClass(QuestCompleted::class)]
#[CoversClass(InvalidQuest::class)]
final class ValuesTest extends TestCase
{
    public function testAStepReadsItsVerbsAndContext(): void
    {
        $step = new Step('read', new Scope(['book.*', 'book.*', 'news.read'], ['system' => 'dnd5e', 'ranked' => true]), 300, StepMeasure::Total);

        self::assertSame(['book.*', 'news.read'], $step->scope->patterns);
        self::assertTrue($step->accepts($this->activity('book.read', 40, ['system' => 'dnd5e', 'ranked' => true, 'extra' => 1])));
        self::assertFalse($step->accepts($this->activity('book.read', 40, ['system' => 'pf2e', 'ranked' => true])));
        self::assertFalse($step->accepts($this->activity('book.read', 40, ['system' => 'dnd5e'])));
        self::assertFalse($step->accepts($this->activity('game.played', 40, ['system' => 'dnd5e', 'ranked' => true])));
        self::assertSame(40, $step->valueOf($this->activity('book.read', 40)));
        self::assertSame(1, new Step('read', new Scope(['book.*']))->valueOf($this->activity('book.read', 40)));
        self::assertSame(1, new Step('read', new Scope(['book.*']))->target);
        self::assertSame(1_000_000_000_000, new Step('s', new Scope(['a.b']), Step::MAX_TARGET)->target);
    }

    public function testAQuestKnowsItsStepsAndPatterns(): void
    {
        $quest = new Quest('q', [new Step('a', new Scope(['game.played', 'game.*'])), new Step('b', new Scope(['game.played', 'book.read']))]);

        self::assertSame(['game.played', 'game.*', 'book.read'], $quest->patterns());
        self::assertSame(['a', 'b'], array_map(static fn (Step $step): string => $step->code, $quest->stepsAccepting($this->activity('game.played'))));
        self::assertSame(['b'], array_map(static fn (Step $step): string => $step->code, $quest->stepsAccepting($this->activity('book.read'))));
        self::assertSame(['a'], array_map(static fn (Step $step): string => $step->code, $quest->stepsAccepting($this->activity('game.won'))));
        self::assertFalse($quest->needsAcceptance());
        self::assertFalse($quest->ordered);
        self::assertSame(Admission::Automatic, new Quest('q', [self::step()], QuestKind::Community)->admission);
        self::assertSame(Admission::OnAcceptance, new Quest('q', [self::step()], QuestKind::Team)->admission);
        self::assertTrue(new Quest('q', [self::step()], QuestKind::Duel)->needsAcceptance());
        self::assertTrue(new Quest('q', [self::step()], admission: Admission::OnAcceptance)->needsAcceptance());
        self::assertSame(Admission::OnAcceptance, new Quest('q', [self::step()], QuestKind::Duel, admission: Admission::OnAcceptance)->admission);
        self::assertCount(50, new Quest('q', array_map(static fn (int $i): Step => new Step('s'.$i, new Scope(['a.b'])), range(1, 50)))->steps);
        self::assertCount(20, new Quest('q', [self::step()], rewards: array_fill(0, 20, new Reward('points', 'xp')))->rewards);
        self::assertSame(2, new Quest('q', [self::step()], QuestKind::Duel, duelLength: 'PT2H')->duelLength?->h);
    }

    /**
     * @return iterable<string, array{callable(): mixed, string}>
     */
    public static function refusals(): iterable
    {
        yield 'a bad code' => [static fn (): Quest => new Quest('Q', [self::step()]), '"Q" is not a quest code: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.'];
        yield 'a code too long' => [static fn (): string => Names::check('a'.str_repeat('b', 64), 'step'), 'is not a step code'];
        yield 'a code with a newline' => [static fn (): string => Names::check("a\n", 'step'), 'is not a step code'];
        yield 'no steps' => [static fn (): Quest => new Quest('q', []), 'A quest has 1 to 50 steps; "q" has 0.'];
        yield 'too many steps' => [static fn (): Quest => new Quest('q', array_map(static fn (int $i): Step => new Step('s'.$i, new Scope(['a.b'])), range(1, 51))), 'A quest has 1 to 50 steps; "q" has 51.'];
        yield 'two steps with a code' => [static fn (): Quest => new Quest('q', [self::step(), self::step()]), 'The steps of "q" each have their own code.'];
        yield 'too many rewards' => [static fn (): Quest => new Quest('q', [self::step()], rewards: array_fill(0, 21, new Reward('points', 'xp'))), 'A quest declares at most 20 rewards; "q" declares 21.'];
        yield 'following itself' => [static fn (): Quest => new Quest('q', [self::step()], after: 'q'), '"q" cannot follow "q": a quest follows another, and a duel none.'];
        yield 'a duel following' => [static fn (): Quest => new Quest('q', [self::step()], QuestKind::Duel, after: 'p'), '"q" cannot follow "p": a quest follows another, and a duel none.'];
        yield 'following a bad code' => [static fn (): Quest => new Quest('q', [self::step()], after: 'P'), '"P" is not a quest code'];
        yield 'a team followed automatically' => [static fn (): Quest => new Quest('q', [self::step()], QuestKind::Team, admission: Admission::Automatic), 'A team quest is joined by acceptance; "q" cannot be followed automatically.'];
        yield 'a duel followed automatically' => [static fn (): Quest => new Quest('q', [self::step()], QuestKind::Duel, admission: Admission::Automatic), 'A duel quest is joined by acceptance; "q" cannot be followed automatically.'];
        yield 'a length for another quest' => [static fn (): Quest => new Quest('q', [self::step()], QuestKind::Team, duelLength: 'P1D'), 'Only a duel lasts for a length; "q" is a team quest.'];
        yield 'a length that is not one' => [static fn (): Quest => new Quest('q', [self::step()], QuestKind::Duel, duelLength: 'soon'), '"soon" is not an ISO 8601 duration like "P7D".'];
        yield 'a length of no time' => [static fn (): Quest => new Quest('q', [self::step()], QuestKind::Duel, duelLength: 'P0D'), 'A duel lasts some time; "P0D" is no time.'];
        yield 'a step with no verb' => [static fn (): Scope => new Scope([]), 'A step reads at least one verb.'];
        yield 'a step with a bad verb' => [static fn (): Scope => new Scope(['Game']), '"Game"'];
        yield 'a step with a bad context' => [static fn (): Scope => new Scope(['a.b'], ['Bad' => 1]), '"Bad"'];
        yield 'a step with a bad code' => [static fn (): Step => new Step('Read', new Scope(['a.b'])), '"Read" is not a step code'];
        yield 'a target of zero' => [static fn (): Step => new Step('s', new Scope(['a.b']), 0), 'A step asks for 1 to 1000000000000; "s" asks for 0.'];
        yield 'a target too high' => [static fn (): Step => new Step('s', new Scope(['a.b']), Step::MAX_TARGET + 1), 'A step asks for 1 to 1000000000000; "s" asks for 1000000000001.'];
        yield 'a reward with a bad kind' => [static fn (): Reward => new Reward('Points', 'xp'), '"Points" is not a reward kind code'];
        yield 'a reward naming nothing' => [static fn (): Reward => new Reward('points', ''), 'What a reward names is 1 to 255 bytes without whitespace or control characters; "" is not.'];
        yield 'a reward naming too much' => [static fn (): Reward => new Reward('points', str_repeat('x', 256)), 'What a reward names is 1 to 255 bytes'];
        yield 'a reward naming with a space' => [static fn (): Reward => new Reward('points', 'x y'), 'What a reward names is 1 to 255 bytes'];
        yield 'a reward of zero' => [static fn (): Reward => new Reward('points', 'xp', 0), 'A reward gives 1 to 1000000000; this one gives 0.'];
        yield 'a reward too high' => [static fn (): Reward => new Reward('points', 'xp', Reward::MAX_AMOUNT + 1), 'A reward gives 1 to 1000000000; this one gives 1000000001.'];
        yield 'a dated quest without dates' => [static fn (): Dated => new Dated(null, null), 'A dated quest opens or closes at a date; one that does neither is permanent.'];
        yield 'a dated quest closing when it opens' => [static fn (): Dated => new Dated(self::at('10:00'), self::at('10:00')), 'A dated quest closes after it opens.'];
        yield 'a duel without a key' => [static fn (): Duel => self::duel(''), 'A duel key is 1 to 200 bytes without whitespace or control characters; "" is not.'];
        yield 'a duel with a long key' => [static fn (): Duel => self::duel(str_repeat('k', 201)), 'A duel key is 1 to 200 bytes'];
        yield 'a duel with a space' => [static fn (): Duel => self::duel('k k'), 'A duel key is 1 to 200 bytes'];
        yield 'a duel with oneself' => [static fn (): Duel => new Duel('d', 'q', self::ref('member:1'), self::ref('member:1'), self::at('10:00')), 'A member cannot challenge themselves.'];
    }

    #[DataProvider('refusals')]
    public function testAWrongValueIsRefused(callable $make, string $message): void
    {
        $this->expectException(InvalidQuest::class);
        $this->expectExceptionMessageIsOrContains($message);

        $make();
    }

    public function testTheLimitsAreAccepted(): void
    {
        self::assertSame(str_repeat('k', 200), self::duel(str_repeat('k', 200))->key);
        self::assertSame(str_repeat('x', 255), new Reward('points', str_repeat('x', 255))->code);
        self::assertSame(Reward::MAX_AMOUNT, new Reward('points', 'xp', Reward::MAX_AMOUNT)->amount);
        self::assertSame('a'.str_repeat('b', 63), Names::check('a'.str_repeat('b', 63), 'step'));
        self::assertSame(['kind' => 'badge', 'code' => 'explorer', 'amount' => 1], new Reward('badge', 'explorer')->toArray());
    }

    public function testAWindowHoldsItsStartAndNotItsEnd(): void
    {
        $window = new Window(self::at('10:00'), self::at('12:00'));

        self::assertFalse($window->contains(self::at('09:59')));
        self::assertTrue($window->contains(self::at('10:00')));
        self::assertTrue($window->contains(self::at('11:59')));
        self::assertFalse($window->contains(self::at('12:00')));
        self::assertTrue(new Window()->contains(self::at('12:00')));
        self::assertTrue(new Window(null, self::at('12:00'))->contains(self::at('01:00')));
        self::assertTrue(new Window(self::at('10:00'))->contains(self::at('23:00')));

        self::assertEquals(new Window(self::at('11:00'), self::at('12:00')), $window->intersect(new Window(self::at('11:00'), self::at('13:00'))));
        self::assertEquals(new Window(self::at('10:00'), self::at('11:00')), new Window(self::at('09:00'), self::at('11:00'))->intersect($window));
        self::assertEquals($window, $window->intersect(new Window()));
        self::assertEquals($window, new Window()->intersect($window));
        self::assertEquals(new Window(self::at('13:00'), self::at('12:00')), $window->intersect(new Window(self::at('13:00'))));
        self::assertEquals(new Window(null, self::at('11:00')), new Window(null, self::at('12:00'))->intersect(new Window(null, self::at('11:00'))));
        self::assertEquals(new Window(self::at('11:00')), new Window(self::at('11:00'))->intersect(new Window(self::at('10:00'))));
        self::assertEquals(new Window(), new Window()->intersect(new Window()));
        self::assertFalse(new Window(self::at('13:00'), self::at('12:00'))->contains(self::at('12:30')));
    }

    public function testASchedulesSaysTheOccurrenceOfAMoment(): void
    {
        self::assertEquals(new Occurrence(), new Permanent()->occurrenceAt(self::at('10:00')));
        self::assertEquals(new Window(), new Occurrence()->window());
        $dated = new Dated(self::at('10:00'), self::at('12:00'));
        self::assertEquals(new Occurrence('', self::at('10:00'), self::at('12:00')), $dated->occurrenceAt(self::at('10:00')));
        self::assertNull($dated->occurrenceAt(self::at('12:00')));
        self::assertNull($dated->occurrenceAt(self::at('09:00')));
        self::assertNotNull(new Dated(null, self::at('12:00'))->occurrenceAt(self::at('01:00')));
        self::assertNotNull(new Dated(self::at('10:00'), null)->occurrenceAt(self::at('23:00')));
    }

    public function testAnEnrolmentCountsFromItsAcceptanceUntilItsAbandonment(): void
    {
        $member = self::ref('member:1');
        $enrolment = new Enrolment('q', $member, self::at('10:00'), self::ref('team:red'));
        $abandoned = $enrolment->abandon(self::at('12:00'));
        $accepted = new Quest('q', [self::step()], admission: Admission::OnAcceptance);
        $automatic = new Quest('q', [self::step()]);

        self::assertFalse($enrolment->isAbandoned());
        self::assertTrue($enrolment->isUnder(self::ref('team:red')));
        self::assertFalse($enrolment->isUnder(self::ref('team:blue')));
        self::assertFalse(new Enrolment('q', $member, self::at('10:00'))->isUnder(self::ref('team:red')));
        self::assertTrue($abandoned->isAbandoned());
        self::assertEquals(new Enrolment('q', $member, self::at('10:00'), self::ref('team:red'), self::at('12:00')), $abandoned);
        self::assertEquals(new Window(self::at('10:00'), self::at('12:00')), $abandoned->window($accepted));
        self::assertEquals(new Window(null, self::at('12:00')), $abandoned->window($automatic));
        self::assertSame(['quest' => 'q', 'member' => 'member:1', 'accepted_at' => '2026-11-01T10:00:00+00:00', 'team' => 'team:red', 'abandoned_at' => '2026-11-01T12:00:00+00:00'], $abandoned->toArray());
        self::assertSame(['quest' => 'q', 'member' => 'member:1', 'accepted_at' => '2026-11-01T10:00:00+00:00', 'team' => null, 'abandoned_at' => null], new Enrolment('q', $member, self::at('10:00'))->toArray());
    }

    public function testTheRecordsAreWrittenAsPlainValues(): void
    {
        self::assertSame(
            ['quest' => 'q', 'step' => 's', 'member' => 'member:1', 'activity' => 'k1', 'value' => 3, 'occurred_at' => '2026-11-01T10:00:00+00:00'],
            new Contribution('q', 's', self::ref('member:1'), 'k1', 3, self::at('10:00'))->toArray(),
        );
        self::assertSame(
            ['quest' => 'q', 'occurrence' => '2026-12-01', 'subject' => 'community:q', 'completed_at' => '2026-11-01T10:00:00+00:00', 'activity' => 'k1'],
            new Completion('q', '2026-12-01', Completion::community('q'), self::at('10:00'), 'k1')->toArray(),
        );
        $quest = new Quest('q', [self::step()], rewards: [new Reward('points', 'xp')]);
        self::assertEquals([new Reward('points', 'xp')], new QuestCompleted($quest, new Completion('q', '', self::ref('member:1'), self::at('10:00'), 'k1'), [self::ref('member:1')])->rewards());
    }

    public function testADuelGoesThroughItsStates(): void
    {
        $duel = self::duel('d1');

        self::assertSame(DuelStatus::Offered, $duel->status);
        self::assertTrue($duel->involves(self::ref('member:1')));
        self::assertTrue($duel->involves(self::ref('member:2')));
        self::assertFalse($duel->involves(self::ref('member:3')));
        self::assertNull($duel->window());
        self::assertFalse($duel->covers(self::at('10:00')));

        $accepted = $duel->accepted(self::at('11:00'), self::at('13:00'));
        self::assertEquals(new Window(self::at('11:00'), self::at('13:00')), $accepted->window());
        self::assertSame(DuelStatus::Accepted, $accepted->status);
        self::assertFalse($accepted->covers(self::at('10:59')));
        self::assertTrue($accepted->covers(self::at('11:00')));
        self::assertFalse($accepted->covers(self::at('13:00')));
        $won = $accepted->with(DuelStatus::Won, self::at('12:00'), self::ref('member:2'));
        self::assertEquals(new Duel('d1', 'q', self::ref('member:1'), self::ref('member:2'), self::at('10:00'), DuelStatus::Won, self::at('11:00'), self::at('13:00'), self::ref('member:2'), self::at('12:00')), $won);
        self::assertEquals($accepted, $won->reopened());
        self::assertSame([
            'key' => 'd1', 'quest' => 'q', 'challenger' => 'member:1', 'opponent' => 'member:2', 'offered_at' => '2026-11-01T10:00:00+00:00',
            'status' => 'won', 'accepted_at' => '2026-11-01T11:00:00+00:00', 'ends_at' => '2026-11-01T13:00:00+00:00', 'winner' => 'member:2', 'decided_at' => '2026-11-01T12:00:00+00:00',
        ], $won->toArray());
        self::assertSame([
            'key' => 'd1', 'quest' => 'q', 'challenger' => 'member:1', 'opponent' => 'member:2', 'offered_at' => '2026-11-01T10:00:00+00:00',
            'status' => 'offered', 'accepted_at' => null, 'ends_at' => null, 'winner' => null, 'decided_at' => null,
        ], $duel->toArray());
    }

    private static function step(): Step
    {
        return new Step('s', new Scope(['a.b']));
    }

    private static function duel(string $key): Duel
    {
        return new Duel($key, 'q', self::ref('member:1'), self::ref('member:2'), self::at('10:00'));
    }

    private static function ref(string $reference): ResourceReference
    {
        return ResourceReference::fromString($reference);
    }

    private static function at(string $time): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-01 '.$time.' UTC');
    }

    /**
     * @param array<string, bool|int|string> $context
     */
    private function activity(string $verb, int $quantity = 1, array $context = []): Activity
    {
        return Activity::of('k', 'member:1', $verb, self::at('10:00'), null, $quantity, $context);
    }
}
