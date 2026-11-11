# Writing a store

An application that keeps its exchanges elsewhere than in memory or in the bundle's tables implements the stores. Each
contract is also a test case in the library's tests — `ReservationStoreTestCase`, `TransferLogTestCase`,
`OfferStoreTestCase` — that a store's test extends.

## `ReservationStore`

- `add()` writes a reservation once per key — false when the key is there — and checks the units of the reward's
  reserved and confirmed reservations against the stock and the member's limit **in the same step**, so that two
  members never get the last unit: under a lock, or in one statement.
- `settle()` moves a reservation out of `reserved` only while it is reserved, and says whether this call did.
- `discard()` deletes a reservation still reserved; `held()`, `due()`, `ofMember()` and `eraseMember()` read and erase.

## `TransferLog`

`record()` writes a transfer once per key; `given()` sums what a member gave in a currency over a period; `ofMember()`,
`latest()` and `eraseMember()` read and erase.

## `OfferStore`

- `open()` writes an offer once per key — false when the key is there — and holds its offered items **in the same
  step**, refusing with `Refusal::AlreadyHeld` an item another open offer holds.
- `close()` moves an offer out of `open` only while it is open, releases its items, and says whether this call did.
- `holder()`, `made()`, `due()`, `ofMember()` and `eraseMember()` read and erase.

## `Transaction` and `Inventory`

A `Transaction` runs the steps of an acceptance or a counter-offer as one unit; with a database, one of its
transactions. An `Inventory` tells an item's owner and whether it may change hands, and moves the items of an accepted
trade all together, refusing with `Refusal::NotOwned` an item that no longer belongs to its giver.
