<!-- phil-dev source-sha256=942527443b126fdc3dc6d3884b28f68278c6dd6f81ad0f9cd497d9075478066e -->
# Objectifs et courbes

## Un objectif

```php
$goal = new Goal(
    'novel-2026',                         // the goal's identifier
    ResourceReference::of('member', '44'), // its author
    50_000,                               // the target
    Day::fromString('2026-11-01'),        // the first day, included
    Day::fromString('2026-11-30'),        // the deadline, included
    Unit::Words,                          // or Unit::Pages
    Curve::linear(),                      // the default
    WritingDays::everyDay(),              // the default
    'America/Montreal',                   // the author's timezone, UTC by default
);
```

| Argument | Accepté |
|---|---|
| `$id` | De 1 à 128 lettres, chiffres, `_`, `.`, `:` ou `-`, commençant par une lettre ou un chiffre |
| `$target` | De 1 à 1 000 000 000 |
| `$start`, `$deadline` | Des `Day` espacés de 1 à 3 660 jours, les deux inclus, avec au moins un jour d'écriture entre eux |
| `$timezone` | Tout fuseau horaire que connaît PHP |

`writingDaysOfPlan()` liste dans l'ordre les jours d'écriture de l'objectif. Un objectif est une valeur : pour repousser
une échéance ou changer une courbe, enregistrez un nouveau `Goal` avec le même identifiant ; ce qui a été consigné reste.

## Jours

Un `Day` est une date sans heure : `Day::fromString('2026-11-01')`, `Day::of($moment, $timezone)` pour la date où tombe
un moment, `plus()`, `daysUntil()`, `isBefore()`, `isAfter()`, `equals()`, `weekday()` — 1 pour lundi à 7 pour
dimanche — et `toString()`. Deux jours se comparent comme deux entiers, les jours écoulés depuis 1970-01-01. Le jour
d'aujourd'hui de l'auteur est la date dans le fuseau horaire de l'objectif : à 23 h 30 à Montréal, un compte va à ce
jour, non au lendemain en UTC.

## Jours d'écriture

```php
WritingDays::everyDay();
WritingDays::on(1, 2, 3, 4, 5);                                    // Monday to Friday
WritingDays::on(1, 3, 5)->except(Day::fromString('2026-11-11'));   // less a date set aside
```

Un jour qui n'est pas un jour d'écriture n'a pas de quota ; un auteur met de côté jusqu'à 366 dates.

## Courbes

Une courbe donne à chaque jour d'écriture un poids selon sa place parmi tous les jours d'écriture du plan :

| Courbe | Poids sur 4 jours d'écriture | Pour |
|---|---|---|
| `Curve::linear()` | 1, 1, 1, 1 | La même part chaque jour |
| `Curve::frontLoaded()` | 7, 6, 5, 4 | Un départ fort, une fin plus légère ; le premier jour pèse environ le double du dernier |
| `Curve::backLoaded()` | 4, 5, 6, 7 | Un départ doux qui monte en puissance |
| `Curve::custom([1, 0, 1])` | Étirés sur les jours | La forme propre à l'auteur : ici, une pause dans le tiers du milieu |

Une courbe personnalisée a de 1 à 366 poids de 0 à 1 000, dont un supérieur à 0 au moins. Les poids sont étirés sur les
jours d'écriture : avec trois poids et six jours, chaque poids couvre deux jours. Un jour de poids 0 ne demande rien tant
qu'il reste d'autres jours ; quand il ne reste que des jours de poids 0, ils se partagent également ce qui reste, de
sorte qu'un objectif ne demande jamais l'impossible.
