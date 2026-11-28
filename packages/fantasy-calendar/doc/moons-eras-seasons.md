# Moons, eras and seasons

## Moons

A `Moon` has a cycle as an exact fraction of days and the number of a day on which it is new:

```php
new Moon('red', 'Red', 25, 2, 0);                          // a cycle of 12.5 days, new on day 0
Moon::ofDecimalCycle('moon', 'Moon', '29.530588', 730_124); // the same, written as a decimal number
```

The fraction is kept in lowest terms; `decimalCycle()` writes it back as a decimal number when it has at most six
decimals.

Its phase on a day is one of the eight `Phase` cases — `New`, `WaxingCrescent`, `FirstQuarter`, `WaxingGibbous`,
`Full`, `WaningGibbous`, `LastQuarter`, `WaningCrescent` —, each covering an eighth of the cycle centred on its exact
moment, computed with integers:

```php
$calendar->moonPhases($day);                 // ['red' => Phase::Full, 'pale' => Phase::New]
$moon->phaseOn($day->number);
$moon->nextPhase($day->number, Phase::Full); // the first day from this one with a full moon, or null
```

`nextPhase()` gives null when the cycle is so short that it skips the phase: a cycle of two days shows only new and
full moons.

## Eras

An `Era` numbers the years from its first year, which it calls 1, until the next era. An era without a first year
comes first and counts backward the years before the first dated era: the last year before that era is its year 1.

```php
// Before the Founding, Founding from year 1, Kings from year 300.
$calendar->yearInEra(0);                     // EraYear(before, 1)
$calendar->yearInEra(299);                   // EraYear(founding, 299)
$calendar->yearInEra(300);                   // EraYear(kings, 1)
$calendar->yearFromEra('kings', 5);          // 304
```

A calendar without eras, or a year before the first era when no era counts backward, gives an `EraYear` without an
era, the year as the calendar numbers it. `yearFromEra()` refuses an unknown era and a year the era does not reach.

## Seasons

A `Season` starts on a month and day every year has — not a leap day —, and lasts until the next season starts. The
seasons of a calendar come in the order of the year; the last one runs on into the next year until the first.

```php
$season = $calendar->seasonOn($day);
$season->season->id;                         // 'winter'
$season->climateZone;                        // the climate zone of the calendar, or null
$season->day;                                // 26: the 26th day of the season
$season->length;                             // 89: its days this time
```

A calendar without seasons gives null.

## The season port

`Contract\SeasonSource` is the port through which what draws the weather of the world reads the season and the climate
zone of a day. `Calendar` implements it from its own seasons and climate zone. An application whose regions or planes
have their own seasons implements the port itself and gives that to the weather instead.

The identifiers of the seasons and of the climate zone are those the weather tables of the application use.
