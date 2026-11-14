<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins;

use Phil\Resource\ResourceReference;
use Tilttek\DiceSkins\Contract\OwnedDice;
use Tilttek\DiceSkins\Contract\SkinCatalog;
use Tilttek\DiceSkins\Contract\SkinChoiceStore;
use Tilttek\DiceSkins\Exception\InvalidSkin;
use Tilttek\DiceSkins\Exception\SkinRefused;
use Tilttek\DiceSkins\Store\NoOwnedDice;

/**
 * The appearance of a member's dice: the skins they choose, for all their dice or for one number of sides, and the
 * appearance each die is shown with in the roller.
 *
 * The appearance of a die is the first of: the die of a set the member owns and chose for its number of sides — its
 * colour with the black or white ink that stands out most —, the skin they chose for that number of sides, the skin
 * they chose for all their dice, and the site's default skin. A skin the catalog no longer offers is passed over. For
 * a viewer who asks for high contrast, the numbers are kept at 7:1 at least, on solid faces without texture.
 *
 * The service keeps nothing between two calls: one instance serves every request of a worker.
 */
final readonly class DiceSkins
{
    public function __construct(
        private SkinCatalog $catalog,
        private SkinChoiceStore $choices,
        private OwnedDice $owned = new NoOwnedDice(),
        private string $default = BaseSkins::DEFAULT,
    ) {
    }

    /**
     * The skins the site offers.
     *
     * @return list<Skin>
     */
    public function skins(): array
    {
        return $this->catalog->skins();
    }

    /**
     * Chooses a skin for all the member's dice, or, with `$sides`, for the dice of that number of sides.
     *
     * @throws SkinRefused when the catalog does not offer the skin
     * @throws InvalidSkin when a die cannot have that number of sides
     */
    public function choose(ResourceReference $member, string $skin, ?int $sides = null): SkinChoice
    {
        if (null !== $sides) {
            Skin::sides($sides);
        }

        if (!$this->catalog->skin($skin) instanceof Skin) {
            throw SkinRefused::unknownSkin($skin);
        }

        $choice = new SkinChoice($member, $skin, $sides);
        $this->choices->save($choice);

        return $choice;
    }

    /**
     * Forgets the member's skin for all their dice, or for one number of sides; returns whether there was one.
     */
    public function clear(ResourceReference $member, ?int $sides = null): bool
    {
        return $this->choices->remove($member, $sides);
    }

    /**
     * The member's choices: the one for all their dice first, then by number of sides.
     *
     * @return list<SkinChoice>
     */
    public function choices(ResourceReference $member): array
    {
        return $this->choices->ofMember($member);
    }

    /**
     * The dice of the sets the member owns, among which they choose on their shelf.
     *
     * @return list<OwnedDie>
     */
    public function owned(ResourceReference $member): array
    {
        return $this->owned->ownedBy($member);
    }

    /**
     * The appearance a die of the member is shown with.
     *
     * @throws InvalidSkin when a die cannot have that number of sides
     * @throws SkinRefused when the appearance falls back to a default skin the catalog does not offer
     */
    public function appearance(ResourceReference $member, int $sides, Accessibility $accessibility = Accessibility::Standard): DieAppearance
    {
        $appearance = $this->dressed($member, Skin::sides($sides));

        return Accessibility::HighContrast === $accessibility ? $this->inHighContrast($appearance) : $appearance;
    }

    /**
     * Forgets every choice of the member; returns how many. The dice they own stay with their collection.
     */
    public function eraseMember(ResourceReference $member): int
    {
        return $this->choices->eraseMember($member);
    }

    private function dressed(ResourceReference $member, int $sides): DieAppearance
    {
        $owned = $this->owned->chosen($member, $sides);

        if (!$owned instanceof OwnedDie) {
            return $this->skinned($member, $sides);
        }

        $look = $owned->look();

        if ($look instanceof SkinLook) {
            return new DieAppearance($sides, $owned->name, $look, AppearanceSource::Owned, item: $owned->item);
        }

        // A die its collection gives no colour takes the look the member's dice would have otherwise.
        $skinned = $this->skinned($member, $sides);

        return new DieAppearance($sides, $owned->name, $skinned->look, AppearanceSource::Owned, $skinned->skin, $owned->item);
    }

    private function skinned(ResourceReference $member, int $sides): DieAppearance
    {
        foreach ([AppearanceSource::ChosenForSides->value => $sides, AppearanceSource::ChosenForAll->value => null] as $source => $slot) {
            $choice = $this->choices->find($member, $slot);
            $skin = $choice instanceof SkinChoice ? $this->catalog->skin($choice->skin) : null;

            if ($skin instanceof Skin) {
                return new DieAppearance($sides, $skin->label, $skin->lookFor($sides), AppearanceSource::from($source), $skin->key);
            }
        }

        $skin = $this->catalog->skin($this->default) ?? throw SkinRefused::unknownSkin($this->default);

        return new DieAppearance($sides, $skin->label, $skin->lookFor($sides), AppearanceSource::Default, $skin->key);
    }

    private function inHighContrast(DieAppearance $appearance): DieAppearance
    {
        $look = $appearance->look;
        $face = $look->face;
        $ink = $look->reaches(Color::AAA) ? $look->ink : $face->bestInk();

        if ($face->contrastWith($ink) < Color::AAA) {
            $fallback = BaseSkins::highContrast()->look;
            [$face, $ink] = [$fallback->face, $fallback->ink];
        }

        return new DieAppearance(
            $appearance->sides,
            $appearance->name,
            new SkinLook($face, $ink),
            $appearance->source,
            $appearance->skin,
            $appearance->item,
            true,
        );
    }
}
