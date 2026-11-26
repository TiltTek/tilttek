# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| Point | What an application gives | Default |
|---|---|---|
| `Messages` | Its list of messages | `Messages::defaults()` |
| `Limits` | Its daily cap, and its limit per receiver over a period | Five a day, three per receiver in seven days |
| `Contract\KudosPreferences` | The members' choice to take thanks or not | `Preferences\AcceptAll` |
| `Contract\MemberTimeZones` | The time zone each member's day is counted in | `TimeZone\SiteTimeZone`, UTC |
| `Contract\KudosObserver` | What to do with thanks given or cancelled | None |

The messages and limits are values. The library keeps no state between two calls; the implementations an application
gives keep none either.

## Messages

The default list holds six messages, each with a name, a translation key and an English text:

| Name | Translation key | Text |
|---|---|---|
| `help` | `kudos.message.help` | Thanks for your help |
| `well_played` | `kudos.message.well_played` | Well played |
| `great_idea` | `kudos.message.great_idea` | Great idea |
| `welcome` | `kudos.message.welcome` | Welcome to the table |
| `good_game` | `kudos.message.good_game` | Good game |
| `great_session` | `kudos.message.great_session` | Great session |

An application adds its own, replaces a text, or takes some out:

```php
$messages = Messages::defaults()
    ->with(new Message('nice_map', 'Nice map'))
    ->without('welcome');
```

A name is 1 to 64 lower-case letters, digits and `_`, starting with a letter; a text 1 to 200 bytes; a list at most 100
messages. The application translates each message with its translation key, and falls back on the text. Thanks keep
the message's name: a message taken out of the list stays readable on the thanks given with it, and can no longer be
given.

## Preferences

`KudosPreferences::accepts($receiver, $giver)` says whether the receiver takes thanks from this giver. An application
keeps the members' choice — a setting to decline all thanks, a list of members they decline them from — and answers
from it. `Preferences\AcceptAll` takes every thanks.

## Time zones

`MemberTimeZones::of($member)` gives the time zone the member's day is counted in: the one they chose, the site's
otherwise. `TimeZone\SiteTimeZone` gives the site's zone to every member.

## Observers

`KudosObserver::given()` and `cancelled()` are told once, after the journal wrote the entry, never for a request
repeated under the same key. An observer that sends a notification or a message on a bus carries the event's
identifiers, never more.

## The badge criterion

The thanks a member gave are the activities of the verb `kudos.given` (`Kudos::VERB`) whose member they are, withdrawn
ones left out. A badge of `tilttek/badges` reads them with a count on that verb:

```php
use Tilttek\Badges\Criterion\Count;
use Tilttek\Badges\Criterion\Scope;

new Count(new Scope([Kudos::VERB]), 25); // have thanked other members 25 times
```

Outside `tilttek/badges`, `Kudos::givenCount()` and the journal's `totals()` give the same count. Thanks received are
no measure: the library gives no way to count them, so no badge rewards popularity.
