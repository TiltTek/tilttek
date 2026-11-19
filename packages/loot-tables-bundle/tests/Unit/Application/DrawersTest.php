<?php

declare(strict_types=1);

namespace Tilttek\LootTablesBundle\Tests\Unit\Application;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\LootTables\Exception\InvalidLoot;
use Tilttek\LootTables\Exception\UnknownLoot;
use Tilttek\LootTablesBundle\Application\Drawers;
use Tilttek\LootTablesBundle\Tests\Unit\Fixture\Shop;

#[CoversClass(Drawers::class)]
final class DrawersTest extends TestCase
{
    public function testTheNamesOfTheDrawers(): void
    {
        $drawers = Shop::drawers();

        self::assertSame('loot-box:starter', Drawers::box('starter'));
        self::assertTrue($drawers->supports('loot-box:starter'));
        self::assertTrue($drawers->supports('loot-text'));
        self::assertFalse($drawers->supports('loot-box:unknown'));
        self::assertFalse($drawers->supports('starter'));
        self::assertFalse($drawers->supports('xloot-box:starter'));
        self::assertTrue($drawers->isText('loot-text'));
        self::assertFalse($drawers->isText('loot-box:starter'));
    }

    public function testTheBoxOfADrawer(): void
    {
        $drawers = Shop::drawers();

        self::assertSame(Shop::catalog()->box('starter')->dice(), $drawers->boxOf('loot-box:starter', null)->dice());
        $text = $drawers->boxOf('loot-text', Shop::EPITAPHS);
        self::assertSame([3], $text->dice());
        self::assertSame('text', $text->key);
        self::assertSame('Gone fishing.', $text->tables[0]->entry(2)->name);
    }

    public function testATextWithoutAList(): void
    {
        $this->expectException(InvalidLoot::class);

        Shop::drawers()->boxOf('loot-text', null);
    }

    public function testAnUnknownBox(): void
    {
        $this->expectExceptionObject(UnknownLoot::box('unknown'));

        Shop::drawers()->boxOf('loot-box:unknown', null);
    }

    public function testADrawerOfAnotherKind(): void
    {
        $this->expectExceptionObject(UnknownLoot::box('starter'));

        Shop::drawers()->boxOf('starter', null);
    }
}
