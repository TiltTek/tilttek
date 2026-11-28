# Extension points

The package is at `0.x`: its extension points are stable only from its 1.0 release. Until then, a minor version may
change them, and its CHANGELOG says how.

| To | Do |
|---|---|
| Keep the plays | Implement `Contract\PlayStore`, and pass it to `PlayLog` and `PlayStatistics`. |
| Link members who agreed beforehand | Implement `Contract\PlayerConsent`, and pass it to `PlayLog`. |
| Act on what happened | Implement `Contract\PlayObserver`, and pass it to `PlayLog`. |
| Receive the plays of a game night | Adapt the game night's port to `PlayLog::record()`. |
| Give the catalogue a reviewer's experience | Adapt `PlayStatistics` to the catalogue's `Contract\PlayHistory`. |

## The store

`Contract\PlayStore` keeps the plays, each at a version:

- `play()` gives a play by its identifier, or null;
- `save()` keeps a play at the next version and returns it. A play at version 0 is new, and the store has none of its
  identifier; any other is kept only when the store holds it at that version, and `PlayConflict` is raised otherwise,
  nothing kept;
- `delete()` deletes a play the store holds at that version, and raises `PlayConflict` otherwise;
- `plays()` gives the plays a `PlayQuery` matches, at most the limit, the latest first, those whose date is unknown
  last, then by identifier. `PlayQuery::matches()` states what each query means; a store reads it from `kind`,
  `subject`, `game`, `from` and `until`, by an index on the members of the confirmed seats, the members of the
  waiting seats, the member who recorded the play and the group, each with the date.

`Store\InMemoryPlayStore` keeps the plays in memory, as long as the object lasts: it is for tests. An application
keeps them in its database; a play holds strings, integers, booleans, a date and an enumeration, and its seats keep
their order.

## The consent

`Contract\PlayerConsent::allows()` says whether a member agreed beforehand to be linked to the plays another member
records: they are friends, they joined a group whose plays link its members, or a setting of their account says so.
It is asked for each seat of another member, when a play is recorded or a correction adds the member. Without a
consent, every seat of another member waits for that member to confirm it.

## The observer and its events

`Contract\PlayObserver::notify()` is told each fact once the play is kept, by identifiers only:

| Event | When | Carries |
|---|---|---|
| `Event\PlayRecorded` | A play was recorded. | the member who recorded it |
| `Event\PlayCorrected` | The member who recorded a play corrected it. | the member who recorded it |
| `Event\PlayDeleted` | The member who recorded a play deleted it, or they were forgotten. | the member who recorded it |
| `Event\SeatLinked` | A member's seat was confirmed — by them, by their consent, or as the member who recorded it. | the member |
| `Event\SeatPending` | A member was given a seat they have not confirmed: the moment to ask them. | the member |
| `Event\SeatUnlinked` | A member's confirmed seat no longer counts for them: declined, taken off by a correction, or its play deleted. | the member |

Every event implements `Event\PlayEvent`, whose `play` and `game` are identifiers. The fact of the play comes first,
then the seats linked, waiting and unlinked, each in the order of the seats. An application sends a message from
them — through Messenger, with the identifiers alone — and reads the rest from the log.

`SeatLinked` and `SeatUnlinked` are what an activity journal, such as `tilttek/activity`, counts: an observer records
an activity of the member on the game for `SeatLinked`, with the play and the member as its idempotency key, and
cancels it for `SeatUnlinked`. The package depends on no such library.

## A game night's plays

A game night library that sends the games played at its tables through a port of its own reaches the log through an
adapter the application writes. For `tilttek/game-night`, whose `Contract\PlayJournal` receives `PlayedGame` values —
the night, the table, the game, the members, the guests as a number, the moment and the minutes:

```php
use Override;
use Tilttek\GameNight\Contract\PlayJournal;
use Tilttek\GameNight\PlayedGame;
use Tilttek\PlayLog\Play;
use Tilttek\PlayLog\PlayLog;
use Tilttek\PlayLog\Seat;

final readonly class NightPlayJournal implements PlayJournal
{
    public function __construct(private PlayLog $log)
    {
    }

    #[Override]
    public function record(PlayedGame $play): void
    {
        $this->log->record(new Play(
            'night-'.hash('xxh128', implode('|', [$play->night, $play->table, $play->game, $play->playedAt->format('U.u')])),
            $play->game,
            $play->players[0],
            [...Seat::members($play->players), ...Seat::guests($play->guests)],
            playedAt: $play->playedAt,
            minutes: $play->minutes,
        ));
    }
}
```

Both libraries take the identifiers of the catalogue for the games and members without "@", and the bounds of a
`PlayedGame` — 30 seats, 1 to 1440 minutes — hold within those of a `Play`: the values pass as they are. The guests
become anonymous seats. The adapter decides who records the play — here the first member of the table, whose seat is
linked at once; the others confirm theirs, or are linked by the consent, since a night they attended is the kind of
agreement a `PlayerConsent` may read — and which group it belongs to, when the application keeps the group of a night.
The identifier made from the night, the table, the game and the moment gives a play recorded twice the same
identifier, which the log refuses with `PlayRefused`.

## The catalogue's play history

`tilttek/game-catalog` asks a `Contract\PlayHistory` how many games of an entry a member played, to show the
experience of a reviewer. An adapter reads it from the statistics:

```php
public function played(string $member, string $entry): int
{
    return $this->statistics->ofMember($member)->game($entry)?->plays ?? 0;
}
```

The log knows no game master: `ran()` comes from where the application records who ran a game, or is 0.

## Worker-safe

The services and everything they use keep nothing between calls: they can live as long as a worker, and serve several
sites of one application at once, the store deciding which plays it reads.
