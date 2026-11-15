<?php

declare(strict_types=1);

namespace Tilttek\Sets\Tests\Unit;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Tilttek\Sets\Completion;
use Tilttek\Sets\Contract\CompletionStore;
use Tilttek\Sets\Contract\PendingDeliveries;
use Tilttek\Sets\DeliveryBackoff;
use Tilttek\Sets\Event\DeliveryKeepsFailing;
use Tilttek\Sets\Event\SetCompleted;
use Tilttek\Sets\Event\TierReached;
use Tilttek\Sets\Exception\DeliveryFailed;
use Tilttek\Sets\Exception\InvalidSet;
use Tilttek\Sets\Exception\UnsupportedStore;
use Tilttek\Sets\SetDefinition;
use Tilttek\Sets\Sets;
use Tilttek\Sets\Store\InMemoryCompletionStore;
use Tilttek\Sets\Store\InMemoryDefinitionStore;
use Tilttek\Sets\Tests\Unit\Fixture\FailingDispatcher;
use Tilttek\Sets\Tests\Unit\Fixture\FrozenClock;
use Tilttek\Sets\Tests\Unit\Fixture\PlainCompletionStore;
use Tilttek\Sets\Tests\Unit\Fixture\RecordingDispatcher;
use Tilttek\Sets\Tests\Unit\Fixture\UnboundedPendingStore;
use Tilttek\Sets\Tier;

#[CoversClass(Sets::class)]
#[CoversClass(InMemoryCompletionStore::class)]
#[CoversClass(DeliveryFailed::class)]
#[CoversClass(UnsupportedStore::class)]
#[CoversClass(DeliveryBackoff::class)]
#[CoversClass(DeliveryKeepsFailing::class)]
final class PendingDeliveriesTest extends TestCase
{
    private InMemoryDefinitionStore $definitions;

    private DateTimeImmutable $at;

    protected function setUp(): void
    {
        $this->definitions = new InMemoryDefinitionStore();
        $this->definitions->add(new SetDefinition('starter', 1, ['a', 'b'], [new Tier('half', required: 1)]));

        $this->at = new DateTimeImmutable('2026-11-13 12:00:00 UTC');
    }

    private function holder(string $id): ResourceReference
    {
        return ResourceReference::of('member', $id);
    }

    /**
     * Adds, undelivered, the completion of the set and of its tier for each holder named, in that order.
     */
    private function pending(CompletionStore $store, string ...$holders): void
    {
        foreach ($holders as $id) {
            $store->add(new Completion('starter', $this->holder($id), 'half', 1, $this->at));
            $store->add(new Completion('starter', $this->holder($id), null, 1, $this->at));
        }
    }

    /**
     * @param list<object> $events
     *
     * @return list<string> the holder of each event
     */
    private function holders(array $events): array
    {
        return array_map(static function (object $event): string {
            self::assertTrue($event instanceof SetCompleted || $event instanceof TierReached);

            return $event->completion->holder->id;
        }, $events);
    }

    public function testTheStoreListsEachHolderWaitingOnceTheLongestWaitingFirst(): void
    {
        $store = new InMemoryCompletionStore();
        $first = $this->holder('3');
        $store->add(new Completion('starter', $first, null, 1, $this->at));
        $store->add(new Completion('starter', $this->holder('1'), null, 1, $this->at));
        $store->add(new Completion('starter', $this->holder('3'), 'half', 1, $this->at));
        $store->add($delivered = new Completion('starter', $this->holder('2'), null, 1, $this->at));
        $store->add(new Completion('other', $this->holder('1'), null, 1, $this->at));
        $store->add(new Completion('starter', $this->holder('4'), null, 1, $this->at));
        $store->markDelivered($delivered);

        self::assertSame([$first], $store->holdersWithUndelivered(1, $this->at));
        self::assertEquals([$first, $this->holder('1')], $store->holdersWithUndelivered(2, $this->at));
        self::assertEquals([$first, $this->holder('1'), $this->holder('4')], $store->holdersWithUndelivered(10, $this->at));
        self::assertSame($first, $store->holdersWithUndelivered(10, $this->at)[0]);
        self::assertSame([], $store->holdersWithUndelivered(0, $this->at));
        self::assertSame([], $store->holdersWithUndelivered(-1, $this->at));

        $store->eraseHolder($first);
        self::assertEquals([$this->holder('1'), $this->holder('4')], $store->holdersWithUndelivered(10, $this->at));
        self::assertSame([], new InMemoryCompletionStore()->holdersWithUndelivered(10, $this->at));
    }

