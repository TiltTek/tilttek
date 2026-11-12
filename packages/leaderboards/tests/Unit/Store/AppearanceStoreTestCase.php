<?php

declare(strict_types=1);

namespace Tilttek\Leaderboards\Tests\Unit\Store;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Leaderboards\Appearance;
use Tilttek\Leaderboards\Store\AppearanceStore;

/**
 * The contract every store of appearances passes: extend it with the store to test.
 */
abstract class AppearanceStoreTestCase extends TestCase
{
    abstract protected function store(): AppearanceStore;

    public function testTheChoicesAreKeptAndHiddenOnesForgotten(): void
    {
        $store = $this->store();
        $one = ResourceReference::fromString('member:1');
        $two = ResourceReference::fromString('member:2');
        $three = ResourceReference::fromString('member:3');

        self::assertSame([], $store->of([]));
        self::assertSame([], $store->of([$one]));

        $store->choose($one, Appearance::named());
        $store->choose($two, Appearance::pseudonym('Gris'));
        $store->choose($three, Appearance::hidden());

        self::assertEquals(['member:1' => Appearance::named(), 'member:2' => Appearance::pseudonym('Gris')], $store->of([$one, $two, $three]));

        $store->choose($one, Appearance::pseudonym('Nain'));
        $store->choose($two, Appearance::hidden());

        self::assertEquals(['member:1' => Appearance::pseudonym('Nain')], $store->of([$one, $two, $three]));

        $store->choose($one, Appearance::named());

        self::assertEquals(['member:1' => Appearance::named()], $store->of([$one]));
    }

    public function testAMemberIsErased(): void
    {
        $store = $this->store();
        $store->choose(ResourceReference::fromString('member:1'), Appearance::named());
        $store->choose(ResourceReference::fromString('member:2'), Appearance::named());

        self::assertSame(1, $store->eraseMember(ResourceReference::fromString('member:1')));
        self::assertSame(0, $store->eraseMember(ResourceReference::fromString('member:1')));
        self::assertSame(['member:2'], array_keys($store->of([ResourceReference::fromString('member:1'), ResourceReference::fromString('member:2')])));
    }
}
