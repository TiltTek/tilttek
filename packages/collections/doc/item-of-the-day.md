# The item of the day

One item a day, which a member gets by coming by that day.

## The calendar

`DailyCalendar` computes the item of each day, written `Y-m-d` in its timezone:

1. the item kept for the day — chosen by the site, or generated ahead;
2. else the item of the first rule that speaks of the day: a `FixedDate`, every year on a month and a day (`10-31`)
   or once on a full date (`2026-12-25`), or a `DateRule` of the application's own — `tilttek/collections-bundle`
   provides one on `phil/almanac`, for Easter or the fourth Thursday of November;
3. else a draw weighted by rarity among the items of the calendar's sets — every item when none is named —, seeded by
   the SHA-256 of the calendar's salt and the day: the same day always gives the same item.

`pick(day)` reads the calendar, `compute(day)` what the rules and the draw give whatever is kept, and `dayOf(moment)`
the day a moment falls on.

## Generating and correcting

`generate(from, until, regenerate)` writes the computed items of up to 3,660 days to the `DailyStore` and returns
them, so that the site can review them: a day already generated keeps its item unless `regenerate` is true, a day the
site chose always does. `choose(day, item)` puts an item on a day; `release(day)` forgets what is kept for it.

## Claiming

`Daily::claim(member, moment)` gives the member the item of the day the moment falls on, once per member and day,
under a key derived from the day and the member; `today()` reads it. `takeBack(member, moment)` takes it back if the
member still has it and never traded it.

## The projection

`DailyProjection` is a projection of `tilttek/activity`: the first activity of a member on a day among the verbs the
site names — `page.viewed`, `member.signed_in` — gives the item of that day, at the moment of the activity. The
cancellation of that activity takes the item back unless it was traded; another activity of the same day gives it
again on a rebuild. Before a rebuild the items of the day given in the period and never traded are forgotten, then
given again as the activity is replayed, with the calendar as it is now; a traded one stays with its new owner.
