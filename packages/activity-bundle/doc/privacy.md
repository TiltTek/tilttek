# Privacy

When `phil/privacy-bundle` is installed, `ActivityPrivacy` is registered as its exporter and its eraser, module
`tilttek_activity`. The data subject is a member: the subject `44` is the reference `member:44`, the kind coming from
`tilttek_activity.privacy.member_kind`.

- **The export** lists every entry of the member's gestures — position, kind, when it was written, and the activity:
  key, verb, moment, object, quantity, context —, read in batches of `batch_size`. A member with no activity exports
  nothing.
- **The erasure** deletes the entries of the member's gestures and forgets the member as the object of other members'
  gestures, which stay. Its report names `tilttek_activity_entry` (deleted) and `tilttek_activity_entry.object`
  (redacted). It can run again on an erased member.

What other packages derived from the activity — points, badges, progress — is erased by their own erasers.
