<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Tests\Unit;

use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceSkins\Accessibility;
use Tilttek\DiceSkins\AppearanceSource;
use Tilttek\DiceSkins\Color;
use Tilttek\DiceSkins\DiceSkins;
use Tilttek\DiceSkins\DieAppearance;
use Tilttek\DiceSkins\Exception\InvalidSkin;
use Tilttek\DiceSkins\Exception\SkinRefused;
use Tilttek\DiceSkins\Material;
use Tilttek\DiceSkins\OwnedDie;
use Tilttek\DiceSkins\Skin;
use Tilttek\DiceSkins\SkinChoice;
use Tilttek\DiceSkins\Store\FixedSkinCatalog;
use Tilttek\DiceSkins\Store\InMemorySkinChoiceStore;
use Tilttek\DiceSkins\Tests\Unit\Fixture\FixedOwnedDice;

#[CoversClass(DiceSkins::class)]
#[CoversClass(DieAppearance::class)]
#[CoversClass(OwnedDie::class)]
#[CoversClass(SkinChoice::class)]
#[CoversClass(SkinRefused::class)]
final class DiceSkinsTest extends TestCase
{
    private const string SITE_SKINS = <<<'JSON'
        [
            {"key": "ember", "label": "Ember", "face": "#9b1b30", "ink": "#ffffff", "material": "pearl", "texture": "pearl/soft",
             "types": {"20": {"face": "#1b1b1f", "ink": "#f5f5f5"}}},
            {"key": "fog", "label": "Fog", "face": "#767676", "ink": "#ffffff", "material": "glitter", "texture": "glitter/fine"}
        ]
        JSON;

    public function testTheDefaultSkinDressesTheDiceOfAMemberWhoChoseNothing(): void
    {
        $appearance = $this->skins()->appearance($this->member(), 6);

        self::assertSame(['sides' => 6, 'name' => 'Ivory', 'source' => 'default', 'skin' => 'ivory', 'item' => null, 'high_contrast' => false, 'contrast' => 15.3, 'face' => '#f6f1e3', 'ink' => '#1f1a14', 'material' => 'solid'], $appearance->toArray());
        self::assertSame(AppearanceSource::Default, $appearance->source);
    }

    public function testAMemberChoosesASkinForAllTheirDiceAndAnotherForOneType(): void
    {
        $store = new InMemorySkinChoiceStore();
        $skins = $this->skins($store);
        $member = $this->member();

        $all = $skins->choose($member, 'ember');
        $d6 = $skins->choose($member, 'obsidian', 6);

        self::assertSame(['member' => 'member:1', 'skin' => 'ember', 'sides' => null], $all->toArray());
        self::assertSame(['member' => 'member:1', 'skin' => 'obsidian', 'sides' => 6], $d6->toArray());
        self::assertSame([$all, $d6], $skins->choices($member));

        $six = $skins->appearance($member, 6);
        self::assertSame([AppearanceSource::ChosenForSides, 'obsidian', 'Obsidian', '#1b1b1f'], [$six->source, $six->skin, $six->name, $six->look->face->hex]);
        $eight = $skins->appearance($member, 8);
        self::assertSame([AppearanceSource::ChosenForAll, 'ember', '#9b1b30', 'pearl/soft'], [$eight->source, $eight->skin, $eight->look->face->hex, $eight->look->texture?->key]);
        self::assertSame('#1b1b1f', $skins->appearance($member, 20)->look->face->hex, 'The d20 of the skin has its own look.');
        self::assertSame(AppearanceSource::Default, $skins->appearance($this->member('2'), 8)->source);

        self::assertTrue($skins->clear($member, 6));
        self::assertFalse($skins->clear($member, 6));
        self::assertSame(AppearanceSource::ChosenForAll, $skins->appearance($member, 6)->source);
        self::assertTrue($skins->clear($member));
        self::assertSame(AppearanceSource::Default, $skins->appearance($member, 6)->source);
    }

    public function testASkinTheCatalogNoLongerOffersIsPassedOver(): void
    {
        $store = new InMemorySkinChoiceStore();
        $store->save(new SkinChoice($this->member(), 'retired', 6));
        $store->save(new SkinChoice($this->member(), 'retired'));

        self::assertSame(AppearanceSource::Default, $this->skins($store)->appearance($this->member(), 6)->source);
    }

    public function testOnlyASkinOfTheCatalogIsChosen(): void
    {
        $store = new InMemorySkinChoiceStore();

        try {
            $this->skins($store)->choose($this->member(), 'retired');
            self::fail('A skin the catalog does not offer is refused.');
        } catch (SkinRefused $skinRefused) {
            self::assertSame('The catalog offers no skin "retired".', $skinRefused->getMessage());
        }

        self::assertSame([], $store->ofMember($this->member()));
    }

