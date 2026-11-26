<?php

declare(strict_types=1);

namespace Tilttek\Conditions\Tests\Unit;

use DateTimeImmutable;
use DateTimeZone;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\Conditions\ActiveCondition;
use Tilttek\Conditions\Anchor;
use Tilttek\Conditions\Boundary;
use Tilttek\Conditions\CharacterConditions;
use Tilttek\Conditions\ConditionCatalogue;
use Tilttek\Conditions\ConditionDefinition;
use Tilttek\Conditions\Conditions;
use Tilttek\Conditions\Decay;
use Tilttek\Conditions\Duration;
use Tilttek\Conditions\DurationKind;
use Tilttek\Conditions\Effect\AppliedModifier;
use Tilttek\Conditions\Effect\EffectCalculator;
use Tilttek\Conditions\EndReason;
use Tilttek\Conditions\Event\ConditionApplied;
use Tilttek\Conditions\Event\ConditionChanged;
use Tilttek\Conditions\Event\ConditionEnded;
use Tilttek\Conditions\Exception\ConditionNotFound;
use Tilttek\Conditions\Exception\ConditionRefused;
use Tilttek\Conditions\Exception\InvalidCondition;
use Tilttek\Conditions\Modifier;
use Tilttek\Conditions\Rounding;
use Tilttek\Conditions\Stacking;
use Tilttek\Conditions\Store\InMemoryConditionStore;
use Tilttek\Conditions\Tests\Unit\Fixture\Observed;
use Tilttek\Conditions\View\ConditionState;
use Tilttek\Conditions\View\ConditionsView;

#[CoversClass(Conditions::class)]
#[CoversClass(ConditionsView::class)]
#[CoversClass(ConditionState::class)]
#[CoversClass(ConditionApplied::class)]
#[CoversClass(ConditionChanged::class)]
#[CoversClass(ConditionEnded::class)]
final class ConditionsTest extends TestCase
{
    private const int SECOND = 1_000;

    private MockClock $now;

    private Observed $observed;

    private InMemoryConditionStore $store;

    private Conditions $conditions;

    protected function setUp(): void
    {
        $this->store = new InMemoryConditionStore();
        $this->now = new MockClock(new DateTimeImmutable('2027-01-01 12:00:00', new DateTimeZone('UTC')));
        $this->observed = new Observed();
        $this->conditions = new Conditions($this->store, $this->catalogue(), $this->now, $this->observed);
    }

    public function testAppliedConditionGetsAnInstanceAndIsTold(): void
    {
        $kept = $this->conditions->apply('ada', 'prone');

        self::assertSame(1, $kept->version);
        self::assertSame(1, $kept->issued);
        self::assertCount(1, $kept->active);
        $one = $kept->active[0];
        self::assertSame(['i1', 'prone', null, null], [$one->id, $one->condition, $one->value, $one->source]);
        self::assertSame(DurationKind::UntilRemoved, $one->duration->kind);
        self::assertSame('2027-01-01T12:00:00+00:00', $one->appliedAt->format(\DATE_ATOM));
        self::assertNull($one->expiresAt);
        self::assertEquals([new ConditionApplied('ada', 'i1', 'prone')], $this->observed->take());
        self::assertSame($kept, $this->conditions->conditions('ada'));
    }

    public function testTheDurationIsTheOneGivenOrTheConditionsOwn(): void
    {
        $this->conditions->apply('ada', 'blessed');
        $kept = $this->conditions->apply('ada', 'prone', duration: Duration::rounds(2), source: 'bo');

        self::assertSame(['kind' => 'rounds', 'amount' => 10, 'boundary' => 'end', 'anchor' => 'target'], $kept->active[0]->duration->toArray());
        self::assertSame(['kind' => 'rounds', 'amount' => 2, 'boundary' => 'end', 'anchor' => 'target'], $kept->active[1]->duration->toArray());
        self::assertSame('bo', $kept->active[1]->source);
        self::assertSame('i2', $kept->active[1]->id);
    }

    public function testACharacterWithoutConditionsHasNoneAtVersionZero(): void
    {
        $none = $this->conditions->conditions('ada');

        self::assertSame(['ada', [], 0], [$none->character, $none->active, $none->version]);
        self::assertNull($this->store->conditions('ada'));

        $this->expectException(InvalidCondition::class);
        $this->conditions->conditions('not one');
    }

