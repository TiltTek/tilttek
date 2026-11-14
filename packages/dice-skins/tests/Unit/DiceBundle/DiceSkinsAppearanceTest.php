<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Tests\Unit\DiceBundle;

use Override;
use Phil\Resource\ResourceReference;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Tilttek\DiceBundle\Application\Accessibility as ViewerAccessibility;
use Tilttek\DiceBundle\Application\DieAppearance as RolledDieAppearance;
use Tilttek\DiceBundle\Application\Port\DiceAppearance;
use Tilttek\DiceSkins\Accessibility;
use Tilttek\DiceSkins\Color;
use Tilttek\DiceSkins\DiceBundle\DiceSkinsAppearance;
use Tilttek\DiceSkins\DiceSkins;
use Tilttek\DiceSkins\Exception\SkinRefused;
use Tilttek\DiceSkins\OwnedDie;
use Tilttek\DiceSkins\Store\FixedSkinCatalog;
use Tilttek\DiceSkins\Store\InMemorySkinChoiceStore;
use Tilttek\DiceSkins\Tests\Unit\Fixture\FixedOwnedDice;

#[CoversClass(DiceSkinsAppearance::class)]
final class DiceSkinsAppearanceTest extends TestCase
{
    private const string SITE_SKINS = <<<'JSON'
        [
            {"key": "ember", "label": "Ember", "face": "#9b1b30", "ink": "#ffffff", "material": "pearl", "texture": "pearl/soft"},
            {"key": "dusk", "label": "Dusk", "face": "#2b2b6b", "ink": "#b0b0b0", "material": "marble", "texture": "marble/veins-02"}
        ]
        JSON;

    #[Override]
    protected function setUp(): void
    {
        if (!enum_exists(ViewerAccessibility::class)) {
            self::markTestSkipped("The adapter needs tilttek/dice-bundle 0.2.0-alpha, whose DieAppearance has an ink and a texture and whose port takes the viewer's accessibility; the installed one has neither.");
        }
    }

    public function testTheAdapterIsTheRollersPort(): void
    {
        self::assertInstanceOf(DiceAppearance::class, $this->adapter());
    }

    public function testAMemberWhoChoseNothingRollsTheDefaultSkin(): void
    {
        $appearance = $this->adapter()->appearance($this->member(), 6);
        self::assertInstanceOf(RolledDieAppearance::class, $appearance);

        self::assertSame(['color' => '#f6f1e3', 'image' => null, 'ink' => '#1f1a14', 'material' => 'solid', 'name' => 'Ivory', 'texture' => null], $this->values($appearance));
    }

    public function testTheChosenSkinGivesItsColoursMaterialAndTexture(): void
    {
        $skins = $this->skins();
        $skins->choose($this->member(), 'ember');

        $appearance = new DiceSkinsAppearance($skins)->appearance($this->member(), 8);
        self::assertInstanceOf(RolledDieAppearance::class, $appearance);

        self::assertSame(['color' => '#9b1b30', 'image' => null, 'ink' => '#ffffff', 'material' => 'pearl', 'name' => 'Ember', 'texture' => 'pearl/soft'], $this->values($appearance));
    }

    public function testADieTheMemberOwnsGivesItsNameAndTheInkThatStandsOutMost(): void
    {
        $frost = new OwnedDie('frost-d6', 'Frost d6', 6, Color::fromHex('#5dade2'), 'Glass', 'copy-1');
        $skins = $this->skins(new FixedOwnedDice(chosen: ['member:1/6' => $frost]));

        $appearance = new DiceSkinsAppearance($skins)->appearance($this->member(), 6);
        self::assertInstanceOf(RolledDieAppearance::class, $appearance);

        self::assertSame(['color' => '#5dade2', 'image' => null, 'ink' => '#000000', 'material' => 'translucent', 'name' => 'Frost d6', 'texture' => null], $this->values($appearance));
    }

