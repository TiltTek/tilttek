<?php

declare(strict_types=1);

namespace Tilttek\BadgesBundle\Tests\Unit\Infrastructure\Store;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\TestCase;
use Tilttek\Badges\Profile;
use Tilttek\Badges\Store\ProfileStore;

/**
 * The contract of a `ProfileStore`, which every implementation passes.
 */
abstract class ProfileStoreTestCase extends TestCase
{
    public function testAProfileIsKeptAndReplaced(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');

        $empty = $store->get($member);
        self::assertTrue($empty->isEmpty());
        self::assertTrue($empty->member->equals($member));

        $store->save(new Profile($member, ['gold', 'silver', 'bronze'], ['bronze', 'gold'], 'silver'));
        $store->save(new Profile(ResourceReference::of('member', '2'), ['gold']));

        $profile = $store->get($member);
        self::assertSame([['gold', 'silver', 'bronze'], ['bronze', 'gold'], 'silver'], [$profile->shown, $profile->featured, $profile->title]);
        self::assertTrue($profile->member->equals($member));

        $store->save(new Profile($member, ['gold']));
        $profile = $store->get($member);
        self::assertSame([['gold'], [], null], [$profile->shown, $profile->featured, $profile->title]);
        self::assertSame(['gold'], $store->get(ResourceReference::of('member', '2'))->shown);
    }

    public function testAnEmptyProfileIsRemoved(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');
        $store->save(new Profile($member, ['gold']));
        $store->save(new Profile($member));

        self::assertTrue($store->get($member)->isEmpty());
        self::assertFalse($store->erase($member));
        $store->save(new Profile($member));
        self::assertFalse($store->erase($member));
    }

    public function testAProfileIsErased(): void
    {
        $store = $this->store();
        $member = ResourceReference::of('member', '1');
        $store->save(new Profile($member, ['gold']));
        $store->save(new Profile(ResourceReference::of('member', '2'), ['gold']));

        self::assertTrue($store->erase($member));
        self::assertTrue($store->get($member)->isEmpty());
        self::assertFalse($store->erase($member));
        self::assertFalse($store->get(ResourceReference::of('member', '2'))->isEmpty());
    }

    abstract protected function store(): ProfileStore;
}
