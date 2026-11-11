# Erasure

`$progression->erase($member)` removes:

- the gains whose subject is the member, and the gains the member's gestures brought to an object — the experience a
  character earned from their sessions —;
- the milestones they reached, and those their gestures reached for an object;
- their streak gestures, frozen units and states.

It returns an `Erasure` with the three counts. The journal of activity is erased by `tilttek/activity`; an object's
experience earned from other members' gestures stays. `tilttek/progression-bundle` registers this erasure as a module of
`phil/privacy-bundle`.
