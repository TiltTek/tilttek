<?php

declare(strict_types=1);

namespace Tilttek\ActivityBundle\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\Messenger\Envelope;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\ReceivedStamp;
use Symfony\Component\Messenger\Transport\InMemory\InMemoryTransport;
use Tilttek\Activity\Activity;
use Tilttek\Activity\Journal;
use Tilttek\ActivityBundle\Infrastructure\Messenger\CatchUpProjection;
use Tilttek\ActivityBundle\Tests\Functional\App\GamesPlayed;
use Tilttek\ActivityBundle\Tests\Functional\App\TestKernel;
use Tilttek\ActivityBundle\TilttekActivityBundle;

/**
 * The projections out of the request: an activity recorded sends a message that names the projection and the entry,
 * the projection stays where it was until a worker consumes the message, and a message handled twice applies nothing.
 */
#[CoversClass(CatchUpProjection::class)]
#[CoversClass(TilttekActivityBundle::class)]
final class AsyncProjectionsTest extends KernelTestCase
{
    #[Override]
    protected static function getKernelClass(): string
    {
        return TestKernel::class;
    }

    #[Override]
    protected function setUp(): void
    {
        if (!ContainerBuilder::willBeAvailable('symfony/event-dispatcher', EventDispatcher::class, ['symfony/dependency-injection'])) {
            self::markTestSkipped('The event dispatcher is installed for development only: no kernel can boot.');
        }

        self::bootKernel(['environment' => 'async']);
        $entityManager = $this->service('doctrine.orm.entity_manager', EntityManagerInterface::class);

        foreach (new SchemaTool($entityManager)->getSchemaFromMetadata([])->toSql($entityManager->getConnection()->getDatabasePlatform()) as $sql) {
            $entityManager->getConnection()->executeStatement($sql);
        }
    }

    public function testTheProjectionsFollowTheJournalThroughMessenger(): void
    {
        $journal = $this->service(Journal::class, Journal::class);
        $games = $this->service(GamesPlayed::class, GamesPlayed::class);
        $transport = $this->service('messenger.transport.async', InMemoryTransport::class);

        $journal->record(Activity::of('game:1:member:1', 'member:1', 'game.played', new DateTimeImmutable('2026-11-06 20:00:00 UTC'), 'game:1'));
        $journal->record(Activity::of('news:3:member:1', 'member:1', 'news.commented', new DateTimeImmutable('2026-11-06 21:00:00 UTC'), 'news:3'));
        $journal->record(Activity::of('game:2:member:2', 'member:2', 'game.played', new DateTimeImmutable('2026-11-06 22:00:00 UTC'), 'game:2'));

        self::assertSame([], $games->games);
        self::assertEquals([new CatchUpProjection('games_played', 1), new CatchUpProjection('games_played', 3)], array_map(static fn (Envelope $envelope): object => $envelope->getMessage(), $transport->getSent()));

        // What a worker does with each message it receives from the transport: hands it to the bus as received.
        $bus = $this->service('messenger.bus.default', MessageBusInterface::class);

        foreach ($transport->get() as $envelope) {
            $bus->dispatch($envelope->with(new ReceivedStamp('async')));
        }

        self::assertSame(['member:1' => 1, 'member:2' => 1], $games->games);

        $bus->dispatch(new Envelope(new CatchUpProjection('games_played', 1), [new ReceivedStamp('async')]));

        self::assertSame(['member:1' => 1, 'member:2' => 1], $games->games);
    }

    /**
     * @template T of object
     *
     * @param class-string<T> $class
     *
     * @return T
     */
    private function service(string $id, string $class): object
    {
        $service = self::getContainer()->get($id);
        self::assertInstanceOf($class, $service);

        return $service;
    }
}
