<?php

declare(strict_types=1);

namespace Tilttek\Activity\Tests\Unit;

use DateInterval;
use DateTimeImmutable;
use Generator;
use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Entry;
use Tilttek\Activity\Exception\InvalidActivity;
use Tilttek\Activity\Exception\UnknownVerb;
use Tilttek\Activity\Filter;
use Tilttek\Activity\IdempotencyKey;
use Tilttek\Activity\Journal;
use Tilttek\Activity\JournalObserver;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Activity\Tests\Unit\Fixture\Clock;
use Tilttek\Activity\Tests\Unit\Fixture\Told;
use Tilttek\Activity\Tests\Unit\Fixture\Witness;
use Tilttek\Activity\Verbs;

#[CoversClass(Journal::class)]
final class JournalTest extends TestCase
{
    private Told $told;

    #[Override]
    protected function setUp(): void
    {
        $this->told = new Told();
    }

    public function testAGestureIsRecordedOnceAndItsObserversToldOnce(): void
    {
        $clock = new Clock('2026-11-07 12:00:00 UTC');
        $journal = new Journal(new InMemoryActivityLog(), $clock, new Verbs(), [$this->observer('first'), $this->observer('second')]);

        $recorded = $journal->record($this->activity('game:1:member:1'));
        self::assertTrue($recorded->new);
        self::assertSame('2026-11-07 12:00:00', $recorded->entry->at->format('Y-m-d H:i:s'));
        $clock->set('2026-11-07 13:00:00 UTC');
        self::assertFalse($journal->record($this->activity('game:1:member:1'))->new);
        self::assertSame(['first:recorded:1', 'second:recorded:1'], $this->told->lines);

        $cancelled = $journal->cancel('game:1:member:1');
        self::assertNotNull($cancelled);
        self::assertSame('2026-11-07 13:00:00', $cancelled->entry->at->format('Y-m-d H:i:s'));
        self::assertFalse($journal->cancel(IdempotencyKey::fromString('game:1:member:1'))?->new);
        self::assertNull($journal->cancel('unknown'));
        self::assertSame(['first:recorded:1', 'second:recorded:1', 'first:cancelled:2', 'second:cancelled:2'], $this->told->lines);
    }

    public function testObserversMayComeFromAGenerator(): void
    {
        $observers = (function (): Generator {
            yield 'first' => $this->observer('first');
            yield 'second' => $this->observer('second');
        })();
        $journal = new Journal(new InMemoryActivityLog(), new Clock(), observers: $observers);

        $journal->record($this->activity('a'));
        $journal->record($this->activity('b'));

        self::assertSame(['first:recorded:1', 'second:recorded:1', 'first:recorded:2', 'second:recorded:2'], $this->told->lines);
    }

    public function testWhatTheJournalHoldsIsReadBack(): void
    {
        $journal = new Journal(new InMemoryActivityLog(), new Clock());
        $journal->record($this->activity('a', 'member:1', 2));
        $journal->record($this->activity('b', 'member:2', 3));
        $journal->record($this->activity('c', 'member:1', 4));
        $journal->cancel('c');

        self::assertSame(1, $journal->find('a')?->position);
        self::assertSame(2, $journal->find(IdempotencyKey::fromString('b'))?->position);
        self::assertNull($journal->find('z'));
        self::assertTrue($journal->isCancelled('c'));
        self::assertTrue($journal->isCancelled(IdempotencyKey::fromString('c')));
        self::assertFalse($journal->isCancelled('a'));
        self::assertSame([2, 3, 4], array_map(static fn (Entry $entry): int => $entry->position, $journal->read(1, 10)));
        self::assertSame([1], array_map(static fn (Entry $entry): int => $entry->position, $journal->read(0, 1)));
        self::assertSame([2], array_map(static fn (Entry $entry): int => $entry->position, $journal->read(0, 10, Filter::all()->member(ResourceReference::of('member', '2')))));
        self::assertSame(2, $journal->totals(Filter::all()->member(ResourceReference::of('member', '1')))->quantity);

        $erasure = $journal->erase(ResourceReference::of('member', '1'));
        self::assertSame(3, $erasure->deleted);
        self::assertSame([2], array_map(static fn (Entry $entry): int => $entry->position, $journal->read(0, 10)));
    }

