<!-- phil-dev source-sha256=660468f2239678072cbbbee6ea896ce94675558919821790af00a404ecc32988 -->
# Lunes, ères et saisons

## Lunes

Une `Moon` a un cycle comme fraction exacte de jours et le numéro d'un jour où elle est nouvelle :

```php
new Moon('red', 'Red', 25, 2, 0);                          // a cycle of 12.5 days, new on day 0
Moon::ofDecimalCycle('moon', 'Moon', '29.530588', 730_124); // the same, written as a decimal number
```

La fraction est gardée sous forme irréductible ; `decimalCycle()` la réécrit comme nombre décimal quand elle a au plus
six décimales.

Sa phase un jour donné est l'un des huit cas de `Phase` — `New`, `WaxingCrescent`, `FirstQuarter`, `WaxingGibbous`,
`Full`, `WaningGibbous`, `LastQuarter`, `WaningCrescent` —, chacun couvrant un huitième du cycle centré sur son moment
exact, calculé avec des entiers :

```php
$calendar->moonPhases($day);                 // ['red' => Phase::Full, 'pale' => Phase::New]
$moon->phaseOn($day->number);
$moon->nextPhase($day->number, Phase::Full); // the first day from this one with a full moon, or null
```

`nextPhase()` donne null quand le cycle est si court qu'il saute la phase : un cycle de deux jours ne montre que des
nouvelles lunes et des pleines lunes.

## Ères

Une `Era` numérote les années à partir de sa première année, qu'elle appelle 1, jusqu'à l'ère suivante. Une ère sans
première année vient en premier et compte à rebours les années avant la première ère datée : la dernière année avant
cette ère est son année 1.

```php
// Before the Founding, Founding from year 1, Kings from year 300.
$calendar->yearInEra(0);                     // EraYear(before, 1)
$calendar->yearInEra(299);                   // EraYear(founding, 299)
$calendar->yearInEra(300);                   // EraYear(kings, 1)
$calendar->yearFromEra('kings', 5);          // 304
```

Un calendrier sans ères, ou une année avant la première ère quand aucune ère ne compte à rebours, donne un `EraYear`
sans ère, l'année telle que le calendrier la numérote. `yearFromEra()` refuse une ère inconnue et une année que l'ère
n'atteint pas.

## Saisons

Une `Season` commence un mois et un jour que toute année a — pas un jour bissextile —, et dure jusqu'au début de la
saison suivante. Les saisons d'un calendrier viennent dans l'ordre de l'année ; la dernière se prolonge dans l'année
suivante jusqu'à la première.

```php
$season = $calendar->seasonOn($day);
$season->season->id;                         // 'winter'
$season->climateZone;                        // the climate zone of the calendar, or null
$season->day;                                // 26: the 26th day of the season
$season->length;                             // 89: its days this time
```

Un calendrier sans saisons donne null.

## Le port des saisons

`Contract\SeasonSource` est le port par lequel ce qui produit la météo du monde lit la saison et la zone climatique d'un
jour. `Calendar` l'implémente à partir de ses propres saisons et de sa zone climatique. Une application dont les régions
ou les plans ont leurs propres saisons implémente elle-même le port et donne celui-là à la météo à la place.

Les identifiants des saisons et de la zone climatique sont ceux qu'utilisent les tables de météo de l'application.
