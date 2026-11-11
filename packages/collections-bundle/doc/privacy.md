# Privacy

When `phil/privacy-bundle` is installed, the bundle registers `CollectionsPrivacy`, the module `tilttek_collections`.
The data subject is a member, referenced under the kind `privacy.member_kind` names (`member` by default).

- **Export:** the member's copies, completed sets, opened packs with their seeds and client seeds, and chosen dice; nothing when there
  is none.
- **Erasure:** all of it, reported as deleted from `tilttek_collections_copy`, `tilttek_collections_completion`,
  `tilttek_collections_opening` and `tilttek_collections_choice`. The copies the member traded away belong to other
  members and stay theirs, without a trace of where they came from.
