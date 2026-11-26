# Reference

Every public type of the package, with its role.

| Type | Role |
|---|---|
| `Kudos` | Gives, refuses, withdraws and cancels thanks; reads what a member received and gave; counts what they gave |
| `Message` | A fixed message: name, translation key and English text |
| `Messages` | The closed list of messages, its defaults and the application's own |
| `Limits` | The daily cap, and the limit per receiver over a period |
| `Refusal` | Why thanks are refused: self, declined, daily cap, limit per receiver |
| `Given` | The thanks given, and whether this call gave them |
| `Thanks` | Thanks as their giver or receiver reads them |
| `ThanksPage` | A page of thanks, and the position to read the next one after |
| `KudosGiven` | The event of thanks given, identifiers only |
| `KudosCancelled` | The event of thanks withdrawn or cancelled, identifiers only |
| `Contract\KudosPreferences` | Whether a receiver takes thanks from a giver |
| `Contract\MemberTimeZones` | The time zone a member's day is counted in |
| `Contract\KudosObserver` | Told once of thanks given and cancelled |
| `Preferences\AcceptAll` | Every member takes thanks from everyone |
| `TimeZone\SiteTimeZone` | Every member's day in the site's time zone |
| `Exception\KudosException` and its three exceptions | [Exceptions](exceptions.md) |