    public function testAnUndeclaredVerbIsRefusedBeforeItIsRecorded(): void
    {
        $log = new InMemoryActivityLog();
        $journal = new Journal($log, new Clock(), Verbs::of(['news.*']), [$this->observer('first')]);

        try {
            $journal->record($this->activity('a'));
            self::fail('An undeclared verb was recorded.');
        } catch (UnknownVerb) {
        }

        self::assertSame(0, $log->lastPosition());
        self::assertSame([], $this->told->lines);
        self::assertTrue($journal->record(Activity::of('b', 'member:1', 'news.read', new DateTimeImmutable('2026-11-07 10:00:00 UTC')))->new);
    }

    public function testAGestureIsDatedWithinTheJournalsWindow(): void
    {
        $log = new InMemoryActivityLog();
        $journal = new Journal($log, new Clock('2026-11-07 12:00:00 UTC'), observers: [$this->observer('first')]);

        // Thirty days back and five minutes ahead, by default, bounds included.
        self::assertSame([Journal::DEFAULT_PAST, Journal::DEFAULT_AHEAD], ['P30D', 'PT5M']);
        self::assertTrue($journal->record($this->dated('a', '2026-10-08 12:00:00 UTC'))->new);
        self::assertTrue($journal->record($this->dated('b', '2026-11-07 12:05:00 UTC'))->new);
        self::assertTrue($journal->record($this->dated('c', '2026-11-07 14:05:00 +02:00'))->new);

        foreach (['2026-10-08 11:59:59 UTC', '2026-11-07 12:05:01 UTC'] as $index => $moment) {
            try {
                $journal->record($this->dated('x'.$index, $moment));
                self::fail('The moment '.$moment.' was recorded.');
            } catch (InvalidActivity $invalidActivity) {
                self::assertStringStartsWith(\sprintf('The activity "x%d" is dated ', $index), $invalidActivity->getMessage());
                self::assertStringEndsWith(", outside the journal's window, from 2026-10-08T12:00:00+00:00 to 2026-11-07T12:05:00+00:00.", $invalidActivity->getMessage());
            }
        }

        self::assertSame(3, $log->lastPosition());
        self::assertCount(3, $this->told->lines);
    }

    public function testTheJournalsWindowIsChosen(): void
    {
        $journal = new Journal(new InMemoryActivityLog(), new Clock('2026-11-07 12:00:00 UTC'), past: new DateInterval('PT1H'), ahead: new DateInterval('PT0S'));

        self::assertTrue($journal->record($this->dated('a', '2026-11-07 11:00:00 UTC'))->new);
        self::assertTrue($journal->record($this->dated('b', '2026-11-07 12:00:00 UTC'))->new);

        foreach (['2026-11-07 10:59:59 UTC', '2026-11-07 12:00:01 UTC'] as $moment) {
            try {
                $journal->record($this->dated('x', $moment));
                self::fail('The moment '.$moment.' was recorded.');
            } catch (InvalidActivity $invalidActivity) {
                self::assertStringEndsWith('from 2026-11-07T11:00:00+00:00 to 2026-11-07T12:00:00+00:00.', $invalidActivity->getMessage());
            }
        }
    }

    public function testAJournalWithoutAPastBoundTakesAnyEarlierMoment(): void
    {
        $journal = new Journal(new InMemoryActivityLog(), new Clock('2026-11-07 12:00:00 UTC'), past: null);

        self::assertTrue($journal->record($this->dated('a', '1970-01-01 00:00:00 UTC'))->new);

        $this->expectExceptionObject(InvalidActivity::because('The activity "b" is dated 2026-11-07T12:05:01+00:00, outside the journal\'s window, from the beginning to 2026-11-07T12:05:00+00:00.'));
        $journal->record($this->dated('b', '2026-11-07 12:05:01 UTC'));
    }

    private function activity(string $key, string $member = 'member:1', int $quantity = 1): Activity
    {
        return Activity::of($key, $member, 'game.played', new DateTimeImmutable('2026-11-07 10:00:00 UTC'), quantity: $quantity);
    }

    private function dated(string $key, string $moment): Activity
    {
        return Activity::of($key, 'member:1', 'game.played', new DateTimeImmutable($moment));
    }

    private function observer(string $name): JournalObserver
    {
        return new Witness($name, $this->told);
    }
}