    public function testTheValueIsGivenWhenAndOnlyWhenTheConditionHasOne(): void
    {
        self::assertSame(1, $this->conditions->apply('ada', 'frightened', 1)->active[0]->value);
        self::assertSame(4, $this->conditions->apply('bo', 'frightened', 4)->active[0]->value);

        foreach ([[null, 'The condition frightened has a value of 1 to 4.'], [0, 'The condition frightened has a value of 1 to 4.'], [5, 'The condition frightened has a value of 1 to 4.']] as [$value, $message]) {
            try {
                $this->conditions->apply('cy', 'frightened', $value);
                self::fail('The value was accepted.');
            } catch (InvalidCondition $invalid) {
                self::assertSame($message, $invalid->getMessage());
            }
        }

        $this->expectException(InvalidCondition::class);
        $this->expectExceptionMessageIsOrContains('The condition prone has no value.');
        $this->conditions->apply('cy', 'prone', 1);
    }

    public function testAConditionOutsideTheCatalogueIsNotFound(): void
    {
        $this->expectException(ConditionNotFound::class);
        $this->conditions->apply('ada', 'petrified');
    }

    public function testAConditionThatIsNotAnIdentifierIsRefused(): void
    {
        $this->expectException(InvalidCondition::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a condition');
        $this->conditions->apply('ada', 'pro ne');
    }

    public function testASourceThatIsNotAnIdentifierIsRefused(): void
    {
        $this->expectException(InvalidCondition::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a source');
        $this->conditions->apply('ada', 'prone', source: 'bo@example');
    }

    public function testADurationOnTheSourcesTurnsNeedsTheSource(): void
    {
        self::assertSame('bo', $this->conditions->apply('ada', 'marked', source: 'bo')->active[0]->source);
        self::assertSame('i1', $this->conditions->apply('cy', 'prone', duration: Duration::gameTime(6))->active[0]->id);

        $this->expectException(InvalidCondition::class);
        $this->expectExceptionMessageIsOrContains('A duration counted on the turns of the source needs the source.');
        $this->conditions->apply('ada', 'prone', duration: Duration::rounds(1, Boundary::End, Anchor::Source));
    }

    public function testReplaceEndsWhatIsInPlace(): void
    {
        $this->conditions->apply('ada', 'prone');
        $this->observed->take();
        $kept = $this->conditions->apply('ada', 'prone', duration: Duration::rounds(1));

        self::assertSame(['i2'], array_map(static fn (ActiveCondition $one): string => $one->id, $kept->active));
        self::assertSame(2, $kept->issued);
        self::assertEquals([new ConditionEnded('ada', 'i1', 'prone', EndReason::Replaced), new ConditionApplied('ada', 'i2', 'prone')], $this->observed->take());
    }

    public function testHighestKeepsTheHigherValue(): void
    {
        $this->conditions->apply('ada', 'frightened', 2);
        $this->observed->take();
        $this->now->sleep(5);

        $lower = $this->conditions->apply('ada', 'frightened', 1, Duration::rounds(3), 'bo');
        self::assertSame(1, $lower->version);
        self::assertSame([], $this->observed->take());

        $same = $this->conditions->apply('ada', 'frightened', 2, Duration::rounds(3), 'bo');
        self::assertSame(2, $same->version);
        self::assertSame(['i1', 2, 'bo', 3], [$same->active[0]->id, $same->active[0]->value, $same->active[0]->source, $same->active[0]->duration->amount]);
        self::assertSame('2027-01-01T12:00:05+00:00', $same->active[0]->appliedAt->format(\DATE_ATOM));
        self::assertEquals([new ConditionChanged('ada', 'i1', 'frightened')], $this->observed->take());

        $higher = $this->conditions->apply('ada', 'frightened', 3);
        self::assertSame([3, 1], [$higher->active[0]->value, $higher->issued]);
        self::assertCount(1, $higher->active);
    }

    public function testHighestRefreshesAConditionWithoutValue(): void
    {
        $catalogue = new ConditionCatalogue([new ConditionDefinition('hasted', Stacking::Highest)]);
        $conditions = new Conditions($this->store, $catalogue, $this->now, $this->observed);
        $conditions->apply('ada', 'hasted', duration: Duration::rounds(1));

        $kept = $conditions->apply('ada', 'hasted', duration: Duration::rounds(5));

        self::assertSame([5, 2], [$kept->active[0]->duration->amount, $kept->version]);
    }

    public function testSumAddsUpToTheHighestValue(): void
    {
        $this->conditions->apply('ada', 'bleeding', 4, Duration::rounds(2));
        $this->observed->take();
        $this->now->sleep(5);

        $kept = $this->conditions->apply('ada', 'bleeding', 3, Duration::rounds(4), 'bo');
        self::assertSame([7, 4, 'bo'], [$kept->active[0]->value, $kept->active[0]->duration->amount, $kept->active[0]->source]);
        self::assertSame('2027-01-01T12:00:00+00:00', $kept->active[0]->appliedAt->format(\DATE_ATOM));
        self::assertEquals([new ConditionChanged('ada', 'i1', 'bleeding')], $this->observed->take());

        self::assertSame(10, $this->conditions->apply('ada', 'bleeding', 9)->active[0]->value);
    }

    public function testSeparateKeepsEachApplicationUpToItsMost(): void
    {
        $this->conditions->apply('ada', 'poisoned');
        $this->conditions->apply('ada', 'poisoned');

        $kept = $this->conditions->apply('ada', 'poisoned');

        self::assertSame(['i1', 'i2', 'i3'], array_map(static fn (ActiveCondition $one): string => $one->id, $kept->active));
        self::assertSame(3600, $kept->active[2]->duration->amount);

        $this->expectException(ConditionRefused::class);
        $this->expectExceptionMessageIsOrContains('The character ada has the most instances of poisoned.');
        $this->conditions->apply('ada', 'poisoned');
    }

    public function testIgnoreKeepsWhatIsInPlace(): void
    {
        $first = $this->conditions->apply('ada', 'immune');
        $this->observed->take();

        self::assertSame($first, $this->conditions->apply('ada', 'immune', duration: Duration::rounds(1)));
        self::assertSame([], $this->observed->take());
    }

    public function testACharacterHasItsMostConditions(): void
    {
        $definitions = array_map(static fn (int $i): ConditionDefinition => new ConditionDefinition('c'.$i), range(1, 65));
        $conditions = new Conditions($this->store, new ConditionCatalogue($definitions), $this->now);
        for ($i = 1; $i <= 64; ++$i) {
            $conditions->apply('ada', 'c'.$i);
        }

        self::assertCount(64, $conditions->conditions('ada')->active);
        self::assertCount(64, $conditions->apply('ada', 'c64')->active);

        $this->expectException(ConditionRefused::class);
        $this->expectExceptionMessageIsOrContains('The character ada has the most conditions.');
        $conditions->apply('ada', 'c65');
    }

    public function testAConditionInRealTimeEndsAtItsMoment(): void
    {
        $this->conditions->apply('ada', 'slowed');
        $this->now->sleep(30);
        $this->conditions->apply('ada', 'slowed', duration: Duration::realTime(10 * self::SECOND));
        $this->observed->take();

        $kept = $this->conditions->conditions('ada');
        self::assertSame('2027-01-01T12:00:40.000+00:00', $kept->active[0]->expiresAt?->format(\DATE_RFC3339_EXTENDED));
        self::assertSame('2027-01-01T12:00:40.000+00:00', $this->conditions->view('ada')->nextExpiry?->format(\DATE_RFC3339_EXTENDED));

        $this->now->sleep(9.999);
        self::assertSame($kept, $this->conditions->expire('ada'));
        self::assertTrue($this->conditions->has('ada', 'slowed'));

        $this->now->sleep(0.001);
        self::assertFalse($this->conditions->has('ada', 'slowed'));
        self::assertSame([], $this->conditions->view('ada')->conditions);
        self::assertNull($this->conditions->view('ada')->nextExpiry);
        self::assertSame([], $this->conditions->modifiers('ada'));
        self::assertSame($kept, $this->conditions->conditions('ada'));

        $ended = $this->conditions->expire('ada');
        self::assertSame([], $ended->active);
        self::assertEquals([new ConditionEnded('ada', 'i2', 'slowed', EndReason::Expired)], $this->observed->take());
        self::assertSame($ended, $this->conditions->expire('ada'));
        self::assertSame([], $this->observed->take());
    }

    public function testTheNextExpiryIsTheEarliest(): void
    {
        $this->conditions->apply('ada', 'slowed', duration: Duration::realTime(50 * self::SECOND));
        $this->conditions->apply('ada', 'prone', duration: Duration::realTime(20 * self::SECOND));
        $this->conditions->apply('ada', 'blinded', duration: Duration::realTime(30 * self::SECOND));

        self::assertSame('2027-01-01T12:00:20.000+00:00', $this->conditions->view('ada')->nextExpiry?->format(\DATE_RFC3339_EXTENDED));
    }

    public function testApplyingEndsWhatRanOutFirst(): void
    {
        $this->conditions->apply('ada', 'slowed');
        $this->conditions->apply('ada', 'immune');

        $this->observed->take();
        $this->now->sleep(60);

        $kept = $this->conditions->apply('ada', 'prone');
        self::assertSame(['immune', 'prone'], $kept->conditionIds());
        self::assertEquals([new ConditionEnded('ada', 'i1', 'slowed', EndReason::Expired), new ConditionApplied('ada', 'i3', 'prone')], $this->observed->take());

        $this->conditions->apply('ada', 'slowed');
        $this->now->sleep(60);
        $ignored = $this->conditions->apply('ada', 'immune');
        self::assertSame(['immune', 'prone'], $ignored->conditionIds());
        self::assertSame(5, $ignored->version);
    }

    public function testRemoveEndsOneApplication(): void
    {
        $this->conditions->apply('ada', 'poisoned');
        $this->conditions->apply('ada', 'poisoned');

        $this->observed->take();

        $kept = $this->conditions->remove('ada', 'i1');
        self::assertSame(['i2'], array_map(static fn (ActiveCondition $one): string => $one->id, $kept->active));
        self::assertSame(2, $kept->issued);
        self::assertEquals([new ConditionEnded('ada', 'i1', 'poisoned', EndReason::Removed)], $this->observed->take());

        $this->expectException(ConditionNotFound::class);
        $this->expectExceptionMessageIsOrContains('The character ada has no condition instance i1.');
        $this->conditions->remove('ada', 'i1');
    }

    public function testRemoveConditionEndsEveryApplication(): void
    {
        $this->conditions->apply('ada', 'poisoned');
        $this->conditions->apply('ada', 'prone');
        $this->conditions->apply('ada', 'poisoned');

        $this->observed->take();

        $kept = $this->conditions->removeCondition('ada', 'poisoned');
        self::assertSame(['prone'], $kept->conditionIds());
        self::assertSame(4, $kept->version);
        self::assertEquals([new ConditionEnded('ada', 'i1', 'poisoned', EndReason::Removed), new ConditionEnded('ada', 'i3', 'poisoned', EndReason::Removed)], $this->observed->take());

        self::assertSame($kept, $this->conditions->removeCondition('ada', 'poisoned'));
        self::assertSame([], $this->observed->take());

        $this->expectException(InvalidCondition::class);
        $this->conditions->removeCondition('ada', '');
    }

    public function testSetValueChangesOrRemoves(): void
    {
        $this->conditions->apply('ada', 'frightened', 2);
        $this->observed->take();

        $changed = $this->conditions->setValue('ada', 'i1', 4);
        self::assertSame(4, $changed->active[0]->value);
        self::assertEquals([new ConditionChanged('ada', 'i1', 'frightened')], $this->observed->take());

        self::assertSame($changed, $this->conditions->setValue('ada', 'i1', 4));
        self::assertSame([], $this->observed->take());

        self::assertSame(1, $this->conditions->setValue('ada', 'i1', 1)->active[0]->value);
        $this->observed->take();

        foreach ([-1, 5] as $value) {
            try {
                $this->conditions->setValue('ada', 'i1', $value);
                self::fail('The value was accepted.');
            } catch (InvalidCondition $invalid) {
                self::assertSame('The condition frightened has a value of 1 to 4, or 0 to remove it.', $invalid->getMessage());
            }
        }

        self::assertSame([], $this->conditions->setValue('ada', 'i1', 0)->active);
        self::assertEquals([new ConditionEnded('ada', 'i1', 'frightened', EndReason::Removed)], $this->observed->take());
    }

    public function testSetValueNeedsAConditionWithAValue(): void
    {
        $this->conditions->apply('ada', 'prone');

        $this->expectException(ConditionRefused::class);
        $this->expectExceptionMessageIsOrContains('The condition prone has no value.');
        $this->conditions->setValue('ada', 'i1', 1);
    }

    public function testSetValueNeedsTheInstance(): void
    {
        $this->expectException(ConditionNotFound::class);
        $this->conditions->setValue('ada', 'i1', 1);
    }

    public function testRoundsCountAtTheEndOfTheTargetsTurns(): void
    {
        $this->conditions->apply('ada', 'prone', duration: Duration::rounds(2));
        $this->observed->take();

        $this->conditions->startTurn('t1', 'ada', ['ada']);
        $this->conditions->endTurn('t2', 'bo', ['ada', 'bo']);
        self::assertSame(2, $this->conditions->conditions('ada')->active[0]->duration->amount);
        self::assertSame(1, $this->conditions->conditions('ada')->version);

        [$after] = $this->conditions->endTurn('t3', 'ada', ['ada']);
        self::assertSame([1, 't3', 2], [$after->active[0]->duration->amount, $after->lastTick, $after->version]);
        self::assertSame([], $this->observed->take());

        [$ended] = $this->conditions->endTurn('t4', 'ada', ['ada']);
        self::assertSame([], $ended->active);
        self::assertEquals([new ConditionEnded('ada', 'i1', 'prone', EndReason::Expired)], $this->observed->take());
    }

    public function testRoundsCountAtTheStartOfTheTargetsTurns(): void
    {
        $this->conditions->apply('ada', 'prone', duration: Duration::rounds(1, Boundary::Start));

        $this->conditions->endTurn('t1', 'ada', ['ada']);
        self::assertCount(1, $this->conditions->conditions('ada')->active);
        self::assertSame([], $this->conditions->startTurn('t2', 'ada', ['ada'])[0]->active);
    }

    public function testRoundsCountOnTheSourcesTurns(): void
    {
        $this->conditions->apply('ada', 'marked', source: 'bo');

        $this->conditions->endTurn('t1', 'ada', ['ada', 'bo']);
        self::assertCount(1, $this->conditions->conditions('ada')->active);

        [$ada, $bo] = $this->conditions->endTurn('t2', 'bo', ['ada', 'bo']);
        self::assertSame([], $ada->active);
        self::assertSame('bo', $bo->character);
    }

    public function testRoundsCountOnTheRounds(): void
    {
        $this->conditions->apply('ada', 'prone', duration: Duration::rounds(2, Boundary::Start, Anchor::Round));

        $this->conditions->startTurn('t1', 'ada', ['ada']);
        $this->conditions->endRound('r1', ['ada']);
        self::assertSame(2, $this->conditions->conditions('ada')->active[0]->duration->amount);
        self::assertSame(1, $this->conditions->startRound('r2', ['ada'])[0]->active[0]->duration->amount);

        $this->conditions->apply('bo', 'prone', duration: Duration::rounds(1, Boundary::End, Anchor::Round));
        self::assertSame([], $this->conditions->endRound('r3', ['bo'])[0]->active);
    }

    public function testAValueDecaysAtTheBoundaryOfTheTargetsOwnTurn(): void
    {
        $this->conditions->apply('ada', 'frightened', 2);
        $this->observed->take();

        $this->conditions->startTurn('t1', 'ada', ['ada']);
        $this->conditions->endTurn('t2', 'bo', ['ada']);
        self::assertSame(2, $this->conditions->conditions('ada')->active[0]->value);
        self::assertSame([], $this->observed->take());

        self::assertSame(1, $this->conditions->endTurn('t3', 'ada', ['ada'])[0]->active[0]->value);
        self::assertEquals([new ConditionChanged('ada', 'i1', 'frightened')], $this->observed->take());

        self::assertSame([], $this->conditions->endTurn('t4', 'ada', ['ada'])[0]->active);
        self::assertEquals([new ConditionEnded('ada', 'i1', 'frightened', EndReason::Decayed)], $this->observed->take());
    }

    public function testADecayAndADurationCountInOneTick(): void
    {
        $this->conditions->apply('ada', 'frightened', 4, Duration::rounds(2));
        $this->observed->take();

        $one = $this->conditions->endTurn('t1', 'ada', ['ada'])[0]->active[0];
        self::assertSame([3, 1], [$one->value, $one->duration->amount]);
        self::assertEquals([new ConditionChanged('ada', 'i1', 'frightened')], $this->observed->take());

        self::assertSame([], $this->conditions->endTurn('t2', 'ada', ['ada'])[0]->active);
        self::assertEquals([new ConditionChanged('ada', 'i1', 'frightened'), new ConditionEnded('ada', 'i1', 'frightened', EndReason::Expired)], $this->observed->take());
    }

    public function testATickToldTwiceCountsOnce(): void
    {
        $this->conditions->apply('ada', 'prone', duration: Duration::rounds(3));

        $first = $this->conditions->endTurn('t1', 'ada', ['ada']);
        self::assertSame($first, $this->conditions->endTurn('t1', 'ada', ['ada']));
        self::assertSame(2, $this->conditions->conditions('ada')->active[0]->duration->amount);
        self::assertSame(1, $this->conditions->endTurn('t2', 'ada', ['ada'])[0]->active[0]->duration->amount);
    }

    public function testParticipantsWithoutChangesAreNotWritten(): void
    {
        $this->conditions->apply('ada', 'prone');
        $results = $this->conditions->endTurn('t1', 'ada', ['ada', 'bo', 'ada']);

        self::assertCount(2, $results);
        self::assertSame([1, null], [$results[0]->version, $results[0]->lastTick]);
        self::assertSame([0, 'bo'], [$results[1]->version, $results[1]->character]);
        self::assertNull($this->store->conditions('bo'));
    }

    public function testATickEndsWhatRanOutInRealTime(): void
    {
        $this->conditions->apply('ada', 'slowed');
        $this->observed->take();
        $this->now->sleep(60);

        [$kept] = $this->conditions->startRound('r1', ['ada']);
        self::assertSame([[], 'r1'], [$kept->active, $kept->lastTick]);
        self::assertEquals([new ConditionEnded('ada', 'i1', 'slowed', EndReason::Expired)], $this->observed->take());
    }

    public function testParticipantsHaveTheirBounds(): void
    {
        $participants = array_map(static fn (int $i): string => 'p'.$i, range(1, Conditions::MAX_PARTICIPANTS));
        self::assertCount(200, $this->conditions->startRound('r1', $participants));

        $participants[] = 'p201';
        $this->expectException(InvalidCondition::class);
        $this->expectExceptionMessageIsOrContains('A turn counts for 200 characters at most.');
        $this->conditions->startRound('r1', $participants);
    }

    public function testAParticipantIsAnIdentifier(): void
    {
        $this->expectException(InvalidCondition::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a character');
        $this->conditions->endRound('r1', ['ada', 'b o']);
    }

    public function testATickIsAnIdentifier(): void
    {
        $this->expectException(InvalidCondition::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a tick');
        $this->conditions->endRound('', ['ada']);
    }

    public function testTheCharacterWhoseTurnItIsIsAnIdentifier(): void
    {
        $this->expectException(InvalidCondition::class);
        $this->conditions->startTurn('t1', 'a b', ['ada']);
    }

    public function testTheEndOfATurnNamesAnIdentifier(): void
    {
        $this->expectException(InvalidCondition::class);
        $this->conditions->endTurn('t1', 'a b', ['ada']);
    }

    public function testGameTimeRunsDownWhenTheTableAdvancesIt(): void
    {
        $this->conditions->apply('ada', 'poisoned');
        $this->conditions->apply('ada', 'prone', duration: Duration::rounds(1));

        $this->observed->take();

        [$after] = $this->conditions->advanceTime('h1', 3599, ['ada']);
        self::assertSame([1, 1], [$after->active[0]->duration->amount, $after->active[1]->duration->amount]);

        [$ended] = $this->conditions->advanceTime('h2', 1, ['ada']);
        self::assertSame(['prone'], $ended->conditionIds());
        self::assertEquals([new ConditionEnded('ada', 'i1', 'poisoned', EndReason::Expired)], $this->observed->take());

        $this->conditions->apply('bo', 'poisoned');
        self::assertSame([], $this->conditions->advanceTime('h3', Duration::MAX_GAME_SECONDS, ['bo'])[0]->active);
    }

    public function testGameTimeAdvancesWithinItsBounds(): void
    {
        foreach ([0, Duration::MAX_GAME_SECONDS + 1] as $seconds) {
            try {
                $this->conditions->advanceTime('h1', $seconds, ['ada']);
                self::fail('The advance was accepted.');
            } catch (InvalidCondition $invalid) {
                self::assertSame('The time advances by 1 to 3162240000 seconds.', $invalid->getMessage());
            }
        }
    }

    public function testTheViewShowsApplicationsThenWhatTheyBring(): void
    {
        $this->conditions->apply('ada', 'dying', 2, Duration::rounds(3), 'bo');
        $this->conditions->apply('ada', 'prone');
        $this->conditions->apply('ada', 'grabbed');

        $view = $this->conditions->view('ada');

        self::assertSame(['dying', 'prone', 'grabbed', 'unconscious', 'blinded', 'off-guard', 'immobilized'], array_map(static fn (ConditionState $state): string => $state->condition, $view->conditions));
        self::assertSame(['unconscious' => ['dying'], 'blinded' => ['dying'], 'off-guard' => ['dying', 'grabbed'], 'immobilized' => ['grabbed']], array_combine(
            array_map(static fn (ConditionState $state): string => $state->condition, \array_slice($view->conditions, 3)),
            array_map(static fn (ConditionState $state): array => $state->impliedBy, \array_slice($view->conditions, 3)),
        ));
        self::assertTrue($view->has('off-guard'));
        self::assertTrue($view->has('dying'));
        self::assertFalse($view->has('poisoned'));
        self::assertSame(3, $view->version);
        self::assertSame([
            'character' => 'ada',
            'conditions' => [
                ['condition' => 'dying', 'instance' => 'i1', 'value' => 2, 'source' => 'bo', 'remaining' => ['kind' => 'rounds', 'amount' => 3, 'boundary' => 'end', 'anchor' => 'target'], 'expiresAt' => null, 'impliedBy' => []],
                ['condition' => 'prone', 'instance' => 'i2', 'value' => null, 'source' => null, 'remaining' => ['kind' => 'until-removed', 'amount' => 0, 'boundary' => 'end', 'anchor' => 'target'], 'expiresAt' => null, 'impliedBy' => []],
                ['condition' => 'grabbed', 'instance' => 'i3', 'value' => null, 'source' => null, 'remaining' => ['kind' => 'until-removed', 'amount' => 0, 'boundary' => 'end', 'anchor' => 'target'], 'expiresAt' => null, 'impliedBy' => []],
                ['condition' => 'unconscious', 'instance' => null, 'value' => null, 'source' => null, 'remaining' => null, 'expiresAt' => null, 'impliedBy' => ['dying']],
                ['condition' => 'blinded', 'instance' => null, 'value' => null, 'source' => null, 'remaining' => null, 'expiresAt' => null, 'impliedBy' => ['dying']],
                ['condition' => 'off-guard', 'instance' => null, 'value' => null, 'source' => null, 'remaining' => null, 'expiresAt' => null, 'impliedBy' => ['dying', 'grabbed']],
                ['condition' => 'immobilized', 'instance' => null, 'value' => null, 'source' => null, 'remaining' => null, 'expiresAt' => null, 'impliedBy' => ['grabbed']],
            ],
            'nextExpiry' => null,
            'version' => 3,
        ], $view->toArray());

        $this->conditions->apply('bo', 'slowed');
        self::assertSame('2027-01-01T12:01:00.000+00:00', $this->conditions->view('bo')->toArray()['nextExpiry']);
        self::assertSame('2027-01-01T12:01:00.000+00:00', $this->conditions->view('bo')->toArray()['conditions'][0]['expiresAt']);
    }

    public function testHasSeesWhatIsBrought(): void
    {
        $this->conditions->apply('ada', 'dying', 1);

        self::assertTrue($this->conditions->has('ada', 'dying'));
        self::assertTrue($this->conditions->has('ada', 'blinded'));
        self::assertFalse($this->conditions->has('ada', 'immobilized'));
        self::assertFalse($this->conditions->has('bo', 'dying'));
    }

    public function testModifiersCountValuesAndWhatIsBroughtOnce(): void
    {
        $this->conditions->apply('ada', 'frightened', 3);
        $this->conditions->apply('ada', 'dying', 1);
        $this->conditions->apply('ada', 'prone');
        $this->conditions->apply('ada', 'grabbed');

        $modifiers = $this->conditions->modifiers('ada');
        self::assertSame(
            [['frightened', 'i1', 'checks', -3], ['prone', 'i3', 'ac', -2], ['blinded', null, 'perception', -4], ['off-guard', null, 'ac', -2]],
            array_map(static fn (AppliedModifier $one): array => [$one->condition, $one->instance, $one->modifier->stat, $one->amount], $modifiers),
        );

        $values = $this->conditions->effective('ada', ['ac' => 18, 'checks' => 5, 'perception' => 3]);
        self::assertSame([14, 2, -1], [$values['ac']->value, $values['checks']->value, $values['perception']->value]);
    }

    public function testTheCalculatorOfTheServiceRounds(): void
    {
        $conditions = new Conditions($this->store, $this->catalogue(), $this->now, null, new EffectCalculator(Rounding::Up));
        $conditions->apply('ada', 'slowed');

        self::assertSame(16, $conditions->effective('ada', ['speed' => 31])['speed']->value);
        self::assertSame(15, $this->conditions->effective('ada', ['speed' => 31])['speed']->value);
    }

    public function testAConditionTheCatalogueLacksStaysWithoutEffect(): void
    {
        $at = new DateTimeImmutable('2027-01-01T11:00:00Z');
        $this->store->save(new CharacterConditions('ada', [new ActiveCondition('i1', 'gone', 2, null, Duration::rounds(2), $at)], 1));

        self::assertSame([], $this->conditions->modifiers('ada'));
        self::assertTrue($this->conditions->has('ada', 'gone'));
        self::assertSame([2, 1], [$this->conditions->endTurn('t1', 'ada', ['ada'])[0]->active[0]->value, $this->conditions->conditions('ada')->active[0]->duration->amount]);

        try {
            $this->conditions->setValue('ada', 'i1', 1);
            self::fail('The value was set.');
        } catch (ConditionRefused $conditionRefused) {
            self::assertSame('The condition gone has no value.', $conditionRefused->getMessage());
        }

        self::assertSame([], $this->conditions->removeCondition('ada', 'gone')->active);
    }

    public function testTheServiceWorksWithoutObserver(): void
    {
        $conditions = new Conditions($this->store, $this->catalogue(), $this->now);
        $conditions->apply('ada', 'prone', duration: Duration::rounds(1));

        self::assertSame([], $conditions->endTurn('t1', 'ada', ['ada'])[0]->active);
    }

    public function testTheApplicationInPlaceIsFoundWhateverComesBeforeIt(): void
    {
        $this->conditions->apply('ada', 'prone');
        $this->conditions->apply('ada', 'bleeding', 3);

        $kept = $this->conditions->apply('ada', 'bleeding', 2);

        self::assertCount(2, $kept->active);
        self::assertSame(['bleeding', 5], [$kept->active[1]->condition, $kept->active[1]->value]);
    }

    public function testReplaceKeepsTheOtherConditionsInAList(): void
    {
        $this->conditions->apply('ada', 'prone');
        $this->conditions->apply('ada', 'blessed');

        $kept = $this->conditions->apply('ada', 'prone');

        self::assertSame([0, 1], array_keys($kept->active));
        self::assertSame(['i2', 'i3'], array_map(static fn (ActiveCondition $one): string => $one->id, $kept->active));
    }

    public function testHighestWithoutValueDoesNotReplaceAKeptValue(): void
    {
        $catalogue = new ConditionCatalogue([new ConditionDefinition('hasted', Stacking::Highest)]);
        $at = $this->now->now();
        $kept = $this->store->save(new CharacterConditions('ada', [new ActiveCondition('i1', 'hasted', 1, null, Duration::untilRemoved(), $at)], 1));
        $conditions = new Conditions($this->store, $catalogue, $this->now, $this->observed);

        self::assertSame($kept, $conditions->apply('ada', 'hasted'));
        self::assertSame([], $this->observed->take());
    }

    public function testASourceIsCheckedEvenWhenTheRuleKeepsWhatIsInPlace(): void
    {
        $this->conditions->apply('ada', 'immune');

        $this->expectException(InvalidCondition::class);
        $this->expectExceptionMessageIsOrContains('The identifier of a source');
        $this->conditions->apply('ada', 'immune', source: 'bo@example');
    }

    public function testEveryParticipantIsCheckedBeforeAnyIsCounted(): void
    {
        $this->conditions->apply('ada', 'prone', duration: Duration::rounds(1, Boundary::End, Anchor::Round));

        try {
            $this->conditions->endRound('r1', ['ada', 'b o']);
            self::fail('The participant was accepted.');
        } catch (InvalidCondition) {
            self::assertCount(1, $this->conditions->conditions('ada')->active);
            self::assertSame(1, $this->conditions->conditions('ada')->version);
        }
    }

    public function testATickAlreadyCountedForOneParticipantStillCountsForTheNext(): void
    {
        $this->conditions->apply('ada', 'prone', duration: Duration::rounds(2, Boundary::End, Anchor::Round));
        $this->conditions->apply('bo', 'prone', duration: Duration::rounds(2, Boundary::End, Anchor::Round));
        $this->conditions->endRound('r1', ['ada']);

        $results = $this->conditions->endRound('r1', ['ada', 'bo']);

        self::assertCount(2, $results);
        self::assertSame(1, $results[0]->active[0]->duration->amount);
        self::assertSame(1, $results[1]->active[0]->duration->amount);
    }

    public function testWhatAConditionBringsIsToldOnceWhateverTheApplications(): void
    {
        $catalogue = new ConditionCatalogue([
            new ConditionDefinition('poisoned', Stacking::Separate, implies: ['sick'], maxInstances: 2),
            new ConditionDefinition('sick'),
        ]);
        $conditions = new Conditions($this->store, $catalogue, $this->now);
        $conditions->apply('ada', 'poisoned');
        $conditions->apply('ada', 'poisoned');

        $states = $conditions->view('ada')->conditions;

        self::assertCount(3, $states);
        self::assertSame(['poisoned'], $states[2]->impliedBy);
    }

    private function catalogue(): ConditionCatalogue
    {
        return new ConditionCatalogue([
            new ConditionDefinition('prone', modifiers: [Modifier::add('ac', -2)]),
            new ConditionDefinition('frightened', Stacking::Highest, 4, [Modifier::add('checks', -1, true, 'status')], decay: new Decay()),
            new ConditionDefinition('poisoned', Stacking::Separate, modifiers: [Modifier::add('str', -1)], duration: Duration::gameTime(3600), maxInstances: 3),
            new ConditionDefinition('bleeding', Stacking::Sum, 10),
            new ConditionDefinition('blessed', Stacking::Ignore, modifiers: [Modifier::add('attack', 1, type: 'status')], duration: Duration::rounds(10)),
            new ConditionDefinition('immune', Stacking::Ignore),
            new ConditionDefinition('slowed', modifiers: [Modifier::multiply('speed', 50)], duration: Duration::realTime(60_000)),
            new ConditionDefinition('marked', duration: Duration::rounds(1, Boundary::End, Anchor::Source)),
            new ConditionDefinition('dying', maxValue: 4, implies: ['unconscious']),
            new ConditionDefinition('unconscious', implies: ['blinded', 'prone', 'off-guard']),
            new ConditionDefinition('grabbed', implies: ['off-guard', 'immobilized']),
            new ConditionDefinition('blinded', modifiers: [Modifier::add('perception', -4)]),
            new ConditionDefinition('off-guard', modifiers: [Modifier::add('ac', -2, type: 'circumstance')]),
            new ConditionDefinition('immobilized'),
        ]);
    }
}