    public function testThePendingCompletionsAreToldForTheHoldersWaitingTheLongest(): void
    {
        $store = new InMemoryCompletionStore();
        $events = new RecordingDispatcher();
        $sets = new Sets($this->definitions, $store, new FrozenClock(), $events);
        $this->pending($store, '2', '1', '3');

        self::assertSame(4, $sets->deliverPending(2));
        self::assertSame(['2', '2', '1', '1'], $this->holders($events->events));
        self::assertEquals([$this->holder('3')], $store->holdersWithUndelivered(10, $this->at));

        self::assertSame(2, $sets->deliverPending(Sets::PENDING_LIMIT));
        self::assertSame([], $store->holdersWithUndelivered(10, $this->at));
        self::assertSame(0, $sets->deliverPending(1));
        self::assertCount(6, $events->events);
    }

    public function testAHolderWhoseHandlerFailsDoesNotHoldTheOthersBack(): void
    {
        $store = new InMemoryCompletionStore();
        $clock = new FrozenClock($this->at);
        $events = new FailingDispatcher(static fn (object $event): bool => $event instanceof TierReached && '1' !== $event->completion->holder->id, 2);
        $sets = new Sets($this->definitions, $store, $clock, $events);
        $this->pending($store, '2', '1', '3');

        try {
            $sets->deliverPending(10);
            self::fail('No exception.');
        } catch (DeliveryFailed $deliveryFailed) {
            self::assertSame(4, $deliveryFailed->delivered);
            self::assertSame(2, $deliveryFailed->failedHolders);
            self::assertSame(3, $deliveryFailed->holders);
            self::assertSame('A handler threw for 2 of the 3 holders told; 4 completions were delivered all the same. The first exception is the previous one.', $deliveryFailed->getMessage());
            self::assertInstanceOf(RuntimeException::class, $deliveryFailed->getPrevious());
            self::assertSame('The handler failed, time 1.', $deliveryFailed->getPrevious()->getMessage());
        }

        self::assertSame(['2', '1', '1', '3'], $this->holders($events->delivered));
        self::assertSame(1, $store->failedDeliveries($this->holder('2')));
        self::assertSame(1, $store->failedDeliveries($this->holder('3')));
        self::assertSame(0, $store->failedDeliveries($this->holder('1')));

        // Postponed by a minute: the next run passes them by.
        self::assertSame([], $store->holdersWithUndelivered(10, $this->at));
        self::assertSame(0, $sets->deliverPending(10));
        self::assertSame([], $store->holdersWithUndelivered(10, $this->at->modify('+59 seconds')));
        self::assertEquals([$this->holder('2'), $this->holder('3')], $store->holdersWithUndelivered(10, $this->at->modify('+60 seconds')));

        $clock->now = $this->at->modify('+60 seconds');
        self::assertSame(2, $sets->deliverPending(10));
        self::assertSame([], $store->holdersWithUndelivered(10, $clock->now));
        self::assertSame(0, $store->failedDeliveries($this->holder('2')));
        self::assertSame(0, $store->failedDeliveries($this->holder('3')));
    }

    public function testAHolderWhoKeepsFailingWaitsLongerEachTimeAndIsReported(): void
    {
        $store = new InMemoryCompletionStore();
        $clock = new FrozenClock($this->at);
        $events = new FailingDispatcher(static fn (object $event): bool => $event instanceof TierReached && '1' === $event->completion->holder->id, 5);
        $sets = new Sets($this->definitions, $store, $clock, $events, new DeliveryBackoff(10, 30, 3));
        $this->pending($store, '1');
        $waited = [];

        for ($run = 1; $run <= 4; ++$run) {
            try {
                $sets->deliverPending(10);
                self::fail('No exception.');
            } catch (DeliveryFailed $deliveryFailed) {
                self::assertSame(1, $deliveryFailed->failedHolders);
            }

            self::assertSame($run, $store->failedDeliveries($this->holder('1')));
            $before = $clock->now;

            while ([] === $store->holdersWithUndelivered(10, $clock->now)) {
                $clock->now = $clock->now->modify('+1 second');
            }

            $waited[] = $clock->now->getTimestamp() - $before->getTimestamp();
        }

        self::assertSame([10, 20, 30, 30], $waited);

        $reports = array_values(array_filter($events->delivered, static fn (object $event): bool => $event instanceof DeliveryKeepsFailing));
        self::assertCount(2, $reports);
        self::assertInstanceOf(DeliveryKeepsFailing::class, $reports[0]);
        self::assertTrue($reports[0]->holder->equals($this->holder('1')));
        self::assertSame(3, $reports[0]->failures);
        self::assertEquals($this->at->modify('+60 seconds'), $reports[0]->nextAttemptAt);
        self::assertSame('The handler failed, time 3.', $reports[0]->failure->getMessage());
        self::assertInstanceOf(DeliveryKeepsFailing::class, $reports[1]);
        self::assertSame(4, $reports[1]->failures);
    }

