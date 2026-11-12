<?php

declare(strict_types=1);

namespace Tilttek\GeneratorBundle\Tests\Unit\Infrastructure;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Tilttek\GeneratorBundle\Infrastructure\State\CacheStateStore;
use Tilttek\GeneratorBundle\Infrastructure\State\InMemoryStateStore;

#[CoversClass(CacheStateStore::class)]
#[CoversClass(InMemoryStateStore::class)]
final class StateStoresTest extends TestCase
{
    public function testACachePoolKeepsTheStatesByGeneratorAndKey(): void
    {
        $pool = new ArrayAdapter();
        $store = new CacheStateStore($pool);

        self::assertNull($store->load('weather', 'campaign {1}'));
        $store->save('weather', 'campaign {1}', ['cell' => '10']);
        $store->save('weather', 'campaign {2}', ['cell' => '3']);
        $store->save('travel', 'campaign {1}', ['day' => '4']);

        self::assertSame(['cell' => '10'], $store->load('weather', 'campaign {1}'));
        self::assertSame(['cell' => '3'], $store->load('weather', 'campaign {2}'));
        self::assertSame(['day' => '4'], $store->load('travel', 'campaign {1}'));
        self::assertTrue($pool->hasItem('tilttek_generator_state.'.hash('sha256', "weather\ncampaign {1}")));
        self::assertNull($store->load("weather\ncampaign", '{1}'));

        $store->forget('weather', 'campaign {1}');
        self::assertNull($store->load('weather', 'campaign {1}'));
        self::assertSame(['cell' => '3'], $store->load('weather', 'campaign {2}'));
    }

    public function testACachedValueThatIsNotAStateIsReadAsWellAsItCanBe(): void
    {
        $pool = new ArrayAdapter();
        $store = new CacheStateStore($pool);
        $key = 'tilttek_generator_state.'.hash('sha256', "weather\ncampaign");

        $pool->save($pool->getItem($key)->set('rain'));
        self::assertNull($store->load('weather', 'campaign'));

        $pool->save($pool->getItem($key)->set(['cell' => '10', 3 => 'x', 'n' => 4]));
        self::assertSame(['cell' => '10'], $store->load('weather', 'campaign'));

        $pool->save($pool->getItem($key)->set([]));
        self::assertSame([], $store->load('weather', 'campaign'));
    }

    public function testMemoryKeepsTheStatesUntilItIsReset(): void
    {
        $store = new InMemoryStateStore();

        self::assertNull($store->load('weather', 'campaign'));
        $store->save('weather', 'campaign', ['cell' => '10']);
        $store->save('weather', 'other', ['cell' => '4']);
        self::assertSame(['cell' => '10'], $store->load('weather', 'campaign'));

        $store->forget('weather', 'campaign');
        self::assertNull($store->load('weather', 'campaign'));
        self::assertSame(['cell' => '4'], $store->load('weather', 'other'));

        $store->reset();
        self::assertNull($store->load('weather', 'other'));
    }
}
