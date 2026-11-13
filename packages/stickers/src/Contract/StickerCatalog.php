<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Contract;

use Tilttek\Stickers\Sticker;

/**
 * The stickers of the site, from its configuration or its database.
 */
interface StickerCatalog
{
    public function get(string $code): ?Sticker;

    /**
     * Every sticker of the catalogue, retired ones included, in the catalogue's order.
     *
     * @return list<Sticker>
     */
    public function all(): array;
}
