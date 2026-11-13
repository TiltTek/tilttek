# During play

## The signals

Anyone at the table raises a signal at any moment, without explaining why:

| Signal | Tool | Meaning |
|---|---|---|
| `SignalKind::XCard` | X-card | take out what has happened |
| `SignalKind::Pause` | Script Change | stop the scene for a moment |
| `SignalKind::Rewind` | Script Change | go back and play the moment differently |
| `SignalKind::FastForward` | Script Change | skip past the scene |
| `SignalKind::OpenDoor` | open door | leave the game |
| `SignalKind::CheckIn` | check-in | the game master asks how everyone is doing |
| `SignalKind::AllGood`, `Unsure`, `NotOkay` | check-in | the answers: go on, slow down, stop |

```php
$signal = $safety->raise($table, $participant, SignalKind::XCard, $isGameMaster, $now);
```

A signal of a tool the table does not use raises `ToolNotEnabled`; a check-in asked by someone else than the game
master raises `NotGameMaster`.

## Anonymous by default

A signal says who raised it only at a table that chose named signals, and never an answer to a check-in nor the check-in
itself. An anonymous signal has no author: the store keeps none, the observer pushes none. Its identifier is drawn at
random, so that it tells nothing of who raised it or when.

## Who sees a signal

| Signal | Seen by |
|---|---|
| the open door, the answers to a check-in | the game master alone |
| the check-in | the whole table |
| the X-card and Script Change | the whole table, or the game master alone when the table chose the discreet signal |

`TableSafety::signals()` gives the game master every signal of the table, and the others those the whole table sees,
the last 100 or those after a moment, in the order they were raised.

## Seen by the game master

The game master marks a signal as seen with `TableSafety::acknowledge()`; it answers false for a signal the table does
not have, or one already seen.

## The observer

A `SignalObserver` given to `TableSafety` is told of each signal once it is kept, and of each signal marked as seen, to
push it to the screens of the table. `Signal::toArray()` is what it pushes: identifiers, the kind, the audience and the
moments — the author's reference only on a named signal.

## Check-ins

`TableSafety::checkIn()` counts the answers given since the last check-in, without saying who gave which:

```php
$tally = $safety->checkIn($table);

$tally->askedAt;           // when the game master asked, or null
$tally->allGood;           // 3
$tally->needsAttention();  // true when someone is unsure or not okay
```

An answer counts once per signal; a check-in asked again starts the count over.
