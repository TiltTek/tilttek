# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Say who views a calendar | Implement `MemberResolver` and name it in `member_resolver` |
| Grant the surprises | Listen to `Tilttek\AdventCalendar\Event\BoxOpened` |
| Take the calendars from elsewhere | Implement `CalendarCatalogue` and alias it, or decorate `Calendars` |
| Keep the openings elsewhere | Alias `Tilttek\AdventCalendar\Contract\OpeningStore` and `MemberOpenings` to your service |
| Change the screen | Override a template under `templates/bundles/TilttekAdventCalendarBundle/` |
| Show the surprises in your own words | Override `_surprise.html.twig` |
| Change the texts | Override the translations of the domain `tilttek_advent_calendar` |
| Edit the calendars in your back office | Use `CalendarType` on a `CalendarRecord` |
