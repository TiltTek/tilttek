# Extension points

## Trades through `tilttek/exchange`

When `tilttek/exchange` is installed, the bundle registers `CollectionsInventory`, the copies as the inventory a trade
moves: an item of an offer is a copy, referenced as `collection-copy:<identifier>` (`Copy::reference()`). Name it in
the exchange's configuration, and keep both bundles on the same connection so that a trade moves the copies and the
points in one transaction:

```yaml
tilttek_exchange:
    trades:
        enabled: true
        inventory: Tilttek\CollectionsBundle\Infrastructure\Exchange\CollectionsInventory
```

Only tradable copies change hands; a bound copy, or an item the catalog marks as not tradable, is refused by the
exchange.

## Dice for `tilttek/dice-bundle`

When `tilttek/dice-bundle` is installed, the bundle registers `CollectionDiceAppearance`: for a member and a number of
sides, the die they chose in their collection — its name in the configured `locale`, colour, material and image —,
or the plain dice. Name it in the dice bundle's configuration:

```yaml
tilttek_dice:
    appearance: Tilttek\CollectionsBundle\Infrastructure\Dice\CollectionDiceAppearance
```

## Seeds

Packs draw their committed seeds from `Tilttek\Collections\Pack\SeedSource`, aliased to `SecureSeeds`. Replace the
alias with a source of your own, such as a hardware generator; the member's client seed is always given to
`Packs::open()` after the commitment, never drawn here.

## The catalog

`Tilttek\Collections\CatalogProvider` is the catalog every service reads. With `catalog: doctrine` it is
`DbalCatalog`, also the `WritableCatalog`; replace the alias to serve a catalog from anywhere else.

## Rewards

A set completed is dispatched as the library's `SetCompleted`, with the rewards the set declares: listen to it and
give them through `tilttek/points`, `tilttek/badges` or anything else ([Events](events.md)).
