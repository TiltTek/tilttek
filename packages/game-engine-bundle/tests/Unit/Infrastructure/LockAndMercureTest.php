<?php

declare(strict_types=1);

namespace Tilttek\GameEngineBundle\Tests\Unit\Infrastructure;

use InvalidArgumentException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Log\AbstractLogger;
use RuntimeException;
use Stringable;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\Mercure\Exception\RuntimeException as MercureFailure;
use Symfony\Component\Mercure\Jwt\StaticTokenProvider;
use Symfony\Component\Mercure\MockHub;
use Symfony\Component\Mercure\Update;
use Tilttek\GameEngine\TableUpdate;
use Tilttek\GameEngineBundle\Infrastructure\Lock\SymfonyTableLock;
use Tilttek\GameEngineBundle\Infrastructure\Mercure\MercureTablePublisher;
use Tilttek\GameEngineBundle\Infrastructure\Mercure\TableTopic;
use Tilttek\GameEngineBundle\Tests\Fixture\RecordingLockFactory;

#[CoversClass(SymfonyTableLock::class)]
#[CoversClass(MercureTablePublisher::class)]
#[CoversClass(TableTopic::class)]
final class LockAndMercureTest extends TestCase
{
    public function testTheWorkRunsWhileTheLockOfItsTableIsHeld(): void
    {
        $store = new InMemoryStore();
        $factory = new LockFactory($store);
        $lock = new SymfonyTableLock($factory);
        $resource = SymfonyTableLock::resource('table:1');

        self::assertSame('tilttek_game_engine.table.'.hash('xxh128', 'table:1'), $resource);
        self::assertSame(42, $lock->hold('table:1', static function () use ($factory, $resource): int {
            self::assertFalse($factory->createLock($resource)->acquire(false));

            return 42;
        }));
        self::assertTrue($factory->createLock($resource)->acquire(false));

        $other = new LockFactory(new InMemoryStore());

        try {
            new SymfonyTableLock($other, 5.0)->hold('table:2', static function (): bool {
                throw new RuntimeException('The work failed.');
            });
        } catch (RuntimeException $runtimeException) {
            self::assertSame('The work failed.', $runtimeException->getMessage());
        }

        self::assertTrue($other->createLock(SymfonyTableLock::resource('table:2'))->acquire(false));
    }

    public function testTheLockIsWaitedForAndReleasedWhateverTheWorkDoes(): void
    {
        foreach ([false, true] as $fails) {
            $lock = $this->createMock(SharedLockInterface::class);
            $lock->expects(self::once())->method('acquire')->with(true)->willReturn(true);
            $lock->expects(self::once())->method('release');
            $factory = $this->createMock(LockFactory::class);
            $factory->expects(self::once())->method('createLock')->with(SymfonyTableLock::resource('table:1'))->willReturn($lock);

            try {
                new SymfonyTableLock($factory)->hold('table:1', static function () use ($fails): bool {
                    if ($fails) {
                        throw new RuntimeException('The work failed.');
                    }

                    return true;
                });
                self::assertFalse($fails);
            } catch (RuntimeException) {
                self::assertTrue($fails);
            }
        }
    }

    public function testTheLockLivesForItsTimeToLive(): void
    {
        $factory = new RecordingLockFactory();
        new SymfonyTableLock($factory, 7.0)->hold('table:1', static fn (): bool => true);
        self::assertSame(7.0, $factory->ttl);

        new SymfonyTableLock($factory)->hold('table:1', static fn (): bool => true);
        self::assertSame(30.0, $factory->ttl);
    }

    public function testEveryChangeIsPublishedOnTheTopicOfItsTableWithIdentifiersOnly(): void
    {
        $published = [];
        $hub = new MockHub('https://hub.test/.well-known/mercure', new StaticTokenProvider('jwt'), static function (Update $update) use (&$published): string {
            $published[] = $update;

            return 'id';
        });

        new MercureTablePublisher($hub)->publish(new TableUpdate('table:1', 3, 'race', 2, ['ann'], false));
        new MercureTablePublisher($hub, new TableTopic('https://example.test/tables/{table}'), false)->publish(new TableUpdate('table:2', 9, 'end', 4, [], true));

        self::assertCount(2, $published);
        self::assertSame(['urn:tilttek-game-engine:table:table:1'], $published[0]->getTopics());
        self::assertSame('{"table":"table:1","sequence":3,"phase":"race","round":2,"to_act":["ann"],"over":false}', $published[0]->getData());
        self::assertTrue($published[0]->isPrivate());
        self::assertSame(['https://example.test/tables/table:2'], $published[1]->getTopics());
        self::assertSame('{"table":"table:2","sequence":9,"phase":"end","round":4,"to_act":[],"over":true}', $published[1]->getData());
        self::assertFalse($published[1]->isPrivate());
    }

    public function testTheDataOfAnUpdateIsReadableWithoutAPublisher(): void
    {
        self::assertSame('{"table":"t","sequence":1,"phase":"p","round":0,"to_act":[],"over":false}', MercureTablePublisher::data(new TableUpdate('t', 1, 'p', 0, [], false)));
    }

    public function testAHubThatFailsIsLoggedAndNotThrown(): void
    {
        $hub = new MockHub('https://hub.test/.well-known/mercure', new StaticTokenProvider('jwt'), static function (): never {
            throw new MercureFailure('The hub is down.');
        });
        $logger = new class extends AbstractLogger {
            /** @var list<array{string, string, array<mixed>}> */
            public array $records = [];

            public function log($level, string|Stringable $message, array $context = []): void
            {
                $this->records[] = [\is_string($level) ? $level : '', (string) $message, $context];
            }
        };

        new MercureTablePublisher($hub, logger: $logger)->publish(new TableUpdate('table:1', 3, 'race', 2, ['ann'], false));
        new MercureTablePublisher($hub)->publish(new TableUpdate('table:1', 3, 'race', 2, ['ann'], false));

        self::assertCount(1, $logger->records);
        self::assertSame('warning', $logger->records[0][0]);
        self::assertSame('The change of the table {table} was not published on the Mercure hub: {message}', $logger->records[0][1]);
        self::assertSame(['table' => 'table:1', 'sequence' => 3, 'message' => 'The hub is down.'], \array_slice($logger->records[0][2], 0, 3));
        self::assertInstanceOf(MercureFailure::class, $logger->records[0][2]['exception']);
    }

    public function testATopicHoldsTheIdentifierOfItsTableOnce(): void
    {
        self::assertSame('urn:tilttek-game-engine:table:t-1', new TableTopic()->of('t-1'));
        self::assertSame('/tables/t.1/live', new TableTopic('/tables/{table}/live')->of('t.1'));

        foreach (['/tables', '/{table}/{table}'] as $template) {
            try {
                new TableTopic($template);
                self::fail(\sprintf('The topic "%s" was accepted.', $template));
            } catch (InvalidArgumentException $exception) {
                self::assertSame(\sprintf('The topic of the tables "%s" holds "{table}" once.', $template), $exception->getMessage());
            }
        }
    }
}
