<!-- phil-dev source-sha256=e556b42361281dcddc18b709af3fe045c79ea58f3595bd5814d1bbef4c3d1713 -->
# Jours et dates

## Jours

Un `Day` est un jour d'un calendrier comme nombre entier : 0 est le premier jour de l'année 1, 1 le suivant, -1 le
dernier jour de l'année 0. Deux jours se comparent par leurs méthodes — jamais avec `==` — et seulement dans un même
calendrier :

```php
$day->isBefore($other);
$day->isAfter($other);
$day->isSameDay($other);
$day->compare($other);         // -1, 0 or 1
$day->plus(30);                // thirty days later
$day->daysSince($other);       // the days from the other to this one
```

Comparer ou soustraire des jours de deux calendriers est refusé avec `CalendarMismatch` : convertissez-en d'abord un,
voyez [Calendriers liés](linked-calendars.md).

## Dates

Une `Date` est une année, un mois et un jour du mois, tels que le calendrier les lit ; le mois et le jour se comptent à
partir de 1, l'année peut être 0 ou négative.

```php
$day = $calendar->day(412, 4, 15);             // also dayOf(new Date(412, 4, 15))
$date = $calendar->date($day);                 // Date(412, 4, 15)
$date->compare($other);                        // by year, then month, then day
$date->compareWithinYear($other);              // by month, then day
```

Une date qui n'existe pas — le 31 d'un mois de 30 jours, un jour bissextile une année commune — est refusée avec
`InvalidCalendar`.

## Années et mois

| Méthode de `Calendar` | Donne |
|---|---|
| `isLeap($year)` | Si l'année est bissextile |
| `yearLength($year)` | Les jours de l'année |
| `monthLength($year, $month)` | Les jours d'un mois cette année-là |
| `month($month)` | Le `Month` de ce numéro |

## Jours de la semaine

`weekday($day)` donne le jour de la semaine à partir de 0, `weekdayName($day)` son nom ; les deux sont null pour un jour
d'un mois hors de la semaine. Les jours hors de la semaine ne la font pas avancer : le lendemain d'une fête a le jour de
la semaine qui suit celui de la veille.

## Années entre deux jours

`yearsBetween($from, $to)` donne les années entières d'un jour à un autre : une de plus chaque fois que le mois et le
jour du premier sont atteints ou dépassés. Une personne née un jour bissextile a son anniversaire le lendemain de la fin
de ce mois une année commune. Voyez [Temps et âges](time-and-ages.md).

## Bornes

Les années vont de -1 000 000 à 1 000 000. Un jour hors de ces bornes, ou une année au-delà, est refusé avec
`OutOfRange`. Dans les bornes, transformer un jour en date est une division et au plus quelques corrections, quelle que
soit l'année.
