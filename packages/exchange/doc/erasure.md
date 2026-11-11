# Erasure

`ExchangeErasure::erase($member)` erases a member's exchanges:

- their **reservations** are deleted;
- their **open offers** are withdrawn, which frees the items they held;
- `erased:member` takes their place in the **offers** and the **transfers** they had with other members, which stay
  those members' trades and points.

It returns an `Erasure`: the reservations deleted, the transfers and the offers changed. It erases from the stores it is
given: an application without transfers or trades gives only the reservations. The points themselves — the spendings
of the reservations, the transfers of the ledger — are erased by `tilttek/points`.
