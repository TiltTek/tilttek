# Privacy

With `phil/privacy-bundle` installed, `ProgressionPrivacy` is registered as an exporter and an eraser under the module
`tilttek_progression`. The data subject is a member, referenced under `privacy.member_kind` (`member`).

**The export** lists the member's gains (track, rule, subject, member, activity, amount, moment, season), the milestones
they reached and their streaks with a run ever — the current and best runs —; it is empty when there is none.

**The erasure** deletes the member's gains and those their gestures brought to an object, their milestones, and their
streak gestures, frozen units and states; the report names the five tables as deleted. Their journal of activity is
erased by `tilttek/activity-bundle`'s own module.
