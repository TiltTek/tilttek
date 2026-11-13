<!-- phil-dev source-sha256=77c762a918bec8bf5ecf53c483611dff1136e1a0d74cf81ee1498247d59d4fda -->
# Saisons et événements

## Calendriers

Un `SeasonSchedule` dit quand vient une saison, dans son fuseau horaire :

```php
SeasonSchedule::once('America/Toronto', '2027-03-20', '2027-04-03');            // once, both days included
SeasonSchedule::yearly('America/Toronto', '2026-12-15', 23);                     // each year from the 15th of December
SeasonSchedule::yearly('America/Toronto', '2026-11-26', 4, 'FREQ=YEARLY;BYMONTH=11;BYDAY=4TH'); // the fourth Thursday of November
```

Une saison annuelle commence aux dates que donne sa règle à partir de sa première date, d'où la règle part :
`FREQ=YEARLY` seul revient à la même date chaque année, `INTERVAL=2` une année sur deux, `COUNT` et `UNTIL` y mettent
fin. Une saison dure de 1 à 183 jours, une demi-année, afin qu'une venue d'une saison ne rencontre jamais la suivante.
Une saison du 29 février revient le 29 février suivant.

Ses jours commencent à minuit à l'horloge murale de son fuseau horaire, malgré les changements d'heure : un jour de 23
ou de 25 heures est un seul jour. Chaque venue d'une saison est une `Period`, du début de son premier jour, inclus, au
début du jour qui suit son dernier, exclu. Son `code` est la date de son premier jour, `2026-12-15` : il reste le même
pour cette venue, quoi que dise l'horloge, de sorte qu'une quête accomplie ou un objet gagné pendant elle se distingue
de l'année suivante.

| Méthode | Répond |
|---|---|
| `periodAt($moment)` | La venue de la saison où tombe un moment, ou null. |
| `nextPeriod($after)` | La première venue qui commence après un moment, ou null quand elle ne revient plus. |
| `periods($from, $through, $limit)` | Les venues qui commencent entre deux dates, au plus un certain nombre. |

## Saisons

Une `Season` a un identifiant stable — le même d'une année à l'autre —, son calendrier, ce qu'elle apporte tant qu'elle
dure, et ses événements :

```php
new Season('winter-festival', $schedule, [
    Content::quest('quest:winter-hunt'),
    Content::item('item:snow-hat'),
    Content::decoration('decoration:snowflakes'),
], $events);
```

Un `Content` est un genre — `ContentKind::Quest`, `Item` ou `Decoration` — et une référence que connaît l'application
ou le paquet auquel elle appartient. Une saison apporte au plus 200 contenus et contient au plus 50 événements.

## Événements

Un `SeasonalEvent` se déroule dans une saison, à partir de l'un de ses jours et pour un nombre de jours — jusqu'à la fin
de la saison par défaut — et ajoute ses propres contenus tant qu'il dure :

```php
new SeasonalEvent('gift-week', fromDay: 8, days: 7, contents: [Content::item('item:wrapped-die')], multiplier: new Multiplier('points', 200), announceDays: 7);
```

Un événement commence et finit à minuit dans le fuseau horaire de la saison, et ne dépasse jamais la fin de la saison.
Son identifiant est unique dans sa saison.

## Annonces et multiplicateurs

Un événement est annoncé `announceDays` jours avant son début, de 0 à 90 : dès lors, `SeasonalEvents::announced()` le
liste, afin qu'un site montre ce qui s'en vient. Un `Multiplier` multiplie les gains d'un registre pendant que son
événement se déroule, en pourcentage : 150 ajoute la moitié, 200 double, de 101 à 500. Un événement doté d'un
multiplicateur est annoncé au moins un jour d'avance : les membres le savent avant qu'il commence, et rien ne se
multiplie par surprise. Les multiplicateurs d'événements qui se recoupent ne s'additionnent jamais : le plus haut
s'applique. `Multiplier::apply()` arrondit à l'inférieur, afin qu'un gain ne grossisse jamais d'une fraction de point.

## Limites

Chaque valeur est vérifiée à sa construction, et un refus lance `InvalidSeason` avec la raison. Les identifiants font de
1 à 64 lettres minuscules, chiffres, `_`, `.` ou `-`, et commencent par une lettre ou un chiffre.
