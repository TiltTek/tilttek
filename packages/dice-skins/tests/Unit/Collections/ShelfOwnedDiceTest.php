<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Tests\Unit\Collections;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Tilttek\Collections\Collections;
use Tilttek\Collections\Dice\DiceShelf;
use Tilttek\Collections\Dice\InMemoryChoiceStore;
use Tilttek\Collections\FixedCatalog;
use Tilttek\Collections\Source;
use Tilttek\Collections\Store\InMemoryCompletionStore;
use Tilttek\Collections\Store\InMemoryCopyStore;
use Tilttek\DiceSkins\AppearanceSource;
use Tilttek\DiceSkins\Collections\ShelfOwnedDice;
use Tilttek\DiceSkins\DiceSkins;
use Tilttek\DiceSkins\OwnedDie;
use Tilttek\DiceSkins\Store\FixedSkinCatalog;
use Tilttek\DiceSkins\Store\InMemorySkinChoiceStore;

#[CoversClass(ShelfOwnedDice::class)]
final class ShelfOwnedDiceTest extends TestCase
{
    public function testTheDiceOfTheMembersSetsAreReadFromTheirShelf(): void
    {
        $collections = new Collections(
            FixedCatalog::fromArray([
                'rarities' => ['common' => 1],
                'items' => [
                    'ember-d6' => ['name' => ['en' => 'Ember d6', 'fr' => 'Braise d6'], 'rarity' => 'common', 'sets' => ['ember'], 'die' => ['sides' => 6, 'color' => '#C0392B', 'material' => 'metal']],
                    'ember-d20' => ['name' => 'Ember d20', 'rarity' => 'common', 'sets' => ['ember'], 'die' => ['sides' => 20]],
                    'medal' => ['name' => 'Medal', 'rarity' => 'common'],
                ],
                'sets' => ['ember' => ['name' => 'Ember dice', 'kind' => 'base']],
            ]),
            new InMemoryCopyStore(),
            new InMemoryCompletionStore(),
            new MockClock('2026-11-13 12:00:00 UTC'),
        );
        $shelf = new DiceShelf($collections, new InMemoryChoiceStore());
        $member = ResourceReference::of('member', '1');
        $copies = $collections->grantSet($member, 'ember', Source::Granted, 'welcome:1');
        $collections->grant($member, 'medal', Source::Granted, 'medal:1');
        $owned = new ShelfOwnedDice($collections, $shelf, 'fr');

        $dice = $owned->ownedBy($member);
        self::assertEqualsCanonicalizing(
            [['ember-d6', 'Braise d6', 6, '#c0392b', 'metal', $copies[0]->id], ['ember-d20', 'Ember d20', 20, null, null, $copies[1]->id]],
            array_map(static fn (OwnedDie $die): array => [$die->item, $die->name, $die->sides, $die->color?->hex, $die->material, $die->copy], $dice),
        );
        self::assertSame([], $owned->ownedBy(ResourceReference::of('member', '2')));
        self::assertNull(new ShelfOwnedDice($collections, $shelf, 'en')->chosen($member, 6));

        $shelf->choose($member, $copies[0]->id);
        $chosen = $owned->chosen($member, 6);
        self::assertSame(['ember-d6', 'Braise d6', '#c0392b', null], [$chosen?->item, $chosen?->name, $chosen?->color?->hex, $chosen?->copy]);
        self::assertNull($owned->chosen($member, 20));

        $skins = new DiceSkins(FixedSkinCatalog::withBase(), new InMemorySkinChoiceStore(), $owned);
        $appearance = $skins->appearance($member, 6);
        self::assertSame([AppearanceSource::Owned, '#c0392b', '#ffffff', 'metal'], [$appearance->source, $appearance->look->face->hex, $appearance->look->ink->hex, $appearance->look->material->value]);
    }
}
