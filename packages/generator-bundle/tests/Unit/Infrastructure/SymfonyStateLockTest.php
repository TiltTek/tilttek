<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Tests\Unit\Infrastructure;

use LogicException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\SharedLockInterface;
use Symfony\Component\Lock\Store\InMemoryStore;
use Tilttek\GeneratorBundle\Infrastructure\Lock\SymfonyStateLock;

#[CoversClass(SymfonyStateLock::class)]
final class SymfonyStateLockTest extends TestCase
{
    public function testTheWorkRunsWhileTheLockOfItsGeneratorAndKeyIsHeld(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $lock = new SymfonyStateLock($factory);

        $result = $lock->hold('weather', 'campaign', static function () use ($factory): string {
            self::assertFalse($factory->createLock(SymfonyStateLock::resource('weather', 'campaign'))->acquire());
            self::assertTrue($factory->createLock(SymfonyStateLock::resource('weather', 'other'))->acquire());

            return 'done';
        });

        self::assertSame('done', $result);
        self::assertTrue($factory->createLock(SymfonyStateLock::resource('weather', 'campaign'))->acquire());
    }

    public function testTheLockIsReleasedWhenTheWorkFails(): void
    {
        $factory = new LockFactory(new InMemoryStore());
        $lock = new SymfonyStateLock($factory);

        try {
            $lock->hold('weather', 'campaign', static function () use ($factory): string {
                if ($factory->createLock(SymfonyStateLock::resource('weather', 'campaign'))->acquire()) {
                    return 'not held';
                }

                throw new LogicException('broken');
            });
            self::fail('The exception is lost.');
        } catch (LogicException $logicException) {
            self::assertSame('broken', $logicException->getMessage());
        }

        self::assertTrue($factory->createLock(SymfonyStateLock::resource('weather', 'campaign'))->acquire());
    }

    public function testTheLockIsWaitedForAndReleasedByTheLockItself(): void
    {
        $held = $this->createMock(SharedLockInterface::class);
        $held->expects(self::once())->method('acquire')->with(true)->willReturn(true);
        $held->expects(self::once())->method('release');
        $factory = $this->createMock(LockFactory::class);
        $factory->expects(self::once())->method('createLock')->with(SymfonyStateLock::resource('weather', 'campaign'), SymfonyStateLock::TTL)->willReturn($held);

        self::assertSame('done', new SymfonyStateLock($factory)->hold('weather', 'campaign', static fn (): string => 'done'));
    }

    public function testTheLockIsReleasedByTheLockItselfWhenTheWorkFails(): void
    {
        $held = $this->createMock(SharedLockInterface::class);
        $held->method('acquire')->willReturn(true);
        $held->expects(self::once())->method('release');
        $factory = self::createStub(LockFactory::class);
        $factory->method('createLock')->willReturn($held);

        $this->expectException(LogicException::class);

        new SymfonyStateLock($factory)->hold('weather', 'campaign', static fn (): never => throw new LogicException('broken'));
    }

    public function testTheResourceIsAHashOfTheGeneratorAndTheKey(): void
    {
        self::assertSame('tilttek_generator.state.'.hash('sha256', "weather\0campaign"), SymfonyStateLock::resource('weather', 'campaign'));
        self::assertNotSame(SymfonyStateLock::resource('weather', 'campaign'), SymfonyStateLock::resource('weather', 'campaigns'));
        self::assertNotSame(SymfonyStateLock::resource('weather', 'campaign'), SymfonyStateLock::resource('travel', 'campaign'));
        self::assertSame(30.0, SymfonyStateLock::TTL);
    }
}
