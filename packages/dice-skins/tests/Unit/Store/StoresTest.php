<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Tests\Unit\Store;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceSkins\BaseSkins;
use Tilttek\DiceSkins\Exception\InvalidSkin;
use Tilttek\DiceSkins\SkinChoice;
use Tilttek\DiceSkins\Store\FixedSkinCatalog;
use Tilttek\DiceSkins\Store\InMemorySkinChoiceStore;
use Tilttek\DiceSkins\Store\NoOwnedDice;

#[CoversClass(FixedSkinCatalog::class)]
#[CoversClass(InMemorySkinChoiceStore::class)]
#[CoversClass(NoOwnedDice::class)]
final class StoresTest extends TestCase
{
    public function testTheCatalogKeepsItsSkinsInOrderByKey(): void
    {
        $catalog = new FixedSkinCatalog(BaseSkins::all());

        self::assertEquals(BaseSkins::all(), $catalog->skins());
        self::assertSame('Gold', $catalog->skin('gold')?->label);
        self::assertNull($catalog->skin('retired'));
        self::assertEquals(BaseSkins::all(), FixedSkinCatalog::withBase()->skins());
    }

    public function testTheCatalogRefusesTwoSkinsOfAKey(): void
    {
        $this->expectException(InvalidSkin::class);
        $this->expectExceptionMessageIsOrContains('Two skins have the key "ivory".');

        FixedSkinCatalog::withBase('[{"key": "ivory", "label": "Mine", "face": "#fff", "ink": "#000"}]');
    }

    public function testTheChoicesAreKeptByMemberAndSides(): void
    {
        $store = new InMemorySkinChoiceStore();
        $one = ResourceReference::of('member', '1');
        $all = new SkinChoice($one, 'ember');
        $d20 = new SkinChoice($one, 'gold', 20);
        $d6 = new SkinChoice($one, 'ivory', 6);

        $store->save($d20);
        $store->save($all);
        $store->save($d6);

        self::assertSame([$all, $d6, $d20], $store->ofMember($one));
        self::assertSame($all, $store->find($one, null));
        self::assertSame($d6, $store->find($one, 6));
        self::assertNull($store->find($one, 8));
        self::assertNull($store->find(ResourceReference::of('member', '2'), null));

        $again = new SkinChoice($one, 'obsidian', 6);
        $store->save($again);
        self::assertSame($again, $store->find($one, 6));
        self::assertCount(3, $store->ofMember($one));

        self::assertTrue($store->remove($one, null));
        self::assertFalse($store->remove($one, null));
        self::assertSame($again, $store->find($one, 6), 'Removing the choice for all the dice keeps the others.');
        self::assertSame([], $store->ofMember(ResourceReference::of('member', '2')));
        self::assertSame(2, $store->eraseMember($one));
        self::assertSame(0, $store->eraseMember($one));
        self::assertSame([], $store->ofMember($one));
    }

    public function testTheChoicesOfAMemberAreSortedBySides(): void
    {
        $one = ResourceReference::of('member', '1');
        $choices = [new SkinChoice($one, 'gold', 20), new SkinChoice($one, 'gold', 12), new SkinChoice($one, 'gold', 8), new SkinChoice($one, 'gold', 6), new SkinChoice($one, 'ember'), new SkinChoice($one, 'gold', 4)];

        foreach ([$choices, array_reverse($choices)] as $order) {
            $store = new InMemorySkinChoiceStore();

            foreach ($order as $choice) {
                $store->save($choice);
            }

            self::assertSame([4, 5, 3, 2, 1, 0], array_map(static fn (SkinChoice $choice): int => (int) array_search($choice, $choices, true), $store->ofMember($one)));
        }
    }

    public function testNobodyOwnsDiceWithoutCollections(): void
    {
        $member = ResourceReference::of('member', '1');

        self::assertSame([], new NoOwnedDice()->ownedBy($member));
        self::assertNull(new NoOwnedDice()->chosen($member, 6));
    }
}