    public function testAChoiceIsForADie(): void
    {
        $store = new InMemorySkinChoiceStore();

        try {
            $this->skins($store)->choose($this->member(), 'ember', 1);
            self::fail('A die has 2 sides at least.');
        } catch (InvalidSkin $invalidSkin) {
            self::assertSame('A die has 2 to 1000 sides; not 1.', $invalidSkin->getMessage());
        }

        self::assertSame([], $store->ofMember($this->member()));
        $this->expectExceptionMessageIsOrContains('A die has 2 to 1000 sides; not 0.');
        $this->skins($store)->appearance($this->member(), 0);
    }

    public function testADieTheMemberOwnsAndChoseComesFirst(): void
    {
        $store = new InMemorySkinChoiceStore();
        $frost = new OwnedDie('frost-d6', 'Frost d6', 6, Color::fromHex('#5dade2'), 'Glass', 'copy-1');
        $owned = new FixedOwnedDice(['member:1' => [$frost]], ['member:1/6' => $frost]);
        $skins = $this->skins($store, $owned);
        $skins->choose($this->member(), 'ember', 6);

        self::assertSame([$frost], $skins->owned($this->member()));
        self::assertSame(['sides' => 6, 'name' => 'Frost d6', 'source' => 'owned', 'skin' => null, 'item' => 'frost-d6', 'high_contrast' => false, 'contrast' => 8.54, 'face' => '#5dade2', 'ink' => '#000000', 'material' => 'translucent'], $skins->appearance($this->member(), 6)->toArray());
        self::assertSame('ivory', $skins->appearance($this->member(), 8)->skin);
    }

    public function testAnOwnedDieWithoutColourTakesTheLookTheDiceWouldHave(): void
    {
        $store = new InMemorySkinChoiceStore();
        $plain = new OwnedDie('ember-d20', 'Ember d20', 20);
        $skins = $this->skins($store, new FixedOwnedDice(chosen: ['member:1/20' => $plain]));
        $skins->choose($this->member(), 'ember');

        $appearance = $skins->appearance($this->member(), 20);

        self::assertSame([AppearanceSource::Owned, 'Ember d20', 'ember', 'ember-d20', '#1b1b1f'], [$appearance->source, $appearance->name, $appearance->skin, $appearance->item, $appearance->look->face->hex]);
        self::assertNull($plain->look());
    }

    public function testHighContrastKeepsALookThatReachesSevenToOne(): void
    {
        $store = new InMemorySkinChoiceStore();
        $skins = $this->skins($store);
        $skins->choose($this->member(), 'ember');

        $appearance = $skins->appearance($this->member(), 6, Accessibility::HighContrast);

        self::assertSame(['sides' => 6, 'name' => 'Ember', 'source' => 'chosen-for-all', 'skin' => 'ember', 'item' => null, 'high_contrast' => true, 'contrast' => 8.1, 'face' => '#9b1b30', 'ink' => '#ffffff', 'material' => 'solid'], $appearance->toArray());
        self::assertSame(Material::Pearl, $skins->appearance($this->member(), 6, Accessibility::Standard)->look->material);
    }

    public function testHighContrastChangesTheInkWhenThatIsEnough(): void
    {
        $store = new InMemorySkinChoiceStore();
        $skins = new DiceSkins(FixedSkinCatalog::withBase('[{"key": "dusk", "label": "Dusk", "face": "#2b2b6b", "ink": "#b0b0b0"}]'), $store);
        $skins->choose($this->member(), 'dusk');

        self::assertLessThan(Color::AAA, $skins->appearance($this->member(), 6)->look->contrast());
        $appearance = $skins->appearance($this->member(), 6, Accessibility::HighContrast);

        self::assertSame(['#2b2b6b', '#ffffff', true], [$appearance->look->face->hex, $appearance->look->ink->hex, $appearance->highContrast]);
    }

    public function testHighContrastFallsBackToBlackOnWhite(): void
    {
        $store = new InMemorySkinChoiceStore();
        $owned = new FixedOwnedDice(chosen: ['member:1/6' => new OwnedDie('fog-d6', 'Fog d6', 6, Color::fromHex('#777777'))]);
        $skins = $this->skins($store, $owned);
        $skins->choose($this->member(), 'fog');

        $fog = $skins->appearance($this->member(), 8, Accessibility::HighContrast);
        self::assertSame(['Fog', 'fog', '#ffffff', '#000000', 'solid'], [$fog->name, $fog->skin, $fog->look->face->hex, $fog->look->ink->hex, $fog->look->material->value]);
        self::assertNull($fog->look->texture);
        self::assertSame(AppearanceSource::ChosenForAll, $fog->source);

        $owned = $skins->appearance($this->member(), 6, Accessibility::HighContrast);
        self::assertSame(['Fog d6', 'fog-d6', '#ffffff', '#000000', true], [$owned->name, $owned->item, $owned->look->face->hex, $owned->look->ink->hex, $owned->highContrast]);
    }

