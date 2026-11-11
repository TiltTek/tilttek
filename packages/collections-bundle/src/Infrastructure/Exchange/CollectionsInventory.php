<?php

declare(strict_types=1);

namespace Tilttek\CollectionsBundle\Infrastructure\Exchange;

use Override;
use Phil\Resource\ResourceReference;
use Tilttek\Collections\Collections;
use Tilttek\Collections\Copy;
use Tilttek\Collections\CopyMove;
use Tilttek\Collections\Exception\CollectionRefused;
use Tilttek\Exchange\Exception\ExchangeRefused;
use Tilttek\Exchange\Refusal;
use Tilttek\Exchange\Trade\Inventory;

/**
 * The members' copies as the inventory `tilttek/exchange` trades through: an item of a trade is a copy, referenced as
 * `collection-copy:<identifier>`; a reference of another kind belongs to no one and never changes hands. Name this
 * service in `tilttek_exchange.trades.inventory`, and keep both bundles on the same connection, so that a trade moves
 * the copies and the points in one transaction.
 */
final readonly class CollectionsInventory implements Inventory
{
    public function __construct(
        private Collections $collections,
    ) {
    }

    #[Override]
    public function ownerOf(ResourceReference $item): ?ResourceReference
    {
        return Copy::KIND === $item->key ? $this->collections->ownerOf($item->id) : null;
    }

    #[Override]
    public function isTradable(ResourceReference $item): bool
    {
        return Copy::KIND === $item->key && $this->collections->isTradable($item->id);
    }

    #[Override]
    public function move(array $moves): void
    {
        $copies = [];

        foreach ($moves as $move) {
            if (Copy::KIND !== $move->item->key) {
                throw ExchangeRefused::because(Refusal::NotOwned, \sprintf('"%s" is not a copy of a collection.', $move->item->toString()));
            }

            $copies[] = new CopyMove($move->item->id, $move->from, $move->to);
        }

        try {
            $this->collections->move($copies);
        } catch (CollectionRefused $collectionRefused) {
            throw ExchangeRefused::because(Refusal::NotOwned, $collectionRefused->getMessage());
        }
    }
}
