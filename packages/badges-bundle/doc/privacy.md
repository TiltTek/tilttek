# Privacy

When `phil/privacy-bundle` is installed, the bundle registers the module `tilttek_badges`, both exporter and eraser.
The data subject 44 is the member `member:44` — the kind is `privacy.member_kind`.

**The export** lists the member's awards, the badges they show, their showcase, their title and the applications they
allowed to read their badges; nothing when the member has none of them.

**The erasure** deletes the member's awards, the contributions of their activity, their profile and their consents:

| Table | Effect |
|---|---|
| `tilttek_badges_award` | Deleted |
| `tilttek_badges_contribution` | Deleted |
| `tilttek_badges_profile` | Deleted |
| `tilttek_badges_grant` | Deleted |

The activity itself is erased by `tilttek/activity-bundle`'s own module.
