# Reference

| Type | Role |
|---|---|
| `AdventCalendar` | Opens boxes once, shows them as a member sees them, erases a member |
| `Calendar` | The boxes by day, their surprises, the timezone, the policy and the closing |
| `Surprise`, `SurpriseKind` | The reward a box refers to; points, a badge, content or a code |
| `OpeningPolicy`, `PolicyKind` | How long a box stays openable; until closing, the same day or a catch-up |
| `OpeningWindow` | When a box may be opened: from, included, until, excluded |
| `Opening` | A box a member opened, and when |
| `OpenedBox` | What `open()` answers: the opening, the surprise, and whether this call opened it |
| `BoxView`, `BoxState` | A box as one member sees it; locked, openable, opened or missed |
| `Event\BoxOpened` | Dispatched once per first opening, for the application to grant the surprise |
| `Contract\OpeningStore`, `Store\InMemoryOpeningStore` | Where the openings are kept |
| `Exception\AdventCalendarException` and its two exceptions | [Exceptions](exceptions.md) |
