<?php

declare(strict_types=1);

namespace Tilttek\DiceSkins\Store;

use Override;
use Tilttek\DiceSkins\BaseSkins;
use Tilttek\DiceSkins\Contract\SkinCatalog;
use Tilttek\DiceSkins\Exception\InvalidSkin;
use Tilttek\DiceSkins\Skin;
use Tilttek\DiceSkins\SkinDefinitions;

/**
 * The skins given once, in their order: the base skins, the site's own, or both.
 */
final readonly class FixedSkinCatalog implements SkinCatalog
{
    /** @var array<string, Skin> */
    private array $skins;

    /**
     * @param list<Skin> $skins
     *
     * @throws InvalidSkin when two skins have the same key
     */
    public function __construct(array $skins)
    {
        $byKey = [];

        foreach ($skins as $skin) {
            if (isset($byKey[$skin->key])) {
                throw InvalidSkin::because(\sprintf('Two skins have the key "%s".', $skin->key));
            }

            $byKey[$skin->key] = $skin;
        }

        $this->skins = $byKey;
    }

    /**
     * The base skins, followed by the site's own read from JSON.
     *
     * @throws InvalidSkin when the definitions do not read, or reuse the key of a base skin
     */
    public static function withBase(string $json = '[]'): self
    {
        return new self([...BaseSkins::all(), ...SkinDefinitions::fromJson($json)]);
    }

    #[Override]
    public function skins(): array
    {
        return array_values($this->skins);
    }

    #[Override]
    public function skin(string $key): ?Skin
    {
        return $this->skins[$key] ?? null;
    }
}
