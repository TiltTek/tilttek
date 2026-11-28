# Time and ages

## The current date

`CurrentDate` is the current day of a world: the day it was set to, the real moment it was set, in seconds since the
Unix epoch, and the `Pace` it moves at since. The application keeps these three values and gives the real time each
time it asks:

```php
$now = new CurrentDate($calendar->day(412, 4, 15), time(), Pace::of(1));
$now->today(new DateTimeImmutable());                  // the day set, plus the whole days passed since

$now = $now->advancedBy(3, new DateTimeImmutable());   // the game master moves it on three days
$now = $now->setTo($day, new DateTimeImmutable());     // or sets it
$now = $now->atPace(Pace::of(7), new DateTimeImmutable()); // a week of the world for each real day, from now
```

| Pace | Moves |
|---|---|
| `Pace::manual()` | Only when the game master moves it |
| `Pace::of(1)` | One day of the world for each real day |
| `Pace::of($worldDays, $realDays)` | So many days of the world for so many real days, each 1 to 1 000 |

Days pass whole: the current day changes once a full day of the world has passed. A real moment before the date was set
gives the day set.

## Ages

A `Lifespan` is the span of an entry: the birth and death of a character, the founding and fall of a city. Its age on a
day is the whole years from its start, and stops at its end:

```php
$life = new Lifespan($calendar->day(380, 6, 2), $calendar->day(441, 1, 9));
$life->ageOn($calendar, $today);       // null before the birth; the age at death after it
$life->hasEndedBy($today);
```

The age counts one more year each time the month and day of the start are reached or passed; see
[Days and dates](days-and-dates.md#years-between-two-days). An end before the start, or days of two calendars, are
refused.
