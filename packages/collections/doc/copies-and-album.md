# Copies and the album

## A copy per key

A member owns copies of items: two copies of an item make a double. `Collections::grant(member, item, source, key)`
gives one, under a key from what makes it unique — `order:2026-0193:line:2`, `quest:first-steps:member:44` —: the
same key given again returns the copy it gave, so that a task run twice or a message delivered twice gives one copy.
The copy's identifier, 32 hexadecimal digits, derives from the key; outside the library a copy is known as
`collection-copy:<identifier>`. A key that gave a copy of another item, or to another member who still has it, is
refused.

An application key is 1 to 200 bytes, without whitespace, control characters or `#`. The keys the library derives
carry `#`: `<key>#<item>` for a set given whole, `<key>#<set>#<item>` on arrival, `<key>#<place>` for a pack, and
`#daily/…` for the item of the day.

## Sources

Every copy remembers how it came to its first member: `welcome`, `daily`, `pack`, `reward`, `exchange`, `purchase`,
`gift` or `granted`. A trade changes its owner, never its source, and marks it as having changed hands.

## Bound copies

A copy given with `tradable: false` stays with its member — an item earned by a quest, for instance —; the copies of
an item the catalog marks as not tradable are always bound.

## Sets given whole, base sets

`grantSet(member, set, source, key)` gives every item of a set — an earned set once `tilttek/exchange` confirms the
reservation, a paid set once the site's commerce confirms the purchase or the gift. `welcome(member, key)` gives every
`base` set to a member arriving on the site. A purchase never converts points into money: the sale is the site's.

## The album

`album(member)` returns an `Album`: a `Holding` per item of the catalog — the copies the member owns, none for an item
still to find, and its doubles — and a `SetProgress` per set: the items owned and missing, `total()` — *12 of 20* —,
`isComplete()`, and whether the member completed it once. `owned()` and `doubles()` filter the holdings.

## Sets completed

When a copy given or moved to a member completes a set, the set is written as completed — once per set and member —
and the `CollectionObserver` is told `setCompleted(SetCompleted)`, with the set and its rewards, for the application to
give through the packages it has. Trading an item away and getting it back tells nothing more. The observer is also
told every copy given.

## Moves

`move(list<CopyMove>)` moves copies, each from the member who gives it to the member who gets it, all of them or none:
every copy is checked before any moves, and each move names the owner it expects, so that a copy traded meanwhile
refuses the whole move. Run it in a transaction of the store when two processes may move the same copies.
`ownerOf(copy)` and `isTradable(copy)` answer what a trade asks; `tilttek/collections-bundle` turns them into the
inventory of `tilttek/exchange`.

## Copies taken back

`takeBack(member, key)` removes the copy a key gave — a purchase refunded — if it is still with its member and never
changed hands.
