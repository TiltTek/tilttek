<!-- phil-dev source-sha256=1b117a3c892cdef7b4a0f1eca797722103fe11bc6764c8e10bbde5a914917d73 -->
# Tests

## Les tests de la bibliothèque

```sh
composer install
vendor/bin/phpunit --no-configuration tests
```

La suite unitaire, sous `tests/Unit`, s'exécute sans réseau : jours et dates, mois, règles bissextiles et leurs
décomptes sur les années négatives, lunes et leurs phases, ères, saisons, un calendrier avec une fête hors de la semaine
et des jours bissextiles seuls, la forme en tableau et ses refus, calendriers liés, l'agenda avec des rappels uniques et
récurrents, la date courante et son rythme, et les âges. Le modèle grégorien est vérifié contre les dates de PHP
lui-même — numéros de jour et jours de la semaine —, et des tests de propriétés transforment des jours tirés au hasard
sur un million d'années en dates et inversement, et chaque date de quarante ans en son jour et inversement. Dans le
monodépôt, Infection maintient la bibliothèque à 100 % des mutants couverts.

`tests/Fuzz/calendar.php` est la cible de fuzzing de `CalendarCodec` : tout JSON est refusé avec les exceptions de la
bibliothèque, ou donne un calendrier qui s'écrit de nouveau dans le même tableau et dont le jour 0 va à sa date et
revient. `composer fuzz` l'exécute.

## Bancs d'essai

`tests/Benchmark/CalendarBench.php` transforme mille jours loin de l'année 1 en dates avec leur jour de la semaine, leurs
phases de lune et leur saison, et liste les rappels de la plus longue plage qu'un agenda permet. Sur PHP 8.5 : environ
80 ms pour les mille jours, environ 11 ms pour 3 660 jours d'agenda parmi 200 rappels.

## Tester du code qui tient le temps

Donnez à `CurrentDate` un `DateTimeImmutable` fixe, et à `Agenda` un `Store\InMemoryReminders` :

```php
$reminders = new InMemoryReminders();
$reminders->add(new Reminder('r1', 'city-17', $calendar->day(1, 4, 15), Recurrence::Yearly));
$agenda = new Agenda(Calendars::empty()->with($calendar), $reminders);
```
