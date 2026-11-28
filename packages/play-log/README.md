# tilttek/play-log

**Log who played which game, when, how long, with scores and winners; players stay anonymous until they confirm, and
statistics per member or group.**

tilttek/play-log keeps the plays of a community's members and groups, on the games of `tilttek/game-catalog`. A play
holds:

- **the game** — an entry of the catalogue, checked when the play is recorded;
- **the seats** — up to 100, each a member by their identifier or an anonymous player, with a score and, in a
  competitive play, whether the seat won;
- **the result** — competitive, a cooperative win, a cooperative loss, or unfinished;
- **the rest, when known** — the date, which may stay unknown rather than pretend the play was today, the length in
  minutes, the group it was played in and the place.

Players stay anonymous by default. The member who records a play is linked to their own seat; the seat of another
member waits for that member to confirm it, unless the application says they agreed beforehand. Until then, whoever
else reads the play sees that seat anonymous, and the play counts for no one but the members who confirmed. A member
declines a seat, which becomes anonymous with its score kept, and a member who leaves is forgotten: the plays they
recorded are deleted and their seats of the others become anonymous.

Statistics are computed when asked, never kept: for a member or a group, over a period, the plays, the wins, the
minutes, every game with its plays, wins, minutes, last date and highest score, the members played with most, and the
h-index of the games — the most games played at least as many times as their number. At most 10 000 plays are read
for one result, which says when more matched.

The facts of a play — recorded, corrected, deleted, a seat linked, waiting or unlinked — go to an observer, each once,
by identifiers only. A game night library that sends its plays through a port of its own reaches the log through a
small adapter, described in the [extension points](doc/extension-points.md).

It is a plain PHP library: two services, value objects, ports and a store kept in memory for tests.

## Status

| Part | State |
|---|---|
| `Play`, `Seat`, `Result`: a play, its seats and how it ended, bounded and checked | Implemented and tested |
| `PlayLog`: record, correct, delete, confirm, decline and forget, with their events and the members' consent | Implemented and tested |
| `PlayQuery`: the plays of a member, waiting for a member, recorded by a member, or of a group, by game and period | Implemented and tested |
| `PlayStatistics`: the statistics of a member or a group, computed when asked and bounded | Implemented and tested |
| `Store\InMemoryPlayStore`: the store of the tests | Implemented and tested |
| A Symfony bundle: the Doctrine store, Messenger, the forms and the Twig components | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Plays](doc/plays.md) | Plays, seats and results, recording and correcting, confirming and declining, reading and forgetting. |
| [Statistics](doc/statistics.md) | What the statistics of a member or a group hold, and their bounds. |
| [Extension points](doc/extension-points.md) | The store, the consent, the observer and its events, and the adapters of other libraries' ports. |
| [Exceptions](doc/exceptions.md) | `PlayLogException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\PlayLog\Play;
use Tilttek\PlayLog\PlayLog;
use Tilttek\PlayLog\PlayQuery;
use Tilttek\PlayLog\PlayStatistics;
use Tilttek\PlayLog\Result;
use Tilttek\PlayLog\Seat;

$log = new PlayLog($store, $catalog, $observer, $consent);    // your PlayStore, the Catalog, your observer and consent

$log->record(new Play(
    'play-1',
    'harbour',                                                 // the game's identifier in the catalogue
    'member-1',                                                // who records it: their own seat is linked at once
    [new Seat('member-1', 42, winner: true), new Seat('member-2', 37), new Seat(score: 30)],
    Result::Competitive,
    new DateTimeImmutable('2027-03-12 19:30'),
    minutes: 75,
    group: 'thursday-club',
));

$log->confirm('play-1', 'member-2');                           // member-2 agrees: the play counts for them

$plays = $log->plays(PlayQuery::ofMember('member-2'), 20);    // the latest first
$visible = $log->play('play-1')->seenBy('member-3');           // seats not confirmed read as anonymous

$statistics = new PlayStatistics($store)->ofMember('member-1');
$statistics->hIndex;                                           // the most games played at least that many times
```

## Requirements

PHP 8.5 or later, with `ext-mbstring` and `tilttek/game-catalog`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
