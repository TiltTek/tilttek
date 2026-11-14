<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Tests\Benchmark;

use Phil\Resource\ResourceReference;
use Tilttek\DiceSkins\Accessibility;
use Tilttek\DiceSkins\BaseSkins;
use Tilttek\DiceSkins\DiceSkins;
use Tilttek\DiceSkins\SkinDefinitions;
use Tilttek\DiceSkins\Store\FixedSkinCatalog;
use Tilttek\DiceSkins\Store\InMemorySkinChoiceStore;

/**
 * What dressing the dice costs: the appearance of one die — every die of every roll shown —, in high contrast, and the
 * reading of a catalog of 256 skins with a d20 of their own.
 */
final readonly class DiceSkinsBench
{
    private DiceSkins $skins;

    private ResourceReference $member;

    private string $catalog;

    public function __construct()
    {
        $this->skins = new DiceSkins(FixedSkinCatalog::withBase(), new InMemorySkinChoiceStore());
        $this->member = ResourceReference::of('member', '1');
        $this->skins->choose($this->member, 'crimson');
        $this->skins->choose($this->member, 'gold', 20);

        $definitions = [];

        for ($index = 0; $index < SkinDefinitions::MAX_SKINS; ++$index) {
            $definitions[] = ['key' => 'skin-'.$index, 'label' => 'Skin '.$index, 'face' => '#1b1b1f', 'ink' => '#f5f5f5', 'types' => ['20' => ['face' => '#9b1b30', 'ink' => '#ffffff']]];
        }

        $this->catalog = json_encode($definitions, \JSON_THROW_ON_ERROR);
    }

    public function benchAppearance(): void
    {
        $this->skins->appearance($this->member, 20);
    }

    public function benchAppearanceInHighContrast(): void
    {
        $this->skins->appearance($this->member, 6, Accessibility::HighContrast);
    }

    public function benchReadACatalogOf256Skins(): void
    {
        $skins = SkinDefinitions::fromJson($this->catalog);

        \assert(SkinDefinitions::MAX_SKINS === \count($skins) && BaseSkins::DEFAULT !== $skins[0]->key);
    }
}
