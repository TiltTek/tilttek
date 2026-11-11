# Commands

| Command | What it does |
|---|---|
| `tilttek:badges:award member:44 organiser --reason="Ran the convention"` | Awards a badge by hand; a badge that is not repeatable and is already held is not awarded again |
| `tilttek:badges:revoke member:44 organiser` | Withdraws the awards of the badge made by hand |
| `tilttek:badges:list member:44` | Lists where the member stands with every badge: awards and progress; hidden badges not earned are left out |

A member reference or a badge code that is not one ends the command with an error and a non-zero status.
