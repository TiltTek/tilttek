<?php

declare(strict_types=1);

namespace Tilttek\Sets\Tests\Unit\Symfony;

use DateTimeImmutable;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\TestWith;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\RecurringMessage;
use Tilttek\Sets\Completion;
use Tilttek\Sets\Exception\InvalidSet;
use Tilttek\Sets\SetDefinition;
use Tilttek\Sets\Sets;
use Tilttek\Sets\Store\InMemoryCompletionStore;
use Tilttek\Sets\Store\InMemoryDefinitionStore;
use Tilttek\Sets\Symfony\DeliverPendingCompletions;
use Tilttek\Sets\Symfony\DeliverPendingCompletionsHandler;
use Tilttek\Sets\Symfony\DeliverySchedule;
use Tilttek\Sets\Tests\Unit\Fixture\FrozenClock;
use Tilttek\Sets\Tests\Unit\Fixture\RecordingDispatcher;

#[CoversClass(DeliverySchedule::class)]
#[CoversClass(DeliverPendingCompletions::class)]
#[CoversClass(DeliverPendingCompletionsHandler::class)]
final class ScheduleTest extends TestCase
{
    /**
     * @return list<object>
     */
    private function messages(RecurringMessage $recurring): array
    {
        $context = new MessageContext(DeliverySchedule::NAME, $recurring->getId(), $recurring->getTrigger(), new DateTimeImmutable());

        return iterator_to_array($recurring->getMessages($context), false);
    }

    public function testTheScheduleSendsTheMessageEveryFiveMinutesByDefault(): void
    {
        $schedule = new DeliverySchedule()->getSchedule();
        $recurring = $schedule->getRecurringMessages();

        self::assertCount(1, $recurring);
        self::assertSame('every 5 minutes', (string) $recurring[0]->getTrigger());
        self::assertEquals([new DeliverPendingCompletions(100)], $this->messages($recurring[0]));
        self::assertTrue($schedule->shouldProcessOnlyLastMissedRun());
        self::assertNull($schedule->getLock());
    }

    public function testTheIntervalTheLimitAndTheLockAreTheApplications(): void
    {
        $lock = new LockFactory(new InMemoryStore())->createLock(DeliverySchedule::NAME);
        $schedule = new DeliverySchedule('1 hour', 500, $lock)->getSchedule();
        $recurring = $schedule->getRecurringMessages();

        self::assertSame('every 1 hour', (string) $recurring[0]->getTrigger());
        self::assertEquals([new DeliverPendingCompletions(500)], $this->messages($recurring[0]));
        self::assertSame($lock, $schedule->getLock());
        self::assertSame('every 30 seconds', (string) new DeliverySchedule(30)->getSchedule()->getRecurringMessages()[0]->getTrigger());
    }

    public function testTheScheduleAndTheHandlerAreDeclared(): void
    {
        $schedule = new ReflectionClass(DeliverySchedule::class)->getAttributes(AsSchedule::class);
        self::assertCount(1, $schedule);
        self::assertSame('tilttek_sets', $schedule[0]->newInstance()->name);
        self::assertSame('tilttek_sets', DeliverySchedule::NAME);

        self::assertCount(1, new ReflectionClass(DeliverPendingCompletionsHandler::class)->getAttributes(AsMessageHandler::class));
    }

    public function testTheMessageCarriesALimitOnly(): void
    {
        self::assertSame(100, new DeliverPendingCompletions()->limit);
        self::assertSame(100, DeliverPendingCompletions::LIMIT);
        self::assertSame(1, new DeliverPendingCompletions(1)->limit);
        self::assertSame(Sets::PENDING_LIMIT, new DeliverPendingCompletions(Sets::PENDING_LIMIT)->limit);
        self::assertSame(['limit'], array_keys(get_object_vars(new DeliverPendingCompletions())));
    }

    #[TestWith([0])]
    #[TestWith([Sets::PENDING_LIMIT + 1])]
    public function testALimitOutOfBoundsIsRefusedAsTheScheduleIsBuilt(int $limit): void
    {
        $this->expectException(InvalidSet::class);
        $this->expectExceptionMessageIsOrContains(\sprintf('A pending delivery tells 1 to 10000 holders; %d is not.', $limit));

        new DeliverySchedule(limit: $limit);
    }

    public function testTheHandlerDeliversThePendingCompletions(): void
    {
        $definitions = new InMemoryDefinitionStore();
        $definitions->add(new SetDefinition('starter', 1, ['a']));

        $store = new InMemoryCompletionStore();
        $at = new DateTimeImmutable('2026-11-13 12:00:00 UTC');

        foreach (['1', '2', '3'] as $id) {
            $store->add(new Completion('starter', ResourceReference::of('member', $id), null, 1, $at));
        }

        $events = new RecordingDispatcher();
        $handler = new DeliverPendingCompletionsHandler(new Sets($definitions, $store, new FrozenClock(), $events));

        self::assertSame(2, $handler(new DeliverPendingCompletions(2)));
        self::assertCount(2, $events->events);
        self::assertSame(1, $handler(new DeliverPendingCompletions()));
    }
}
