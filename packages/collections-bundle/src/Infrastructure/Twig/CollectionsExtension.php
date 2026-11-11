<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Twig;

use Phil\Resource\ResourceReference;
use Tilttek\Collections\Album;
use Tilttek\Collections\Collections;
use Tilttek\Collections\Daily\Daily;
use Tilttek\Collections\Daily\DailyPick;
use Tilttek\Collections\Item;
use Tilttek\Collections\Odds;
use Tilttek\Collections\Pack\Packs;
use Tilttek\Collections\Texts;
use Twig\Attribute\AsTwigFunction;

/**
 * The collections in the application's templates: `tilttek_collections_album('member:44')` for the album,
 * `tilttek_collections_today()` for the item of the day, `tilttek_collections_item('pirate')` for an item, and
 * `tilttek_collections_odds('booster')` for the odds a pack publishes, and `tilttek_collections_text(item.name, 'fr')`
 * for a name or a description in a locale.
 */
final readonly class CollectionsExtension
{
    public function __construct(
        private Collections $collections,
        private Packs $packs,
        private Daily $daily,
        private string $domain,
    ) {
    }

    /**
     * The translation domain of the templates' texts.
     */
    #[AsTwigFunction('tilttek_collections_domain')]
    public function domain(): string
    {
        return $this->domain;
    }

    #[AsTwigFunction('tilttek_collections_album')]
    public function album(string $member): Album
    {
        return $this->collections->album(ResourceReference::fromString($member));
    }

    #[AsTwigFunction('tilttek_collections_today')]
    public function today(): DailyPick
    {
        return $this->daily->today();
    }

    #[AsTwigFunction('tilttek_collections_item')]
    public function item(string $code): Item
    {
        return $this->collections->catalog->catalog()->item($code);
    }

    #[AsTwigFunction('tilttek_collections_text')]
    public function text(Texts $texts, string $locale): string
    {
        return $texts->in($locale);
    }

    #[AsTwigFunction('tilttek_collections_odds')]
    public function odds(string $pack): Odds
    {
        return $this->packs->odds($pack);
    }
}
