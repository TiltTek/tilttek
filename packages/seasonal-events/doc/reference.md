# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `SeasonSchedule` | When a season comes, in its timezone: once, or each year by a yearly rule. |
| `Period` | One time a season comes, with its code, the date of its first day. |
| `Season` | A season: its identifier, schedule, contents and events. |
| `SeasonalEvent` | An event inside a season, with its contents, announcement and multiplier. |
| `Content` | One thing a season or an event brings: a kind and a reference. |
| `ContentKind` | `Quest`, `Item` or `Decoration`. |
| `Multiplier` | A multiplier of the gains of a ledger, in percent, rounded down. |
| `SeasonalEvents` | The questions of a moment: what is on, what is announced, the next time of a season; implements `ActiveContent`. |
| `Snapshot` | What is on at one moment. |
| `ActiveSeason` | A season in the time it is on. |
| `ActiveEvent` | An event in one time of its season, with when it runs and when it is announced. |
| `Days` | The calendar arithmetic of the library: dates as numbers of days, and the start of a day in a timezone. |
| `SeasonReader` | Seasons read from plain arrays and written back. |
| `Contract\SeasonCatalogue` | Where the seasons come from. |
| `Contract\ActiveContent` | The port the other packages read what is on through. |
| `Store\InMemorySeasonCatalogue` | Seasons given once, in code or read from a configuration. |
| `Bridge\Quests\SeasonQuestSchedule` | The schedule of a `tilttek/quests` quest that opens with a season or an event. |
| `Bridge\Collections\SeasonalItems` | The `tilttek/collections` items the seasons bring at a moment. |
| `Exception\SeasonalEventsException` | Every exception of the library. |
| `Exception\InvalidSeason` | A value the library refuses, with the reason. |
