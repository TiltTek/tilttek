# Privacy

When `phil/privacy-bundle` is installed, the bundle registers `PointsPrivacy` as an exporter and an eraser, module
`tilttek_points`. A data subject `44` stands for the member `member:44`, or for the kind `privacy.member_kind` names.

- **The export** lists every movement of the member's account, in every currency, as plain values: position, key,
  kind, currency, debit, credit, amount, the moment it counts at, when it was written, its cause and the movement it
  undoes. It is read `batch_size` movements at a time.
- **The erasure** deletes the movements between the member and the ledger's own accounts, writes `erased:member` in
  place of the member in the transfers with other members — whose points do not change —, and drops the member's lock
  rows. The report names `tilttek_points_movement` as deleted and its `debit` and `credit` columns as redacted.

The member's activity is exported and erased by `tilttek/activity-bundle`'s own module. Build the movement keys from
identifiers that say nothing about the member: they stay in the other members' transfers.
