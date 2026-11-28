# tilttek/celebrations

**A rewards inbox: every achievement a member earns, from any package, waits there, is grouped with its kind and is
celebrated once.**

tilttek/celebrations gathers what a member earns across a site — a badge, a level, a quest accomplished, a set
completed — into one inbox. The application's listeners turn the events of its packages into an `Achievement`: the
member, the source that gave it, `badges`, its kind, `badge`, what was earned, `badge.first-roll`, and the
occurrence that makes it unique, `badge.first-roll#1`. An achievement told twice — a notice delivered again, a retried
message — is kept once, so the member is never congratulated twice for the same thing.

Pending entries are **grouped** by source and kind: three badges earned during one session make one "3 new badges",
not three windows. The window shows the **next** group, the earliest earned first, then the next one, until the member
has seen them all; the bell reads the **count**. A reward taken back — a badge revoked — is **withdrawn** and never
shown. An achievement may carry the moment after which it is no longer worth showing — a seasonal trophy after its
season —: its entry then **lapses**, while the reward itself stands, for the inbox only decides what is celebrated,
never what is held.

Each member **chooses**: to turn the window off and keep the inbox, how much it moves — fully, reduced or not at all —,
and whether it plays a sound, which is off unless the member turns it on. A device that asks for reduced motion
(`prefers-reduced-motion`) always reduces it.

It is for a community site, a game site or any application that gives rewards through several packages. It is a plain
PHP library whose dependencies are `phil/resource`, the opaque reference of a member, and `psr/clock`; it depends on no
rewards package, which its achievements only name. Members are references the application gives: the library never
reads a name, an e-mail or an account, and its events carry identifiers only.

## Status

| Part | State |
|---|---|
| `Achievement`: what a member earned, from which source, with its parameters and its moment to stop being shown | Implemented and tested |
| `Celebrations`: receive once, withdraw, group, count, next, mark seen, history, preferences, erase | Implemented and tested |
| `Entry`, `EntryState`: pending, seen, withdrawn or lapsed, with the order of reception | Implemented and tested |
| `Group`, `Celebration`: the entries of one source and kind, and what the window shows next | Implemented and tested |
| `Preferences`, `Motion`: the window on or off, the motion, the sound, the device's reduced motion | Implemented and tested |
| `Contract\CelebrationStore`, with `Store\InMemoryCelebrationStore` | Implemented and tested |
| `Contract\CelebrationObserver`, `CelebrationChanged`: what is told, identifiers only | Implemented and tested |
| `Limits`: pending entries and entries per member, entries shown per group | Implemented and tested |
| A Symfony bundle: Doctrine store, listeners for the rewards packages, the window as a Stimulus controller, translations | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements, installing and wiring. |
| [The inbox](doc/inbox.md) | Receiving achievements once, groups, seen, withdrawn and lapsed entries, bounds, history and erasure. |
| [Celebrating](doc/celebrating.md) | The next celebration, the members' preferences, motion and sound, and listeners for other packages. |
| [Extension points](doc/extension-points.md) | The store, observers, limits and the clock. |
| [Exceptions](doc/exceptions.md) | `CelebrationException` and its exception, with their messages. |
| [Testing](doc/testing.md) | The library's tests and bench, and testing code that uses the inbox. |
| [Reference](doc/reference.md) | Every public type, with its role. |

## Quick start

```sh
composer require tilttek/celebrations
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Celebrations\Achievement;
use Tilttek\Celebrations\Celebrations;
use Tilttek\Celebrations\Store\InMemoryCelebrationStore;

$celebrations = new Celebrations(new InMemoryCelebrationStore(), $clock);
$member = ResourceReference::of('member', '42');

$celebrations->receive(new Achievement($member, 'badges', 'badge', 'badge.first-roll', 'badge.first-roll#1', $earnedAt));
$celebrations->receive(new Achievement($member, 'badges', 'badge', 'badge.night-owl', 'badge.night-owl#1', $earnedAt));
$celebrations->receive(new Achievement($member, 'progression', 'level', 'track.reader', 'track.reader#5', $earnedAt, ['level' => 5]));

$celebrations->count($member);                         // 3, for the bell

$next = $celebrations->next($member, deviceReducesMotion: false);
$next->group->count;                                   // 2: "2 new badges"
$next->remaining;                                      // 1 more group after this one
$celebrations->markSeen($member, $next->group->sequences());
```

## Requirements

PHP 8.5 or later.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
