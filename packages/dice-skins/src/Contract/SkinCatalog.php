<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Contract;

use Tilttek\DiceSkins\Skin;

/**
 * The skins a site offers its members.
 */
interface SkinCatalog
{
    /**
     * @return list<Skin>
     */
    public function skins(): array;

    /**
     * The skin of this key, or null when the site offers none.
     */
    public function skin(string $key): ?Skin;
}
