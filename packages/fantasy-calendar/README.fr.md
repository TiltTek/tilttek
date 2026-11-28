<!-- phil-dev source-sha256=2b3f7f020cf37a959c8439fd08a7c7f06c15b1447b8ef242c475fb5899a14f16 -->
# tilttek/fantasy-calendar

**Tenez le temps d'un monde imaginaire : ses propres mois, semaines, années bissextiles, lunes, ères et saisons, des calendriers liés, des âges, des rappels et une date qui avance.**

tilttek/fantasy-calendar décrit les calendriers des mondes imaginaires et le temps qui s'y écoule. Un calendrier a ses
propres mois — certains hors de la semaine, comme une fête entre deux mois —, sa propre semaine, des années bissextiles
selon toute règle de cycles et d'exceptions, des lunes avec des cycles à elles et leurs phases, des ères qui numérotent
de nouveau les années, et des saisons qui donnent le climat du jour. Chaque jour est un nombre entier, négatif avant la
première année ; un calendrier le transforme en date et inversement, donne son jour de la semaine, ses phases de lune,
son année dans l'ère et sa saison, pour toute année à un million de l'année 0.

Les calendriers sont liés par un jour de la campagne qu'ils partagent, de sorte qu'un jour se convertit de l'un à
l'autre ; un calendrier enfant montre les rappels de son parent. Les rappels lient une entrée de l'application — un
personnage, une ville, une bataille — à un jour, une fois ou chaque année ou chaque mois. La date courante du monde
avance au rythme que choisit le maître de jeu, ou seulement quand on la fait avancer ; les âges — d'un personnage,
d'une ville — se calculent à partir de leur début et de leur fin. Les saisons et la zone climatique d'un jour sont
offertes par un port à ce qui produit la météo.

Il sert à une application qui tient une campagne, un monde ou un jeu dans son propre temps. C'est une bibliothèque PHP
simple dont l'unique prérequis est l'extension `mbstring`. Un calendrier grégorien proleptique est fourni comme modèle à
copier ; aucun calendrier d'un monde sous licence n'est livré.

## État

| Partie | État |
|---|---|
| `Calendar`, `Month`, `LeapRule`, `LeapCycle` : mois, semaines, années bissextiles, jours hors de la semaine | Implémenté et testé |
| `Day`, `Date` : jours comme nombres entiers, dates, jours de la semaine, années négatives | Implémenté et testé |
| `Moon`, `Phase`, `Era`, `EraYear`, `Season`, `SeasonAt`, `Contract\SeasonSource` : lunes, ères, saisons et climat | Implémenté et testé |
| `CalendarCodec`, `Template\Gregorian` : calendriers écrits dans des tableaux et relus, le modèle à copier | Implémenté et testé |
| `Calendars`, `Link` : calendriers liés, parents et enfants, conversions | Implémenté et testé |
| `Reminder`, `Recurrence`, `Agenda`, `Contract\ReminderSource`, `Store\InMemoryReminders` | Implémenté et testé |
| `CurrentDate`, `Pace`, `Lifespan` : la date qui avance, les âges | Implémenté et testé |
| Heures et minutes dans un jour | Non commencé |
| Un magasin de rappels sur Doctrine DBAL, un bundle Symfony, une vue de calendrier | Non commencé : des adaptateurs de l'application, ou d'un futur bundle |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Définir un calendrier](doc/fr/defining-a-calendar.md) | Mois, semaines, années bissextiles, lunes, ères, saisons, la forme en tableau et le modèle grégorien. |
| [Jours et dates](doc/fr/days-and-dates.md) | Numéros de jour, dates, jours de la semaine, comparaison de jours, années négatives et bornes. |
| [Lunes, ères et saisons](doc/fr/moons-eras-seasons.md) | Phases de lune, années dans les ères, saisons, zones climatiques et port des saisons. |
| [Calendriers liés](doc/fr/linked-calendars.md) | Calendriers joints par un jour commun, parents et enfants, conversions. |
| [Rappels](doc/fr/reminders.md) | Rappels uniques ou récurrents, l'agenda et le port des rappels. |
| [Temps et âges](doc/fr/time-and-ages.md) | La date courante et son rythme, les âges et les durées de vie. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `FantasyCalendarException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, sa cible de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\FantasyCalendar\CalendarCodec;
use Tilttek\FantasyCalendar\CurrentDate;
use Tilttek\FantasyCalendar\Lifespan;
use Tilttek\FantasyCalendar\Pace;

$calendar = CalendarCodec::fromArray($definition);          // read from the JSON or YAML of the world

$coronation = $calendar->day(412, 4, 15);                 // a Day: a whole number in this calendar
$calendar->date($coronation->plus(100));                   // the date a hundred days later
$calendar->weekdayName($coronation);                       // 'Fireday'
$calendar->moonPhases($coronation);                        // ['silver' => Phase::Full]
$calendar->yearInEra(412);                                // the year 412 of its era
$calendar->seasonOn($coronation);                          // the season, its climate zone, its day and length

$now = new CurrentDate($coronation, time(), Pace::of(1));  // one day of the world for each real day
$today = $now->today(new DateTimeImmutable());

new Lifespan($calendar->day(380, 1, 1))->ageOn($calendar, $today);   // the age of a character
```

## Prérequis

PHP 8.5 ou une version plus récente, avec l'extension `mbstring`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
