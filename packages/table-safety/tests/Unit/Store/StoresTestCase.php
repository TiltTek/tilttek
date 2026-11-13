<?php

declare(strict_types=1);

namespace Tilttek\TableSafety\Tests\Unit\Store;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\TableSafety\Boundary;
use Tilttek\TableSafety\ConsentChecklist;
use Tilttek\TableSafety\ConsentLevel;
use Tilttek\TableSafety\Contract\FeedbackStore;
use Tilttek\TableSafety\Contract\ParticipationStore;
use Tilttek\TableSafety\Contract\SignalStore;
use Tilttek\TableSafety\Contract\TableStore;
use Tilttek\TableSafety\Feedback\Feedback;
use Tilttek\TableSafety\Feedback\StarsAndWishes;
use Tilttek\TableSafety\LinesAndVeils;
use Tilttek\TableSafety\Participation;
use Tilttek\TableSafety\SafetyTool;
use Tilttek\TableSafety\SessionZero\Answers;
use Tilttek\TableSafety\SessionZero\Templates;
use Tilttek\TableSafety\Signal\Audience;
use Tilttek\TableSafety\Signal\Signal;
use Tilttek\TableSafety\Signal\SignalKind;
use Tilttek\TableSafety\TableSettings;

/**
 * What every implementation of the four stores does, run against each.
 */
abstract class StoresTestCase extends TestCase
{
    abstract protected function tables(): TableStore;

    abstract protected function participations(): ParticipationStore;

    abstract protected function signals(): SignalStore;

    abstract protected function feedback(): FeedbackStore;

    public function testATableKeepsItsSettings(): void
    {
        $store = $this->tables();
        $table = ResourceReference::of('table', '7');
        $settings = new TableSettings([SafetyTool::XCard], true, Audience::GameMaster, ['clowns' => 'Clowns'], Templates::get('online'));

        self::assertNull($store->settings($table));

        $store->save($table, TableSettings::defaults());
        $store->save($table, $settings);
        $store->save(ResourceReference::of('table', '8'), TableSettings::defaults());

        self::assertEquals($settings, $store->settings($table));
        self::assertEquals(TableSettings::defaults(), $store->settings(ResourceReference::of('table', '8')));

        $store->remove($table);
        $store->remove(ResourceReference::of('table', '9'));

        self::assertNull($store->settings($table));
        self::assertNotNull($store->settings(ResourceReference::of('table', '8')));
    }

    public function testAParticipationIsKeptReplacedAndRemoved(): void
    {
        $store = $this->participations();
        $first = $this->participation('7', 'ana', LinesAndVeils::none()->with('gore', Boundary::Veil));
        $second = $this->participation('7', 'ana', LinesAndVeils::none()->with('gore', Boundary::Line), ConsentChecklist::none()->with('war', ConsentLevel::Red), new Answers(['tone' => 'dark', 'pillars' => ['combat']]));

        self::assertNull($store->find($first->table, $first->participant));

        $store->save($first);
        $store->save($second);

        self::assertEquals($second, $store->find($first->table, $first->participant));
        self::assertNull($store->find(ResourceReference::of('table', '8'), $first->participant));
        self::assertNull($store->find($first->table, ResourceReference::of('member', 'bo')));

        $store->remove($first->table, ResourceReference::of('member', 'bo'));
        self::assertNotNull($store->find($first->table, $first->participant));

        $store->remove($first->table, $first->participant);
        self::assertNull($store->find($first->table, $first->participant));
    }

    public function testATableListsItsParticipations(): void
    {
        $store = $this->participations();
        $store->save($this->participation('7', 'cy'));
        $store->save($this->participation('7', 'ana'));
        $store->save($this->participation('8', 'bo'));

        $participants = array_map(static fn (Participation $participation): string => $participation->participant->toString(), $store->ofTable(ResourceReference::of('table', '7')));

        self::assertSame(['member:ana', 'member:cy'], $participants);
        self::assertSame([], $store->ofTable(ResourceReference::of('table', '9')));
    }

