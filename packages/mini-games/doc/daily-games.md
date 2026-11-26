# Daily games

`DailyGames` gives every player of a day the same game of each mini-game, at a table of their own.

## The seed of the day

`DailySeed::for($game, $day)` is the HMAC-SHA256 of the game's name and the day, `bulls-and-cows` and `2026-11-24`,
written as 64 hexadecimal digits: the seed a table of `tilttek/game-engine` is opened with. Its key is derived from the
site's secret by HKDF-SHA256 under the label `DailySeed::USAGE`, `tilttek_mini_games.daily_seed`, so the same secret
used for something else never gives the same hashes.
Every player of the day gets the same seed, so the same setup and the same chance; another day, another game or
another site's secret gives another seed, and nobody can compute tomorrow's without the secret.

A `Day` is a calendar day as the site counts them: `Day::of($now, $timezone)` gives the day a moment falls on in the
site's timezone, and `Day::fromString('2026-11-24')` reads one, from `2000-01-01`. Days compare as the calendar does,
by their text, and `numberFrom($first)` numbers them from a first day, which is 1: the number of the daily game, shown
in its share.

## The player's table

| Call | What it does |
|---|---|
| `start($game, $day, $player)` | Opens the player's table of that game and day, or gives it back as it is |
| `play($game, $day, $player, $type, $payload, $expected)` | Plays a move of the game for the player; refused with `ConcurrentMove` when `$expected` is not the number of moves |
| `view($game, $day, $player, $preferences)` | What the player sees of their table |
| `share($game, $day, $player)` | The player's result to share |
| `ending($game, $day, $player, $best)` | How the game ended for the player, or null while it goes on |
| `finishedLog($game, $day, $player, $today)` | The log of the finished table, seed included, once the day is over |

The table's identifier is `daily:<game>:<day>:<player>`, from `DailyGames::tableId()`. The player's identifier is an
opaque value of the application — an ASCII letter, then letters, digits, `:`, `.`, `_` or `-` —, never a name or an
address.

A player plays each game once a day: `start()` on the next visit gives back the same table, finished or not. A table
of a past day stays as it was; a day missed opens no table and costs nothing.

## The score sink

When the move that ends a game is played, `DailyGames` sends a `DailyScore` to the score sink, once: the game, the day,
the player's identifier, whether they won, the score the game reports — null when it reports none —, and the way scores
go. An adapter of the application turns it into what its rankings read: an activity of `tilttek/activity` whose
quantity a board of `tilttek/leaderboards` keeps the best of, for instance.

```php
final readonly class LeaderboardScoreSink implements ScoreSink
{
    public function record(DailyScore $score): void
    {
        if ($score->won && null !== $score->score) {
            // Record an activity of the player for the game, with the score as its quantity.
        }
    }
}
```

The sink carries identifiers and numbers only; a sink that defers its work through Symfony Messenger sends them as
they are.

## Verifying the game of a day

The engine shows each player the commitment to the seed of their table from the opening — `Tables::commitment()` —,
and the seed itself in the log of a finished table. Since every player of a day shares the seed, `finishedLog()`
refuses it with `SeedNotRevealable` until the day is over: given the current day, it shows the log of a day before
it only. Anyone may then replay the log and check each draw against the commitment, as `tilttek/game-engine`
describes.
