# What players have seen

## Marking what a player opened

```php
$sighting = $handouts->markSeen($letter, $ada, $ada);
```

The application calls `markSeen()` when a player opens a handout. It records a `Sighting`: the handout's current
revision, when the player first opened it and when they last did. A player opens only what they see
(`Refusal::NotRevealed`).

The event `Change::Seen` is told once per revision: opening the handout again moves only the last moment, while
opening it after an edit tells it again.

## The player's view

```php
foreach ($handouts->visibleTo($table, $ada) as $visible) {
    $visible->handout;        // the current revision
    $visible->revealedAt;     // the earliest moment it was revealed to Ada, to everyone or by name
    $visible->freshness;      // Freshness::Unseen, Outdated or Seen
    $visible->sighting;       // what Ada opened, or null
}
```

`visibleTo()` lists the table's handouts the player sees, the earliest revealed first, handouts revealed at the same
moment in the byte order of their references. Their `Freshness` says what the player has seen:

| Freshness | The player |
|---|---|
| `Unseen` | Has not opened the handout |
| `Outdated` | Opened an earlier revision |
| `Seen` | Opened the current revision |

`unseen()` keeps the handouts that are not `Seen`: the badge on the player's screen, the list of what changed since
they last looked.

## The game master's view

`sightings($handout)` lists what each player opened of the handout, the earliest first seen first: who read the letter,
which revision, and when. `audience($handout)` and `staged($handout)` say to whom it is revealed, and what waits.

## Erasing a player

```php
$handouts->forget($ada, $gm);
```

`forget()` removes the player's disclosures by name, staged reveals and sightings in every table, and tells
`Change::Forgotten` once per handout that held any. Handouts revealed to everyone stay so; the handouts themselves,
which the game master wrote, stay.
