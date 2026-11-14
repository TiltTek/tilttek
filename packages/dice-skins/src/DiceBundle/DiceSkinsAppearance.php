<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\DiceBundle;

use Phil\Resource\ResourceReference;
use Tilttek\DiceBundle\Application\Accessibility as ViewerAccessibility;
use Tilttek\DiceBundle\Application\DieAppearance as RolledDieAppearance;
use Tilttek\DiceBundle\Application\Port\DiceAppearance;
use Tilttek\DiceSkins\Accessibility;
use Tilttek\DiceSkins\DiceSkins;
use Tilttek\DiceSkins\Exception\SkinRefused;
use Tilttek\DiceSkins\Skin;

/**
 * The appearance `DiceSkins` gives a member's dice, handed to the roller of `tilttek/dice-bundle` through its
 * `DiceAppearance` port: the name of the skin or of the die owned, the colour of the faces, the material, the colour
 * of the numbers and the texture key. A number of sides no die of a skin has — under 2 or over `Skin::MAX_SIDES` —
 * gets no appearance, and the roller shows its plain dice. The viewer's accessibility, when the roller passes it, chooses
 * the standard or the high-contrast look; without it, the adapter gives the look it was built with.
 *
 * The adapter keeps nothing between two calls: one instance serves every request of a worker.
 */
final readonly class DiceSkinsAppearance implements DiceAppearance
{
    public function __construct(
        private DiceSkins $skins,
        private Accessibility $accessibility = Accessibility::Standard,
    ) {
    }

    /**
     * @throws SkinRefused when the appearance falls back to a default skin the catalog does not offer
     */
    public function appearance(ResourceReference $member, int $sides, ?ViewerAccessibility $viewer = null): ?RolledDieAppearance
    {
        if ($sides < 2 || $sides > Skin::MAX_SIDES) {
            return null;
        }

        $accessibility = $viewer instanceof ViewerAccessibility ? Accessibility::from($viewer->value) : $this->accessibility;
        $appearance = $this->skins->appearance($member, $sides, $accessibility);
        $look = $appearance->look;

        return new RolledDieAppearance(
            name: $appearance->name,
            color: $look->face->hex,
            material: $look->material->value,
            ink: $look->ink->hex,
            texture: $look->texture?->key,
        );
    }
}
