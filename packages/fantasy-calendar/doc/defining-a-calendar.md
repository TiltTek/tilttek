# Defining a calendar

A `Calendar` is built from its parts, or read from an array with `CalendarCodec`.

```php
$calendar = new Calendar(
    'vale',
    'Reckoning of the Vale',
    [
        new Month('frost', 'Frost', 30),
        new Month('thaw', 'Thaw', 30),
        new Month('midfeast', 'Midfeast', 0, leapDays: 1, inWeek: false),
        new Month('bloom', 'Bloom', 30),
        new Month('harvest', 'Harvest', 30, leapDays: 1),
    ],
    ['Moonday', 'Fireday', 'Wellday', 'Restday'],
    firstWeekday: 0,
    leapRule: new LeapRule([new LeapCycle(4), new LeapCycle(100, 0, false)]),
    moons: [Moon::ofDecimalCycle('silver', 'Silver', '29.5')],
    eras: [new Era('before', 'Before the Founding', null), new Era('founding', 'Founding', 1)],
    seasons: [new Season('cold', 'Cold', 1, 1), new Season('warm', 'Warm', 4, 1)],
    climateZone: 'temperate',
);
```

## Months

A `Month` has an identifier, a name, its days in a common year, the days it gains in a leap year, and whether its days
count in the week. A month of no common days and one leap day is a leap day standing alone; a month outside the week
is a festival whose days have no weekday and do not move the week.

## The week

The week is the list of its day names, 1 to 100 of them. `firstWeekday` is the weekday, from 0, of day 0 — the first
day of year 1 —; the weekday of every other day follows from it, skipping the days outside the week.

## Leap years

A `LeapRule` is a list of `LeapCycle`: every so many years from an offset, a year is leap, or for an exception, is
not. The last cycle a year matches decides. The Gregorian rule is three cycles:

```php
new LeapRule([new LeapCycle(4), new LeapCycle(100, 0, false), new LeapCycle(400)]);
```

The pattern repeats every period, the least common multiple of the cycles, at most 10 000 years; counting the leap
years before any year is then a division and a look-up. Without cycles, no year is leap.

## Moons, eras and seasons

- A `Moon` has a cycle of its own as an exact fraction of days, and the number of a day on which it is new.
- An `Era` numbers the years again from its first year; an era without a first year counts backward the years before
  the first dated era.
- A `Season` starts on a month and day every year has, and lasts until the next.

They are described in [Moons, eras and seasons](moons-eras-seasons.md).

## The array form

`CalendarCodec::toArray()` writes a calendar to an array — for JSON or YAML — and `CalendarCodec::fromArray()` reads it
back:

```json
{
    "id": "vale",
    "name": "Reckoning of the Vale",
    "months": [
        {"id": "frost", "name": "Frost", "days": 30},
        {"id": "midfeast", "name": "Midfeast", "days": 0, "leapDays": 1, "inWeek": false}
    ],
    "weekdays": ["Moonday", "Fireday", "Wellday", "Restday"],
    "firstWeekday": 0,
    "leap": [{"every": 4}, {"every": 100, "leap": false}],
    "moons": [{"id": "silver", "name": "Silver", "cycle": "29.5", "newMoon": 3}],
    "eras": [{"id": "before", "name": "Before the Founding", "start": null}, {"id": "founding", "name": "Founding", "start": 1}],
    "seasons": [{"id": "cold", "name": "Cold", "month": "frost", "day": 1}],
    "climateZone": "temperate"
}
```

- `leapDays`, `inWeek`, `firstWeekday`, `offset`, `leap`, `newMoon`, and the lists of leap cycles, moons, eras and
  seasons are optional.
- A moon cycle is a decimal number of days as a text, with at most six decimals; a whole number of days; or
  `[numerator, denominator]` for any other fraction.
- A season names its month by identifier.
- A key the codec does not know, a value of the wrong type or out of its bounds is refused with `InvalidCalendar`,
  whose message starts with the place of the value: `months.2.days: is a whole number.`

## A Gregorian model

`Template\Gregorian::definition()` gives the proleptic Gregorian calendar as an array ready to copy — twelve months, a
week from Monday, the leap years of 4, 100 and 400, the Moon, the eras before and of the common era, and the seasons of
the northern hemisphere —; `Template\Gregorian::calendar()` reads it. Its day 0, the 1st of January of year 1, is a
Monday, and its year 0 is 1 BCE. Copy the definition, rename it, and change what the world needs.

No calendar of a licensed world is shipped: an application that needs one writes its definition under the licence it
holds.

## Bounds

| Part | Bound |
|---|---|
| Months of a year, days of a week | 1 to 100 |
| Days of a month, leap days included | 0 to 1 000 |
| Leap cycles, moons, seasons | 16 |
| Eras | 100 |
| The period of the leap years | 10 000 years |
| A moon cycle | 1 to 10 000 days, with a denominator up to 1 000 000 |
| Identifiers | 1 to 64 ASCII letters, digits, dots, underscores and hyphens, from a letter or a digit |
| Names | 1 to 200 characters |
