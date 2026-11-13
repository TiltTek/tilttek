<?php

declare(strict_types=1);

namespace Tilttek\Stickers\Store;

use Override;
use Tilttek\Stickers\Contract\StickerCatalog;
use Tilttek\Stickers\Exception\InvalidSticker;
use Tilttek\Stickers\Sticker;

/**
 * A catalogue of stickers given whole, from the application's configuration or for tests.
 */
final readonly class InMemoryStickerCatalog implements StickerCatalog
{
    /** @var array<string, Sticker> */
    private array $stickers;

    /**
     * @param list<Sticker> $stickers
     *
     * @throws InvalidSticker when two stickers share a code
     */
    public function __construct(array $stickers)
    {
        $byCode = [];

        foreach ($stickers as $sticker) {
            if (isset($byCode[$sticker->code])) {
                throw InvalidSticker::because(\sprintf('The catalogue names the sticker "%s" twice.', $sticker->code));
            }

            $byCode[$sticker->code] = $sticker;
        }

        $this->stickers = $byCode;
    }

    #[Override]
    public function get(string $code): ?Sticker
    {
        return $this->stickers[$code] ?? null;
    }

    #[Override]
    public function all(): array
    {
        return array_values($this->stickers);
    }
}
