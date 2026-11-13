<?php

declare(strict_types=1);

namespace Tilttek\PuzzleBuilder\Tests\Unit\Limiter;

use DateTimeImmutable;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Tilttek\PuzzleBuilder\Exception\InvalidScenario;
use Tilttek\PuzzleBuilder\Limiter\Allowance;
use Tilttek\PuzzleBuilder\Limiter\InMemoryGuessLimiter;
use Tilttek\PuzzleBuilder\Limiter\RateLimiterGuessLimiter;

#[CoversClass(InMemoryGuessLimiter::class)]
#[CoversClass(RateLimiterGuessLimiter::class)]
#[CoversClass(Allowance::class)]
#[CoversClass(InvalidScenario::class)]
final class LimitersTest extends TestCase
{
    public function testAnAllowanceSaysWhenToRetry(): void
    {
        $at = new DateTimeImmutable('2026-11-13 20:00 UTC');

        self::assertTrue(Allowance::granted()->allowed());
        self::assertNull(Allowance::granted()->retryAfter);
        self::assertFalse(Allowance::refused($at)->allowed());
        self::assertSame($at, Allowance::refused($at)->retryAfter);
    }

    public function testTheMemoryLimiterCountsEachKeyInItsWindow(): void
    {
        $clock = new MockClock('2026-11-13 20:00:00 UTC');
        $limiter = new InMemoryGuessLimiter(2, 60, $clock);

        self::assertTrue($limiter->attempt('a')->allowed());
        $clock->sleep(59);
        self::assertTrue($limiter->attempt('a')->allowed());
        self::assertTrue($limiter->attempt('b')->allowed());

        $refused = $limiter->attempt('a');
        self::assertFalse($refused->allowed());
        self::assertSame('2026-11-13 20:01:00', $refused->retryAfter?->format('Y-m-d H:i:s'));
        self::assertFalse($limiter->attempt('a')->allowed());

        $clock->sleep(1);
        self::assertTrue($limiter->attempt('a')->allowed());
        self::assertTrue($limiter->attempt('a')->allowed());
        self::assertFalse($limiter->attempt('a')->allowed());
        self::assertSame(2, $limiter->keys());
    }

    public function testTheMemoryLimiterDropsTheWindowsOverOnceFull(): void
    {
        $clock = new MockClock('2026-11-13 20:00:00 UTC');
        $limiter = new InMemoryGuessLimiter(1, 60, $clock);

        for ($key = 0; $key < InMemoryGuessLimiter::MAX_KEYS - 1; ++$key) {
            $limiter->attempt('old'.$key);
        }

        $clock->sleep(30);
        $limiter->attempt('recent');
        self::assertSame(InMemoryGuessLimiter::MAX_KEYS, $limiter->keys());

        $clock->sleep(30);
        $limiter->attempt('new');
        self::assertSame(2, $limiter->keys());
        self::assertFalse($limiter->attempt('recent')->allowed());
        $limiter->attempt('new');
        self::assertSame(2, $limiter->keys());
    }

    public function testTheMemoryLimiterKeepsItsKeysUnderTheBound(): void
    {
        $limiter = new InMemoryGuessLimiter(1, 60, new MockClock('2026-11-13 20:00:00 UTC'));

        for ($key = 0; $key < InMemoryGuessLimiter::MAX_KEYS; ++$key) {
            $limiter->attempt('k'.$key);
        }

        self::assertTrue($limiter->attempt('one-more')->allowed());
        self::assertSame(InMemoryGuessLimiter::MAX_KEYS + 1, $limiter->keys());
        self::assertFalse($limiter->attempt('k0')->allowed());
    }

    public function testTheMemoryLimiterTakesOneGuessInOneSecond(): void
    {
        $clock = new MockClock('2026-11-13 20:00:00 UTC');
        $limiter = new InMemoryGuessLimiter(1, 1, $clock);

        self::assertTrue($limiter->attempt('a')->allowed());
        self::assertFalse($limiter->attempt('a')->allowed());
    }

    public function testTheMemoryLimiterTakesTenGuessesInAMinuteByDefault(): void
    {
        $clock = new MockClock('2026-11-13 20:00:00 UTC');
        $limiter = new InMemoryGuessLimiter(clock: $clock);

        for ($guess = 0; $guess < 10; ++$guess) {
            self::assertTrue($limiter->attempt('a')->allowed());
        }

        $refused = $limiter->attempt('a');
        self::assertFalse($refused->allowed());
        self::assertSame('2026-11-13 20:01:00', $refused->retryAfter?->format('Y-m-d H:i:s'));

        $clock->sleep(59);
        self::assertFalse($limiter->attempt('a')->allowed());

        $clock->sleep(1);
        self::assertTrue($limiter->attempt('a')->allowed());
    }

    public function testANoLimitIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);
        $this->expectExceptionMessageIsOrContains('A limit of guesses allows 1 guess or more in 1 second or more.');

        new InMemoryGuessLimiter(0);
    }

    public function testANoPeriodIsRefused(): void
    {
        $this->expectException(InvalidScenario::class);

        new InMemoryGuessLimiter(1, 0);
    }

    public function testTheRateLimiterCountsWithItsPolicy(): void
    {
        $factory = new RateLimiterFactory(['id' => 'guesses', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'], new InMemoryStorage());
        $limiter = new RateLimiterGuessLimiter($factory);

        self::assertTrue($limiter->attempt('manor|team:7|clock')->allowed());
        self::assertTrue($limiter->attempt('manor|team:7|clock')->allowed());
        self::assertTrue($limiter->attempt('manor|team:8|clock')->allowed());

        $refused = $limiter->attempt('manor|team:7|clock');
        self::assertFalse($refused->allowed());
        self::assertGreaterThan(time(), $refused->retryAfter?->getTimestamp());
    }
}
