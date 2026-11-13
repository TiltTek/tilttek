# What is on

`SeasonalEvents` answers for a moment — now, by the clock it was given, unless the call names one. It reads the
catalogue at each call, so that a season edited in a database is read as it is.

## A snapshot

```php
$now = $seasons->at();

$now->isQuiet();                 // no season is on
$now->isOn('winter-festival');
$now->quests();                  // the references of the quests on, each once
$now->items();
$now->decorations();
$now->multiplier('points');      // the highest multiplier of the events running, or null
$now->seasons;                   // list<ActiveSeason>: each season on, with its Period
$now->events;                    // list<ActiveEvent>: each event running, with when it runs
$now->toArray();                 // for a JSON answer or a cache
```

The contents are listed once each, those of the seasons first, then those of the events, in the order of the catalogue.

## What is coming

| Call | Answers |
|---|---|
| `announced($moment, $limit)` | The events announced and not started yet, the soonest first, at most `$limit`, 50 at the most: those of the seasons on, and of the next time of each season. |
| `next($season, $after)` | The next time of a season, or null when it is unknown or never comes again. |

An `ActiveEvent` says when it runs, `from` and `until`, `announcedFrom()`, `isAnnouncedAt($moment)`, and its `key()`,
the season, the code of its time and the event, unique to that run.

## The port other packages read

`Contract\ActiveContent` is what a quest package, a shop, a theme or a ledger reads; `SeasonalEvents` implements it:

```php
$content->active(ContentKind::Item);                                   // list<ResourceReference>
$content->isActive(ContentKind::Decoration, ResourceReference::fromString('decoration:snowflakes'));
$content->multiplier('points');
```

A package that depends on the port depends on no season, and an application that has no seasons gives it a port that
answers nothing.