    public function testAParticipantIsFoundAndErasedAtEveryTable(): void
    {
        $store = $this->participations();
        $store->save($this->participation('7', 'ana'));
        $store->save($this->participation('8', 'ana'));
        $store->save($this->participation('8', 'bo'));

        $ana = ResourceReference::of('member', 'ana');

        $tables = array_map(static fn (Participation $participation): string => $participation->table->toString(), $store->ofParticipant($ana));
        sort($tables);

        self::assertSame(['table:7', 'table:8'], $tables);
        self::assertSame(2, $store->eraseParticipant($ana));
        self::assertSame([], $store->ofParticipant($ana));
        self::assertSame(0, $store->eraseParticipant($ana));
        self::assertCount(1, $store->ofTable(ResourceReference::of('table', '8')));
    }

    public function testSignalsAreKeptInTheOrderTheyWereRaised(): void
    {
        $store = $this->signals();
        $table = ResourceReference::of('table', '7');
        $late = $this->signal('7', SignalKind::Pause, '20:05');
        $early = $this->signal('7', SignalKind::XCard, '20:00', 'ana');
        $store->add($late);
        $store->add($early);
        $store->add($this->signal('8', SignalKind::XCard, '20:01'));

        self::assertEquals([$early, $late], $store->recent($table));
        self::assertEquals([$late], $store->recent($table, new DateTimeImmutable('2026-11-12 20:00:00 UTC')));
        self::assertEquals([$late], $store->recent($table, null, 1));
        self::assertSame([], $store->recent($table, new DateTimeImmutable('2026-11-12 20:05:00 UTC')));
        self::assertSame([], $store->recent(ResourceReference::of('table', '9')));
    }

    public function testASignalIsAcknowledgedOnce(): void
    {
        $store = $this->signals();
        $table = ResourceReference::of('table', '7');
        $signal = $this->signal('7', SignalKind::XCard, '20:00');
        $store->add($signal);
        $at = new DateTimeImmutable('2026-11-12 20:01:00 UTC');

        self::assertNull($store->acknowledge(ResourceReference::of('table', '8'), $signal->id, $at));
        self::assertNull($store->acknowledge($table, Signal::newId(), $at));

        $seen = $store->acknowledge($table, $signal->id, $at);

        self::assertEquals($signal->acknowledge($at), $seen);
        self::assertEquals([$signal->acknowledge($at)], $store->recent($table));
        self::assertNull($store->acknowledge($table, $signal->id, new DateTimeImmutable('2026-11-12 20:02:00 UTC')));
        self::assertEquals([$signal->acknowledge($at)], $store->recent($table));
    }

    public function testAParticipantsNameIsTakenOffTheirSignals(): void
    {
        $store = $this->signals();
        $ana = ResourceReference::of('member', 'ana');
        $named = $this->signal('7', SignalKind::XCard, '20:00', 'ana');
        $other = $this->signal('8', SignalKind::OpenDoor, '20:01', 'ana');
        $bo = $this->signal('7', SignalKind::Pause, '20:02', 'bo');
        $anonymous = $this->signal('7', SignalKind::Rewind, '20:03');

        foreach ([$named, $other, $bo, $anonymous] as $signal) {
            $store->add($signal);
        }

        $ids = array_map(static fn (Signal $signal): string => $signal->id, $store->ofParticipant($ana));
        sort($ids);
        $expected = [$named->id, $other->id];
        sort($expected);

        self::assertSame($expected, $ids);
        self::assertSame(2, $store->forgetParticipant($ana));
        self::assertSame([], $store->ofParticipant($ana));
        self::assertSame(0, $store->forgetParticipant($ana));
        self::assertEquals([$named->anonymous(), $bo, $anonymous], $store->recent(ResourceReference::of('table', '7')));
        self::assertEquals([$other->anonymous()], $store->recent(ResourceReference::of('table', '8')));
    }

