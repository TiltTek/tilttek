<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Unit\Infrastructure;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Entry;
use Tilttek\Activity\EntryKind;
use Tilttek\Activity\Exception\IdempotencyConflict;
use Tilttek\Activity\Exception\InvalidActivity;
use Tilttek\Activity\Filter;
use Tilttek\Activity\IdempotencyKey;
use Tilttek\Activity\Log\ActivityLog;

/**
 * What every journal does: append once by key, cancel once, read in order through a filter, total what is not
 * cancelled, and erase a member.
 */
abstract class ActivityLogTestCase extends TestCase
{
    public function testAnActivityIsAppendedOnce(): void
    {
        $log = $this->log();
        self::assertSame(0, $log->lastPosition());

        $activity = $this->activity('game:1:member:1', 'member:1', 'game.played', '2026-11-07 10:00:00', 'game:1', 2, ['system' => 'dnd5e', 'first' => true]);
        $first = $log->append($activity, $this->at('2026-11-07 10:00:05'));
        self::assertTrue($first->new);
        self::assertSame([1, EntryKind::Recorded], [$first->entry->position, $first->entry->kind]);
        self::assertSame(1, $log->lastPosition());

        $this->clear();
        $again = $log->append($this->activity('game:1:member:1', 'member:1', 'game.played', '2026-11-07 11:00:00', 'game:1', 2, ['first' => true, 'system' => 'dnd5e']), $this->at('2026-11-07 11:00:05'));
        self::assertFalse($again->new);
        self::assertSame(1, $again->entry->position);
        self::assertSame('2026-11-07 10:00:00', $again->entry->activity->occurredAt->format('Y-m-d H:i:s'));
        self::assertSame('2026-11-07 10:00:05', $again->entry->at->format('Y-m-d H:i:s'));
        self::assertSame(1, $log->lastPosition());

        $found = $log->find(IdempotencyKey::fromString('game:1:member:1'));
        self::assertNotNull($found);
        self::assertSame($activity->toArray(), $found->activity->toArray());
        self::assertSame([1, EntryKind::Recorded, '2026-11-07 10:00:05'], [$found->position, $found->kind, $found->at->format('Y-m-d H:i:s')]);
        self::assertNull($log->find(IdempotencyKey::fromString('unknown')));
    }

    public function testTheSameKeyForAnotherGestureIsAConflict(): void
    {
        $log = $this->log();
        $log->append($this->activity('k', 'member:1', 'game.played'), $this->at());
        $this->clear();

        $this->expectException(IdempotencyConflict::class);
        $this->expectExceptionMessageIsOrContains('The idempotency key "k" is already recorded for another gesture.');

        $log->append($this->activity('k', 'member:1', 'game.won'), $this->at());
    }

    public function testAnActivityIsCancelledOnce(): void
    {
        $log = $this->log();
        $key = IdempotencyKey::fromString('order:1');
        self::assertNull($log->cancel($key, $this->at()));
        self::assertSame(0, $log->lastPosition());

        $log->append($this->activity('order:1', 'member:1', 'order.paid', '2026-11-07 10:00:00', 'order:1', 42), $this->at('2026-11-07 10:00:00'));
        $log->append($this->activity('order:2', 'member:1', 'order.paid'), $this->at());
        self::assertFalse($log->isCancelled($key));

        $cancelled = $log->cancel($key, $this->at('2026-11-08 09:00:00'));
        self::assertNotNull($cancelled);
        self::assertTrue($cancelled->new);
        self::assertSame([3, EntryKind::Cancelled, 'order:1', 42, '2026-11-08 09:00:00'], [$cancelled->entry->position, $cancelled->entry->kind, $cancelled->entry->activity->key->value, $cancelled->entry->activity->quantity, $cancelled->entry->at->format('Y-m-d H:i:s')]);
        $this->clear();
        self::assertTrue($log->isCancelled($key));
        self::assertFalse($log->isCancelled(IdempotencyKey::fromString('order:2')));

        $again = $log->cancel($key, $this->at('2026-11-09 09:00:00'));
        self::assertNotNull($again);
        self::assertFalse($again->new);
        self::assertSame([3, '2026-11-08 09:00:00'], [$again->entry->position, $again->entry->at->format('Y-m-d H:i:s')]);
        self::assertSame(3, $log->lastPosition());
        self::assertSame(1, $log->find($key)?->position);
    }

