# Replaying

The ratings of a game are what its results gave, one after the other. `replay()` computes them again from the start:

```php
$ratings->replay('chess');   // the number of rated results replayed
```

It reads every result of the game from the `Contract\PlaySource` given to `Ratings`, in the order they were played,
rates each from the initial ratings, skips the friendly ones, and replaces every rating of the game in the store at
once, with the results the game counts: those the replay rated, so that recording one of them again is refused and a
result deleted from the source may be recorded anew. Each member keeps the visibility they chose; a member with a public rating and no result left keeps it, at the
initial rating. Nothing is dispatched: a replay changes no member's history, it corrects it. The events of the game's
results not delivered yet are cancelled with the ratings they were computed from: a replay may change or remove the
result they told, and `deliver()` then finds none of them.

Replay a game after a result was corrected or deleted, after a member was erased from the source, or to change the
system or a setting: a `Ratings` with another system refuses the ratings of the old one until the game is replayed with
it.

## The source

```php
final class PlayLogSource implements PlaySource
{
    public function results(string $game): iterable
    {
        foreach ($this->plays->ofGame($game) as $row) {   // oldest first
            yield GameResult::fromArray($row);
        }
    }
}
```

The source gives every result of the game, friendly ones included, oldest first; results of the same time keep the
order the source gives. A replay refuses with `InvalidRating` a result of another game, a result earlier than the one
before, and a result given twice. A log of plays is the natural source; until `tilttek/play-log` provides one, the
application implements the interface over its own records.

## The same values every time

Elo ratings are kept in hundredths and skills in millionths, each rounded once per game: no error carries from one game
to the next, and the same results in the same order give the same ratings, whatever was recorded before. A replay of a
game whose results were all recorded gives back exactly the ratings `record()` kept.

A replay holds the ratings of the game's members in memory and reads the results one by one; on a large game, run it
from a worker or a console command, not within a request.