    public function testOldSignalsArePurged(): void
    {
        $store = $this->signals();
        $table = ResourceReference::of('table', '7');
        $old = $this->signal('7', SignalKind::XCard, '19:00');
        $edge = $this->signal('7', SignalKind::Pause, '20:00');
        $other = $this->signal('8', SignalKind::Pause, '19:00');

        foreach ([$old, $edge, $other] as $signal) {
            $store->add($signal);
        }

        self::assertSame(2, $store->purge(new DateTimeImmutable('2026-11-12 20:00:00 UTC')));
        self::assertEquals([$edge], $store->recent($table));
        self::assertSame([], $store->recent(ResourceReference::of('table', '8')));
        self::assertSame(0, $store->purge(new DateTimeImmutable('2026-11-12 20:00:00 UTC')));
    }

    public function testFeedbackIsKeptBySession(): void
    {
        $store = $this->feedback();
        $table = ResourceReference::of('table', '7');
        $first = $this->feedbackOf('7', 's1', 'cy', ['a']);
        $replaced = $this->feedbackOf('7', 's1', 'cy', ['b'], ['c']);
        $ana = $this->feedbackOf('7', 's1', 'ana', ['d']);
        $store->save($first);
        $store->save($replaced);
        $store->save($ana);
        $store->save($this->feedbackOf('7', 's2', 'ana', ['e']));
        $store->save($this->feedbackOf('8', 's1', 'ana', ['f']));

        self::assertEquals([$ana, $replaced], $store->ofSession($table, 's1'));
        self::assertSame([], $store->ofSession($table, 's3'));
    }

    public function testAParticipantsFeedbackIsFoundAndErased(): void
    {
        $store = $this->feedback();
        $ana = ResourceReference::of('member', 'ana');
        $store->save($this->feedbackOf('7', 's1', 'ana', ['a']));
        $store->save($this->feedbackOf('7', 's2', 'ana', ['b']));
        $store->save($this->feedbackOf('8', 's1', 'ana', ['c']));
        $store->save($this->feedbackOf('7', 's1', 'bo', ['d']));

        $stars = array_map(static fn (Feedback $feedback): string => $feedback->starsAndWishes->stars[0], $store->ofParticipant($ana));
        sort($stars);

        self::assertSame(['a', 'b', 'c'], $stars);
        self::assertSame(3, $store->eraseParticipant($ana));
        self::assertSame([], $store->ofParticipant($ana));
        self::assertSame(0, $store->eraseParticipant($ana));
        self::assertCount(1, $store->ofSession(ResourceReference::of('table', '7'), 's1'));
    }

    protected function participation(string $table, string $participant, ?LinesAndVeils $linesAndVeils = null, ?ConsentChecklist $consent = null, ?Answers $answers = null): Participation
    {
        return new Participation(
            ResourceReference::of('table', $table),
            ResourceReference::of('member', $participant),
            $linesAndVeils ?? LinesAndVeils::none()->with('gore', Boundary::Line),
            $consent ?? ConsentChecklist::none(),
            $answers ?? Answers::none(),
            new DateTimeImmutable('2026-11-12 18:00:00 UTC'),
        );
    }

    protected function signal(string $table, SignalKind $kind, string $at, ?string $by = null): Signal
    {
        return new Signal(Signal::newId(), ResourceReference::of('table', $table), $kind, Audience::Table, new DateTimeImmutable('2026-11-12 '.$at.':00 UTC'), null === $by ? null : ResourceReference::of('member', $by));
    }

    /**
     * @param list<string> $stars
     * @param list<string> $wishes
     */
    protected function feedbackOf(string $table, string $session, string $participant, array $stars, array $wishes = []): Feedback
    {
        return new Feedback(ResourceReference::of('table', $table), $session, ResourceReference::of('member', $participant), new StarsAndWishes($stars, $wishes), new DateTimeImmutable('2026-11-12 23:00:00 UTC'));
    }
}