    public function testTheAppearanceIsGivenToTheRenderer(): void
    {
        $store = new InMemorySkinChoiceStore();
        $skins = $this->skins($store);
        $skins->choose($this->member(), 'ember');

        $standard = $skins->appearance($this->member(), 6);
        self::assertSame(['--tilttek-dice-color' => '#9b1b30', '--tilttek-dice-ink' => '#ffffff'], $standard->cssProperties());
        self::assertSame('--tilttek-dice-color: #9b1b30; --tilttek-dice-ink: #ffffff', $standard->style());
        self::assertSame(['data-dice-appearance' => 'Ember', 'data-dice-sides' => '6', 'data-dice-material' => 'pearl', 'data-dice-texture' => 'pearl/soft'], $standard->dataAttributes());

        self::assertSame(['data-dice-appearance' => 'Ember', 'data-dice-sides' => '6', 'data-dice-material' => 'solid', 'data-dice-contrast' => 'high'], $skins->appearance($this->member(), 6, Accessibility::HighContrast)->dataAttributes());
    }

    public function testTheDefaultSkinMustBeOffered(): void
    {
        $skins = new DiceSkins(new FixedSkinCatalog([]), new InMemorySkinChoiceStore(), default: 'ivory');

        self::assertSame([], $skins->skins());
        $this->expectExceptionMessageIsOrContains('The catalog offers no skin "ivory".');
        $skins->appearance($this->member(), 6);
    }

    public function testASiteChoosesItsDefaultAndItsSkins(): void
    {
        $skins = new DiceSkins(FixedSkinCatalog::withBase(self::SITE_SKINS), new InMemorySkinChoiceStore(), default: 'ember');

        self::assertSame('ember', $skins->appearance($this->member(), 6)->skin);
        self::assertCount(10, $skins->skins());
        self::assertSame('ivory', $skins->skins()[0]->key);
    }

    public function testErasingAMemberForgetsTheirChoices(): void
    {
        $store = new InMemorySkinChoiceStore();
        $skins = $this->skins($store);
        $skins->choose($this->member(), 'ember');
        $skins->choose($this->member(), 'gold', 20);
        $skins->choose($this->member('2'), 'gold');

        self::assertSame(2, $skins->eraseMember($this->member()));
        self::assertSame([], $skins->choices($this->member()));
        self::assertCount(1, $skins->choices($this->member('2')));
    }

    public function testAnOwnedDieHasAnItemANameAndSides(): void
    {
        $die = new OwnedDie('gold-d20', 'Gold d20', 20, Color::fromHex('#d4a017'), 'metal', 'copy-9');

        self::assertSame(['gold-d20', 'Gold d20', 20, 'copy-9'], [$die->item, $die->name, $die->sides, $die->copy]);
        self::assertSame(['face' => '#d4a017', 'ink' => '#000000', 'material' => 'metal'], $die->look()?->toArray());

        foreach ([['', 'Name'], [' ', 'Name'], ['item', ''], ['item', ' ']] as [$item, $name]) {
            try {
                new OwnedDie($item, $name, 6);
                self::fail('An owned die has an item and a name.');
            } catch (InvalidSkin $invalid) {
                self::assertSame('A die a member owns has an item and a name.', $invalid->getMessage());
            }
        }

        $this->expectExceptionMessageIsOrContains('A die has 2 to 1000 sides; not 1.');
        new OwnedDie('item', 'Name', 1);
    }

    private function skins(InMemorySkinChoiceStore $store = new InMemorySkinChoiceStore(), ?FixedOwnedDice $owned = null): DiceSkins
    {
        $catalog = FixedSkinCatalog::withBase(self::SITE_SKINS);

        return $owned instanceof FixedOwnedDice ? new DiceSkins($catalog, $store, $owned) : new DiceSkins($catalog, $store);
    }

    private function member(string $id = '1'): ResourceReference
    {
        return ResourceReference::of('member', $id);
    }

    public function testTheSkinsOfTheCatalogAreListed(): void
    {
        self::assertSame(['ivory', 'obsidian', 'crimson', 'forest', 'ocean', 'amethyst', 'gold', 'high-contrast', 'ember', 'fog'], array_map(static fn (Skin $skin): string => $skin->key, $this->skins()->skins()));
    }
}
