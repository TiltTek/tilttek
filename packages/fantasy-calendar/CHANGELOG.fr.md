<!-- phil-dev source-sha256=c94184849439b88cb7a50d052d19fa7545fee09f5bb3ced73c08a19ad6b87afc -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/fantasy-calendar` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Calendar`, `Month`, `LeapRule` et `LeapCycle` : des calendriers de 1 à 100 mois et des semaines de 1 à 100 jours, des
  mois hors de la semaine et des jours des seules années bissextiles, des années bissextiles par cycles et exceptions
  qui se répètent en moins de 10 000 ans ; des dates converties en numéros de jour entiers et inversement pour toute
  année à moins d'un million de l'an 0, les jours de la semaine, et les années entières entre deux jours.
- `Day` et `Date` : des jours d'un même calendrier comparés par leurs méthodes, refusés d'un calendrier à l'autre, et
  des dates d'années négatives.
- `Moon` et `Phase` : des lunes au cycle propre, en fraction exacte de jours, lue depuis un nombre décimal, leurs huit
  phases et le prochain jour d'une phase.
- `Era` et `EraYear` : des ères qui numérotent de nouveau les années, et une ère qui compte à rebours avant la première.
- `Season`, `SeasonAt` et `Contract\SeasonSource` : des saisons, la saison d'un jour avec son jour, sa durée et la zone
  climatique du calendrier, et le port par lequel la météo les lit.
- `CalendarCodec` : des calendriers écrits en tableaux et relus, qui refusent toute clé inconnue ou valeur erronée en
  indiquant sa place.
- `Template\Gregorian` : le calendrier grégorien proleptique, comme définition à copier.
- `Calendars` et `Link` : des calendriers liés, des conversions par la plus courte chaîne de liens, et des parents dont
  les rappels apparaissent dans leurs enfants.
- `Reminder`, `Recurrence`, `Occurrence`, `Agenda`, `Contract\ReminderSource` et `Store\InMemoryReminders` : des rappels
  qui attachent une entrée à un jour une fois, chaque année ou chaque mois, l'agenda d'une période d'au plus 3 660
  jours, le port des rappels et le stockage en mémoire pour les tests.
- `CurrentDate` et `Pace` : le jour courant d'un monde, qui avance à un rythme choisi ou seulement quand on le déplace.
- `Lifespan` : des âges depuis une naissance ou une fondation, qui s'arrêtent à la fin.
- `Limits` et les exceptions sous une seule interface `FantasyCalendarException` : `InvalidCalendar`, `OutOfRange`,
  `CalendarMismatch` et `UnknownCalendar`.
- Des tests contre les dates grégoriennes de PHP lui-même, des tests de propriétés sur un million d'années, une cible de
  fuzzing du codec et un banc d'essai des chemins critiques.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
