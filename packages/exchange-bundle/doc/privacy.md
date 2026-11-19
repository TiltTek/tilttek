# Privacy

When `phil/privacy-bundle` is installed, `ExchangePrivacy` answers its access and erasure requests under the module
`tilttek_exchange`. A data subject `44` is the member `member:44`, or of the kind `privacy.member_kind` names.

- **Export**: the member's reservations, and — when enabled — the transfers they gave or received and the offers they
  made or were made, as plain values; a part with nothing is left out.
- **Erasure**: the reservations are deleted; the open offers are withdrawn, freeing their items; `erased:member` takes
  the member's place in the transfers and the offers they had with other members, which stay those members'. The
  report names `tilttek_exchange_reservation` as deleted and the member columns of the transfers and the offers as
  redacted.

The points — the spendings of the reservations, the transfers of the ledger — are erased by the module of
`tilttek/points-bundle`.