    public function testEntriesAreReadInOrderThroughAFilter(): void
    {
        $log = $this->log();
        $log->append($this->activity('a', 'member:1', 'game.played', '2026-11-01 10:00:00', 'game:1'), $this->at());
        $log->append($this->activity('b', 'member:2', 'game.won', '2026-11-02 10:00:00', 'game:1'), $this->at());
        $log->append($this->activity('c', 'member:1', 'news.commented', '2026-11-03 10:00:00', 'news:9'), $this->at());
        $log->append($this->activity('d', 'member:1', 'game_night.joined', '2026-11-04 10:00:00'), $this->at());
        $log->append($this->activity('e', 'member:1', 'gamebook.read', '2026-11-05 10:00:00'), $this->at());
        $log->cancel(IdempotencyKey::fromString('a'), $this->at());
        $this->clear();

        self::assertSame(['a', 'b', 'c', 'd', 'e', 'a'], $this->keys($log->read(0, 10, Filter::all())));
        self::assertSame([1, 2, 3, 4, 5, 6], array_map(static fn (Entry $entry): int => $entry->position, $log->read(0, 10, Filter::all())));
        self::assertSame(['a', 'b'], $this->keys($log->read(0, 2, Filter::all())));
        self::assertSame(['b'], $this->keys($log->read(1, 1, Filter::all())));
        self::assertSame(['e', 'a'], $this->keys($log->read(4, 10, Filter::all())));
        self::assertSame([], $log->read(6, 10, Filter::all()));
        self::assertSame(['a', 'c', 'd', 'e', 'a'], $this->keys($log->read(0, 10, Filter::all()->member(ResourceReference::of('member', '1')))));
        self::assertSame(['a', 'b', 'a'], $this->keys($log->read(0, 10, Filter::all()->verbs('game.*'))));
        self::assertSame(['d'], $this->keys($log->read(0, 10, Filter::all()->verbs('game_night.*'))));
        self::assertSame(['b', 'c'], $this->keys($log->read(0, 10, Filter::all()->verbs('game.won', 'news.*'))));
        self::assertSame(['a', 'b', 'c', 'd', 'e', 'a'], $this->keys($log->read(0, 10, Filter::all()->verbs('*'))));
        self::assertSame(['a', 'b', 'a'], $this->keys($log->read(0, 10, Filter::all()->object(ResourceReference::of('game', '1')))));
        self::assertSame(['b', 'c'], $this->keys($log->read(0, 10, Filter::all()->since($this->at('2026-11-02 10:00:00'))->until($this->at('2026-11-04 10:00:00')))));
        self::assertSame(['c'], $this->keys($log->read(2, 1, Filter::all()->member(ResourceReference::of('member', '1'))->since($this->at('2026-11-02 00:00:00')))));
        self::assertSame(['b'], $this->keys($log->read(0, 10, Filter::all()->member(ResourceReference::of('member', '2'))->verbs('game.*')->object(ResourceReference::of('game', '1'))->since($this->at('2026-11-02 10:00:00'))->until($this->at('2026-11-02 10:00:01')))));
    }

    public function testAReadIsBounded(): void
    {
        $log = $this->log();

        try {
            $log->read(-1, 10, Filter::all());
            self::fail('A negative position was read.');
        } catch (InvalidActivity $invalidActivity) {
            self::assertSame('A read starts after a position of 0 or more; -1 is not one.', $invalidActivity->getMessage());
        }

        $this->expectException(InvalidActivity::class);
        $this->expectExceptionMessageIsOrContains('A read takes at least one entry; 0 is not a limit.');

        $log->read(0, 0, Filter::all());
    }

