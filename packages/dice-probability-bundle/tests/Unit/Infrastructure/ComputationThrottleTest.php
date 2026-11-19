<?php

declare(strict_types=1);

namespace Tilttek\DiceProbabilityBundle\Tests\Unit\Infrastructure;

use Override;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\RateLimiter\LimiterInterface;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;
use Tilttek\DiceProbabilityBundle\Infrastructure\RateLimiter\ComputationThrottle;

#[CoversClass(ComputationThrottle::class)]
final class ComputationThrottleTest extends TestCase
{
    /**
     * @return iterable<string, array{?string, string}>
     */
    public static function networks(): iterable
    {
        yield 'an IPv4 address' => ['192.0.2.17', '192.0.2.17'];
        yield 'an IPv6 address' => ['2001:db8:1:2:3:4:5:6', '2001:db8:1:2::/64'];
        yield 'another of the same /64' => ['2001:db8:1:2:ffff::1', '2001:db8:1:2::/64'];
        yield 'an IPv4 address mapped into IPv6' => ['::ffff:192.0.2.17', '192.0.2.17'];
        yield 'an IPv6 address that only starts with zeros' => ['::1', '::/64'];
        yield 'no address' => [null, ComputationThrottle::UNKNOWN];
        yield 'not an address' => ['localhost', ComputationThrottle::UNKNOWN];
        yield 'an empty address' => ['', ComputationThrottle::UNKNOWN];
    }

    #[DataProvider('networks')]
    public function testTheNetworkOfAnAddress(?string $address, string $network): void
    {
        self::assertSame($network, ComputationThrottle::network($address));
        self::assertSame('unknown', ComputationThrottle::UNKNOWN);
    }

    public function testEachNetworkHasItsOwnLimit(): void
    {
        $requests = new RequestStack();
        $throttle = new ComputationThrottle(new RateLimiterFactory(['id' => 'test', 'policy' => 'fixed_window', 'limit' => 2, 'interval' => '1 minute'], new InMemoryStorage()), $requests, 'secret');
        $from = static function (string $address) use ($requests): void {
            $requests->pop();
            $requests->push(Request::create('/', server: ['REMOTE_ADDR' => $address]));
        };

        $from('2001:db8::1');
        self::assertTrue($throttle->allows());
        $from('2001:db8::2');
        self::assertTrue($throttle->allows());
        self::assertFalse($throttle->allows(), 'The /64 has spent its limit.');

        $from('2001:db8:0:1::1');
        self::assertTrue($throttle->allows(), 'Another /64 has its own limit.');
    }

    public function testTheMainRequestIsCountedAndTheKeyIsADigest(): void
    {
        $factory = new class implements RateLimiterFactoryInterface {
            /** @var list<?string> */
            public array $keys = [];

            #[Override]
            public function create(?string $key = null): LimiterInterface
            {
                $this->keys[] = $key;

                return new RateLimiterFactory(['id' => 'test', 'policy' => 'no_limit'], new InMemoryStorage())->create($key);
            }
        };
        $requests = new RequestStack([Request::create('/', server: ['REMOTE_ADDR' => '192.0.2.17'])]);
        $requests->push(Request::create('/', server: ['REMOTE_ADDR' => '198.51.100.4']));

        self::assertTrue(new ComputationThrottle($factory, $requests, 'secret')->allows());
        self::assertTrue(new ComputationThrottle($factory, new RequestStack(), 'secret')->allows());
        self::assertSame([hash_hmac('sha256', '192.0.2.17', 'secret'), hash_hmac('sha256', 'unknown', 'secret')], $factory->keys);
    }

    public function testAFailingLimiterRefuses(): void
    {
        $factory = new class implements RateLimiterFactoryInterface {
            #[Override]
            public function create(?string $key = null): LimiterInterface
            {
                throw new RuntimeException('The storage is down.');
            }
        };

        self::assertFalse(new ComputationThrottle($factory, new RequestStack(), 'secret')->allows());
    }
}
