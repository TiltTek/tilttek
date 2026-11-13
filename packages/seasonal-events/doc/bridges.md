# Bridges

The library depends on no other Tilttek package. The bridges under `Bridge\` are used only when the application
installs the package they bridge to.

## Quests

`Bridge\Quests\SeasonQuestSchedule` is the schedule of a `tilttek/quests` quest that opens with a season, or with one of
its events. Each time the season comes is an occurrence, whose code is the date of the season's first day, so that the
quest is accomplished once per year:

```php
use Tilttek\SeasonalEvents\Bridge\Quests\SeasonQuestSchedule;

$schedule = new SeasonQuestSchedule($winter);                              // while the season lasts
$giftWeek = new SeasonQuestSchedule($winter, $winter->event('gift-week')); // while the event runs
```

## Collections

`Bridge\Collections\SeasonalItems` lists the items of a `tilttek/collections` catalog that the seasons bring at a
moment: each item reference of the kind given, `item:snow-hat`, names the item of that code; a reference the catalog
does not hold is left out.

```php
$items = new SeasonalItems($seasons, $catalogProvider)->at();
```

A season that comes each year brings its items again: nothing a member collects is gone for good.

## An advent calendar during a season

A `tilttek/advent-calendar` calendar can run in a season without either package knowing the other: the application
builds the calendar of each year from the season's next time.

```php
use Tilttek\AdventCalendar\Calendar;

$period = $seasons->next('winter-festival');
$calendar = Calendar::of('advent-'.substr($period->code, 0, 4), 'America/Toronto', $period->code, $surprises);
```

The calendar's identifier carries the year, so that the openings of one year are not those of the next.
