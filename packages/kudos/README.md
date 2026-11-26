# tilttek/kudos

**Thanks between members, with fixed messages, daily caps and no public count or leaderboard.**

tilttek/kudos lets a member thank another — for their help, a game well played, a great idea — with one of the
application's fixed messages, picked rather than typed, about an object or not: a comment, a game, a session. The
messages are a closed list the application translates and extends; no free text is ever kept. A member gives at most
five thanks a day by default, counted in their own time zone, and thanks the same member at most three times in seven
days, so that thanks keep their value and two members cannot thank each other in a loop. A member may decline thanks,
from everyone or from one giver; nobody can thank themselves.

What a member received is theirs alone: the receiver reads their thanks, the giver reads what they gave, and the
library offers no total of thanks received, no ranking and no reading of what another member received. What it counts
is the thanks a member **gave**, the measure of a badge for generous members.

Thanks are an activity of `tilttek/activity`, recorded under an idempotency key — a thanks clicked twice is given once
—, withdrawn by an entry of their own, and erased with the member through the journal. The library sends no
notification: it tells the application's observers, which relay the event if they want to.

It is for a community that wants recognition between members without a popularity contest. It is a plain PHP library
whose dependencies are `tilttek/activity`, `phil/resource` and `psr/clock`.

## Status

| Part | State |
|---|---|
| `Kudos`: give, refuse, withdraw, cancel, read what was received and given, count what was given | Implemented and tested |
| `Message`, `Messages`: the closed list of messages, its defaults and the application's own | Implemented and tested |
| `Limits`: the daily cap in the member's time zone, and the limit per receiver over a period | Implemented and tested |
| `Contract\KudosPreferences`, `Contract\MemberTimeZones`, `Contract\KudosObserver`, with their defaults | Implemented and tested |
| A Symfony bundle: services, translations of the default messages, a Stimulus control | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [Giving and reading thanks](doc/kudos.md) | Messages, keys, refusals, caps, reading, withdrawing, erasing. |
| [Extension points](doc/extension-points.md) | Messages, preferences, time zones, observers and the badge criterion. |
| [Exceptions](doc/exceptions.md) | `KudosException` and its exceptions, with their messages. |
| [Testing](doc/testing.md) | The library's tests, and testing code that gives thanks. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require tilttek/kudos
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Activity\Journal;
use Tilttek\Activity\Log\InMemoryActivityLog;
use Tilttek\Kudos\Kudos;

$journal = new Journal(new InMemoryActivityLog(), $clock);
$kudos = new Kudos($journal, $clock);

$kudos->give(ResourceReference::of('member', '44'), ResourceReference::of('member', '12'), 'help', ResourceReference::of('comment', '4512'));

$kudos->received(ResourceReference::of('member', '12'));   // what member 12 received, for member 12 only
$kudos->givenCount(ResourceReference::of('member', '44')); // 1
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
