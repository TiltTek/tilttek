<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Clock\NativeClock;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Tilttek\GeneratorBundle\Infrastructure\RateLimit\SymfonyDrawRateLimiter;

#[CoversClass(SymfonyDrawRateLimiter::class)]
final class SymfonyDrawRateLimiterTest extends TestCase
{
    public function testADrawPastTheLimitWaits(): void
    {
        $limiter = new SymfonyDrawRateLimiter($this->factory(), new NativeClock());

        self::assertSame([null, null], [$limiter->consume('client:a'), $limiter->consume('client:b')]);
        $wait = $limiter->consume('client:a');
        self::assertNotNull($wait);
        self::assertGreaterThanOrEqual(59, $wait);
        self::assertLessThanOrEqual(61, $wait);
    }

    public function testTheWaitIsAtLeastOneSecond(): void
    {
        $limiter = new SymfonyDrawRateLimiter($this->factory(), new MockClock('+1 day'));
        $limiter->consume('client:a');

        self::assertSame(1, $limiter->consume('client:a'));
    }

    private function factory(): RateLimiterFactory
    {
        return new RateLimiterFactory(['id' => 'draws', 'policy' => 'fixed_window', 'limit' => 1, 'interval' => '60 seconds'], new InMemoryStorage());
    }
}
