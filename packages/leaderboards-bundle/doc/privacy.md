# Privacy

When `phil/privacy-bundle` is installed, the bundle registers `LeaderboardsPrivacy`, module `tilttek_leaderboards`.
The data subject is a member, referenced as `<member_kind>:<subject>` — `member:44` by default.

**The export** holds the member's choice of appearance, their scores by board and period, their lines in the
snapshots, their tiers and memberships in the leagues, and the leads they took; it is empty for a member who is hidden
and holds nothing.

**The erasure** deletes the member's contributions and scores, their lines in every snapshot, their choice, their
tiers, their memberships and their leads. The report names the seven tables, each `Deleted`. The other members keep their ranks in the
snapshots and their divisions; the current standings move up.

Nobody appears on a board without choosing it — the library's `doc/appearance.md` says what each viewer sees —: the
application asks its members, in their profile, how they want to appear. The bundle keeps to that choice everywhere it
shows a member: the templates write "A member" for a hidden one, `tilttek:leaderboards:show` a dash, the celebration
speaks to the member alone, and `TopReached` carries the member's choice so that a listener telling the others names
them only as they chose ([Events](events.md)).
