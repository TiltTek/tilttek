# tilttek/game-night

**Run the game nights of a club or a store: tables and their seats, sign-ups with a guest and a waiting list, games
suggested for the players and the time there are, a vote on the games, and the plays sent to the play log.**

tilttek/game-night plans a night — when, where, its host — and the tables it seats, each with its seats and, when
known, its game and the member who leads it. Once the night is published, members sign up:

- a member may bring one guest, who takes a place and of whom nothing else is kept;
- when the places are taken, a sign-up waits on a simple waiting list, served in the order members signed up;
- a member who withdraws frees their places for the first one waiting, who is told once;
- a confirmed member is seated at a table that has the free seats.

The games are suggested for a table, or for the whole night, among the games the members can bring — read from their
shelves through a port, never otherwise — and the games the club or the store keeps: a game is suggested when its
publisher states the number of players and it ends within the minutes there are. The suggestions come from
`tilttek/game-catalog`, ordered by what its members voted for this number of players, and each one carries the number
of votes it rests on, to be shown beside it. Of BoardGameGeek, a suggestion keeps the identifier and the link, nothing
else.

Before the night, the host proposes games of the catalogue to the vote; each member signed up votes once, and may
change their vote until the night starts. The result says how many voted, what each game received and its share, and
whether one game won or several tie: a tie is left to the host, the library does not draw.

Once the night has started, the games played at its tables go to the application's play log through a port, with the
members by their identifiers and the guests as a number.

The facts of a night — published, full, cancelled, a seat offered to a member waiting — go to an observer, each once,
by identifiers only: the library sends no reminder, and never repeats one to the members absent.

It is a plain PHP library: services, ports, value objects and a store kept in memory for tests.

## Status

| Part | State |
|---|---|
| `GameNight`, `Table`, `Registration`: a night, its tables and seats, its sign-ups, its waiting list and its vote | Implemented and tested |
| `GameNights`: planning, tables, publication, sign-ups, withdrawals, seats, the vote and cancellation, with their events | Implemented and tested |
| `Choice\GameChooser`, `Choice\Criteria`: games suggested for the players and the minutes, with the votes they rest on | Implemented and tested |
| `Vote\VoteResult`: the vote counted and explained | Implemented and tested |
| `NightPlays`: the games played sent to the play log | Implemented and tested |
| `Store\InMemoryNightStore`: the store of the tests | Implemented and tested |
| A Symfony bundle: the Doctrine store, Messenger, the forms and the Twig components | Not started |

## Documentation

| Document | What it covers |
|---|---|
| [Installation](doc/installation.md) | Requirements and installing. |
| [Nights and sign-ups](doc/nights.md) | Planning, tables, publication, sign-ups, the guest, the waiting list, seats and cancellation. |
| [Choosing games](doc/choosing-games.md) | The criteria, the shelves, the order of the suggestions and the votes they rest on. |
| [The vote](doc/vote.md) | Proposing games, voting once, and the result explained. |
| [Plays](doc/plays.md) | The games played sent to the play log. |
| [Extension points](doc/extension-points.md) | The store, the observer and its events, the shelves and the play log. |
| [Exceptions](doc/exceptions.md) | `GameNightException` and its exceptions. |
| [Testing](doc/testing.md) | The library's tests. |
| [Reference](doc/reference.md) | Every type of the library, with its role. |

## Quick start

```php
use Tilttek\GameNight\Choice\Criteria;
use Tilttek\GameNight\Choice\GameChooser;
use Tilttek\GameNight\GameNights;
use Tilttek\GameNight\Table;

$nights = new GameNights($store, $clock, $catalog, $observer); // your NightStore, PSR-20 clock, Catalog, NightObserver

$nights->plan('night-42', new DateTimeImmutable('2027-03-05 19:00'), new DateTimeImmutable('2027-03-05 23:00'),
    'venue-7', 'member-1', [new Table('table-a', 4), new Table('table-b', 6)]);
$nights->publish('night-42');                                // NightPublished

$nights->register('night-42', 'member-2', guest: true);      // two places
$nights->register('night-42', 'member-3');

$suggestions = new GameChooser($catalog, $shelves)           // your ShelfReader
    ->suggest(Criteria::forTable($nights->get('night-42'), 'table-a'));

foreach ($suggestions as $suggestion) {
    // $suggestion->name, $suggestion->choice, $suggestion->choiceVotes, $suggestion->boardGameGeekLink()
}

$nights->propose('night-42', ['lanterns', 'harbour']);
$nights->vote('night-42', 'member-2', 'harbour');
$nights->result('night-42')->winner();                       // 'harbour'
```

## Requirements

PHP 8.5 or later, with `psr/clock` and `tilttek/game-catalog`.

## Contributing

Contributions are welcome on the PHIL monorepo: read the [contribution guide](CONTRIBUTING.md) and the
[code of conduct](CODE_OF_CONDUCT.md). Report a vulnerability privately, as the [security policy](SECURITY.md)
explains, never in a public issue.

## License

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. See [LICENSE](LICENSE).
