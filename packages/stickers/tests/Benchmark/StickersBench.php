<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Tests\Benchmark;

use Phil\Resource\ResourceReference;
use Tilttek\Stickers\Limits;
use Tilttek\Stickers\PlacementRequest;
use Tilttek\Stickers\Policy\InMemorySurfacePolicy;
use Tilttek\Stickers\Sticker;
use Tilttek\Stickers\Stickers;
use Tilttek\Stickers\Store\InMemoryOwnershipStore;
use Tilttek\Stickers\Store\InMemoryPlacementStore;
use Tilttek\Stickers\Store\InMemoryStickerCatalog;

/**
 * What stickers cost on a page: the fullest surface a site allows — 100 placements — read for a viewer, and a
 * placement request read from the JSON of a sticker editor.
 */
final readonly class StickersBench
{
    private Stickers $stickers;

    private ResourceReference $profile;

    public function __construct()
    {
        $holder = ResourceReference::of('member', '1');
        $this->profile = ResourceReference::of('profile', '1');
        $surfaces = new InMemorySurfacePolicy();
        $surfaces->assign($this->profile, $holder);

        $catalog = new InMemoryStickerCatalog(array_map(static fn (int $sticker): Sticker => new Sticker('s'.$sticker, 's'.$sticker.'.webp', 'Sticker '.$sticker), range(1, 50)));
        $this->stickers = new Stickers($catalog, new InMemoryOwnershipStore(), new InMemoryPlacementStore(), $surfaces, new Limits(perSurface: 100));

        for ($placement = 0; $placement < 100; ++$placement) {
            $code = 's'.(1 + $placement % 50);
            $this->stickers->grant($holder, $code);
            $this->stickers->place($holder, PlacementRequest::fromArray(['sticker' => $code, 'surface' => 'profile:1', 'x' => $placement * 10, 'y' => 500]));
        }
    }

    /**
     * @Revs(100)
     *
     * @Iterations(3)
     */
    public function benchShowASurfaceOf100Placements(): void
    {
        $this->stickers->onSurface($this->profile);
    }

    /**
     * @Revs(1000)
     *
     * @Iterations(3)
     */
    public function benchReadAPlacementRequest(): void
    {
        PlacementRequest::fromJson('{"sticker":"s1","surface":"message:9f","x":500,"y":250,"scale":120,"rotation":-15}');
    }
}