    public function testTotalsLeaveCancelledActivitiesOut(): void
    {
        $log = $this->log();
        self::assertSame([0, 0], $this->totals($log, Filter::all()));

        $log->append($this->activity('a', 'member:1', 'game.played', quantity: 2), $this->at());
        $log->append($this->activity('b', 'member:1', 'game.played', quantity: 3), $this->at());
        $log->append($this->activity('c', 'member:1', 'news.read', quantity: 5), $this->at());
        $log->append($this->activity('d', 'member:2', 'game.played', quantity: 7), $this->at());
        $log->cancel(IdempotencyKey::fromString('b'), $this->at());
        $this->clear();

        self::assertSame([3, 14], $this->totals($log, Filter::all()));
        self::assertSame([2, 7], $this->totals($log, Filter::all()->member(ResourceReference::of('member', '1'))));
        self::assertSame([2, 9], $this->totals($log, Filter::all()->verbs('game.*')));
        self::assertSame([0, 0], $this->totals($log, Filter::all()->verbs('quest.*')));
    }

    public function testAMemberIsErased(): void
    {
        $log = $this->log();
        $log->append($this->activity('a', 'member:1', 'game.played'), $this->at());
        $log->append($this->activity('b', 'member:2', 'kudos.given', object: 'member:1'), $this->at());
        $log->append($this->activity('c', 'member:1', 'kudos.given', object: 'member:2'), $this->at());
        $log->append($this->activity('d', 'member:2', 'game.played', object: 'game:1'), $this->at());
        $log->cancel(IdempotencyKey::fromString('a'), $this->at());
        $log->cancel(IdempotencyKey::fromString('b'), $this->at());
        $this->clear();

        $erasure = $log->eraseMember(ResourceReference::of('member', '1'));
        self::assertSame([3, 2], [$erasure->deleted, $erasure->detached]);
        $this->clear();

        $entries = $log->read(0, 10, Filter::all());
        self::assertSame(['b', 'd', 'b'], $this->keys($entries));
        self::assertSame([null, 'game:1', null], array_map(static fn (Entry $entry): ?string => $entry->activity->object?->toString(), $entries));
        self::assertSame([2, 4, 6], array_map(static fn (Entry $entry): int => $entry->position, $entries));
        self::assertNull($log->find(IdempotencyKey::fromString('a')));
        self::assertFalse($log->isCancelled(IdempotencyKey::fromString('a')));
        self::assertNull($log->cancel(IdempotencyKey::fromString('c'), $this->at()));
        self::assertSame(6, $log->lastPosition());

        $again = $log->eraseMember(ResourceReference::of('member', '1'));
        self::assertSame([0, 0], [$again->deleted, $again->detached]);
    }

    public function testPositionsAreNeverGivenTwice(): void
    {
        $log = $this->log();
        $log->append($this->activity('a', 'member:1', 'game.played'), $this->at());
        $log->append($this->activity('b', 'member:2', 'game.played'), $this->at());
        $log->eraseMember(ResourceReference::of('member', '2'));
        $this->clear();
        self::assertSame(1, $log->lastPosition());

        self::assertSame(3, $log->append($this->activity('c', 'member:2', 'game.played'), $this->at())->entry->position);
        self::assertSame(3, $log->lastPosition());
        $log->eraseMember(ResourceReference::of('member', '1'));
        $log->eraseMember(ResourceReference::of('member', '2'));
        self::assertSame(0, $log->lastPosition());
    }

    abstract protected function log(): ActivityLog;

    protected function clear(): void
    {
    }

    /**
     * @param array<string, bool|int|string> $context
     */
    protected function activity(string $key, string $member, string $verb, string $at = '2026-11-07 10:00:00', ?string $object = null, int $quantity = 1, array $context = []): Activity
    {
        return Activity::of($key, $member, $verb, $this->at($at), $object, $quantity, $context);
    }

    protected function at(string $at = '2026-11-07 10:00:00'): DateTimeImmutable
    {
        return new DateTimeImmutable($at.' UTC');
    }

    /**
     * @param list<Entry> $entries
     *
     * @return list<string>
     */
    private function keys(array $entries): array
    {
        return array_map(static fn (Entry $entry): string => $entry->activity->key->value, $entries);
    }

    /**
     * @return array{int, int}
     */
    private function totals(ActivityLog $log, Filter $filter): array
    {
        $totals = $log->totals($filter);

        return [$totals->count, $totals->quantity];
    }
}