    public function testAHolderPostponedDoesNotHoldBackTheOthers(): void
    {
        $store = new InMemoryCompletionStore();
        $events = new RecordingDispatcher();
        $sets = new Sets($this->definitions, $store, new FrozenClock($this->at), $events);
        $this->pending($store, '1', '2');
        $store->postpone($this->holder('1'), 2, $this->at->modify('+1 hour'));

        self::assertSame(2, $sets->deliverPending(1));
        self::assertSame(['2', '2'], $this->holders($events->events));
        self::assertSame(2, $store->failedDeliveries($this->holder('1')));
    }

    public function testAReportWhoseListenerThrowsDoesNotStopTheRun(): void
    {
        $store = new InMemoryCompletionStore();
        $events = new FailingDispatcher(static fn (object $event): bool => $event instanceof DeliveryKeepsFailing || ($event instanceof TierReached && '1' === $event->completion->holder->id), 2);
        $sets = new Sets($this->definitions, $store, new FrozenClock($this->at), $events, new DeliveryBackoff(reportAfter: 1));
        $this->pending($store, '1', '2');

        try {
            $sets->deliverPending(10);
            self::fail('No exception.');
        } catch (DeliveryFailed $deliveryFailed) {
            self::assertSame('The handler failed, time 1.', $deliveryFailed->getPrevious()?->getMessage());
            self::assertSame(3, $deliveryFailed->delivered);
        }

        self::assertSame(0, $events->failures);
        self::assertSame(['1', '2', '2'], $this->holders($events->delivered));
    }

    public function testErasingAHolderForgetsTheirFailures(): void
    {
        $store = new InMemoryCompletionStore();
        $this->pending($store, '1');
        $store->postpone($this->holder('1'), 3, $this->at->modify('+1 hour'));
        $store->forgetFailures($this->holder('2'));

        self::assertSame(3, $store->failedDeliveries($this->holder('1')));

        $store->eraseHolder($this->holder('1'));
        self::assertSame(0, $store->failedDeliveries($this->holder('1')));

        $store->postpone($this->holder('1'), 1, $this->at);
        $store->forgetFailures($this->holder('1'));
        self::assertSame(0, $store->failedDeliveries($this->holder('1')));
    }

    public function testARunTellsNoMoreHoldersThanItsLimitWhateverTheStoreGives(): void
    {
        $store = new UnboundedPendingStore();
        $events = new RecordingDispatcher();
        $sets = new Sets($this->definitions, $store, new FrozenClock(), $events);
        $this->pending($store, '1', '2');

        self::assertSame(2, $sets->deliverPending(1));
        self::assertSame(['1', '1'], $this->holders($events->events));
    }

    #[TestWith([0])]
    #[TestWith([-1])]
    #[TestWith([Sets::PENDING_LIMIT + 1])]
    public function testALimitOutOfBoundsIsRefused(int $limit): void
    {
        $sets = new Sets($this->definitions, new InMemoryCompletionStore());

        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A pending delivery tells 1 to 10000 holders; %d is not.', $limit));

        $sets->deliverPending($limit);
    }

    public function testAStoreThatCannotListTheHoldersWaitingIsSaid(): void
    {
        $store = new PlainCompletionStore();
        self::assertNotInstanceOf(PendingDeliveries::class, $store);
        $sets = new Sets($this->definitions, $store);

        $this->expectException(UnsupportedStore::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('The completion store %s does not implement %s: it cannot list the holders with undelivered completions.', PlainCompletionStore::class, PendingDeliveries::class));

        $sets->deliverPending(1);
    }
}
