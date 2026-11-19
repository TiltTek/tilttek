# Privacy

## What is kept of a member

For each box a member opens, the bundle keeps the calendar, the member's reference, the day and the moment of the first
opening. The reference is the application's stable identifier, `member:44`: never a name or an e-mail address, and the
bundle refuses one whose identifier holds `@`. Nothing is kept of a visitor. No ranking of who opened first is made.

What the surprises granted — points, badges, codes — lives in the application, which exports and erases it with the
rest of the member's data.

## Export and erasure

With `phil/privacy-bundle` installed, `AdventCalendarPrivacy` takes part in a member's access and erasure requests,
under the module `tilttek_advent_calendar`. The data subject stands for the reference of the kind
`privacy.member_kind`, `member` by default.

- The export holds the boxes the member opened, in every calendar — those of a calendar since removed too — with the
  moment of each.
- The erasure deletes those openings. The member may open the boxes again while their windows last, and the
  application is told again by `BoxOpened`: an application that keeps its grants keyed by `idempotencyKey()` grants
  nothing twice.

## Worker mode

No service keeps a member's data from one request to the next: the first openings kept for the celebration and the
openings in memory are emptied after each request.
