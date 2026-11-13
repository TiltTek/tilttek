<?php

declare(strict_types=1);

namespace Tilttek\SeasonalEvents\Bridge\Collections;

use DateTimeImmutable;
use Tilttek\Collections\CatalogProvider;
use Tilttek\Collections\Item;
use Tilttek\SeasonalEvents\ContentKind;
use Tilttek\SeasonalEvents\Contract\ActiveContent;

/**
 * The items of a `tilttek/collections` catalog that the seasons bring at a moment: each item reference of the kind
 * given, `item:snow-hat`, names the item of that code; a reference the catalog does not hold is left out. Used only
 * when `tilttek/collections` is installed.
 */
final readonly class SeasonalItems
{
    public function __construct(
        private ActiveContent $seasons,
        private CatalogProvider $catalog,
        private string $kind = 'item',
    ) {
    }

    /**
     * @return list<Item>
     */
    public function at(?DateTimeImmutable $moment = null): array
    {
        $items = [];

        foreach ($this->catalog->catalog()->items() as $item) {
            $items[$item->code] = $item;
        }

        $active = [];

        foreach ($this->seasons->active(ContentKind::Item, $moment) as $reference) {
            if ($this->kind === $reference->key && isset($items[$reference->id])) {
                $active[] = $items[$reference->id];
            }
        }

        return $active;
    }
}