    public function testInHighContrastTheNumbersReachSevenToOneOnSolidFacesWithoutTexture(): void
    {
        $skins = $this->skins();
        $skins->choose($this->member(), 'dusk');

        $standard = new DiceSkinsAppearance($skins)->appearance($this->member(), 6);
        $high = new DiceSkinsAppearance($skins, Accessibility::HighContrast)->appearance($this->member(), 6);
        self::assertInstanceOf(RolledDieAppearance::class, $standard);

        self::assertSame(['color' => '#2b2b6b', 'image' => null, 'ink' => '#b0b0b0', 'material' => 'marble', 'name' => 'Dusk', 'texture' => 'marble/veins-02'], $this->values($standard));
        self::assertInstanceOf(RolledDieAppearance::class, $high);
        self::assertSame(['color' => '#2b2b6b', 'image' => null, 'ink' => '#ffffff', 'material' => 'solid', 'name' => 'Dusk', 'texture' => null], $this->values($high));
    }

    public function testTheViewersAccessibilityChoosesTheLook(): void
    {
        $skins = $this->skins();
        $skins->choose($this->member(), 'dusk');

        $standard = ['color' => '#2b2b6b', 'image' => null, 'ink' => '#b0b0b0', 'material' => 'marble', 'name' => 'Dusk', 'texture' => 'marble/veins-02'];
        $high = ['color' => '#2b2b6b', 'image' => null, 'ink' => '#ffffff', 'material' => 'solid', 'name' => 'Dusk', 'texture' => null];

        $adapter = new DiceSkinsAppearance($skins);
        $highByDefault = new DiceSkinsAppearance($skins, Accessibility::HighContrast);

        self::assertSame($high, $this->values($adapter->appearance($this->member(), 6, ViewerAccessibility::HighContrast) ?? self::fail('No appearance.')));
        self::assertSame($standard, $this->values($adapter->appearance($this->member(), 6, ViewerAccessibility::Standard) ?? self::fail('No appearance.')));
        self::assertSame($standard, $this->values($highByDefault->appearance($this->member(), 6, ViewerAccessibility::Standard) ?? self::fail('No appearance.')));
        self::assertSame($high, $this->values($highByDefault->appearance($this->member(), 6) ?? self::fail('No appearance.')));
        self::assertNull($adapter->appearance($this->member(), 1, ViewerAccessibility::HighContrast));
    }

    public function testANumberOfSidesNoSkinDresses(): void
    {
        $adapter = $this->adapter();

        self::assertNull($adapter->appearance($this->member(), 1));
        self::assertNull($adapter->appearance($this->member(), 0));
        self::assertNull($adapter->appearance($this->member(), 1001));
        self::assertInstanceOf(RolledDieAppearance::class, $adapter->appearance($this->member(), 2));
        self::assertInstanceOf(RolledDieAppearance::class, $adapter->appearance($this->member(), 1000));
    }

    public function testADefaultSkinTheCatalogDoesNotOfferIsAnError(): void
    {
        $adapter = new DiceSkinsAppearance(new DiceSkins(new FixedSkinCatalog([]), new InMemorySkinChoiceStore()));

        $this->expectException(SkinRefused::class);
        $adapter->appearance($this->member(), 6);
    }

    /**
     * @return array<mixed>
     */
    private function values(RolledDieAppearance $appearance): array
    {
        $values = get_object_vars($appearance);
        ksort($values);

        return $values;
    }

    private function adapter(): DiceSkinsAppearance
    {
        return new DiceSkinsAppearance($this->skins());
    }

    private function skins(?FixedOwnedDice $owned = null): DiceSkins
    {
        return new DiceSkins(FixedSkinCatalog::withBase(self::SITE_SKINS), new InMemorySkinChoiceStore(), $owned ?? new FixedOwnedDice());
    }

    private function member(): ResourceReference
    {
        return ResourceReference::of('member', '1');
    }
}
