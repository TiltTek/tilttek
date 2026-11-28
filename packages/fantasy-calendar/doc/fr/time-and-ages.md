<!-- phil-dev source-sha256=963b413d64ffcc09fc171c36773473d0e6410fea30eb9800107c2eeee46a9fac -->
# Temps et âges

## La date courante

`CurrentDate` est le jour courant d'un monde : le jour auquel elle a été réglée, le moment réel où elle l'a été, en
secondes depuis l'époque Unix, et le `Pace` auquel elle avance depuis. L'application garde ces trois valeurs et donne
l'heure réelle chaque fois qu'elle la demande :

```php
$now = new CurrentDate($calendar->day(412, 4, 15), time(), Pace::of(1));
$now->today(new DateTimeImmutable());                  // the day set, plus the whole days passed since

$now = $now->advancedBy(3, new DateTimeImmutable());   // the game master moves it on three days
$now = $now->setTo($day, new DateTimeImmutable());     // or sets it
$now = $now->atPace(Pace::of(7), new DateTimeImmutable()); // a week of the world for each real day, from now
```

| Rythme | Avance |
|---|---|
| `Pace::manual()` | Seulement quand le maître de jeu la fait avancer |
| `Pace::of(1)` | D'un jour du monde pour chaque jour réel |
| `Pace::of($worldDays, $realDays)` | De tant de jours du monde pour tant de jours réels, chacun de 1 à 1 000 |

Les jours passent entiers : le jour courant change une fois qu'un jour entier du monde s'est écoulé. Un moment réel
antérieur au réglage de la date donne le jour réglé.

## Âges

Un `Lifespan` est la durée d'une entrée : la naissance et la mort d'un personnage, la fondation et la chute d'une ville.
Son âge un jour donné est le nombre d'années entières depuis son début, et s'arrête à sa fin :

```php
$life = new Lifespan($calendar->day(380, 6, 2), $calendar->day(441, 1, 9));
$life->ageOn($calendar, $today);       // null before the birth; the age at death after it
$life->hasEndedBy($today);
```

L'âge compte une année de plus chaque fois que le mois et le jour du début sont atteints ou dépassés ; voyez
[Jours et dates](days-and-dates.md#années-entre-deux-jours). Une fin avant le début, ou des jours de deux calendriers,
sont refusés.
