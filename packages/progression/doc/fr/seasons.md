<!-- phil-dev source-sha256=d8b43d074838c807c48cb1d20645984a9b4cc2d5dfbd7ea88c88268f6f3f9814 -->
# Saisons

Une saison est une période nommée à la fin de laquelle une piste recommence à zéro. Une piste sans saisons compte toute
son expérience ensemble.

## Saisons du calendrier

`new CalendarSeasons(SeasonLength::Month, $timezone)` suit le calendrier sans interruption : chaque mois (`2026-11`),
chaque trimestre (`2026-q4`) ou chaque année (`2026`), lus dans le fuseau horaire de `Tracks` quand elles sont
construites à partir d'une configuration (`'seasons' => ['length' => 'quarter']`).

## Saisons nommées

`new NamedSeasons(new Season('summer-2026', $start, $end), …)` nomme et date des saisons : un événement, un été. Une
saison commence à `start`, inclus, et finit à `end`, exclu. Deux saisons ne se chevauchent jamais, et du temps peut
rester entre elles : une activité entre deux saisons n'apporte d'expérience à aucune saison, mais compte quand même dans
le total de toujours.

```php
'seasons' => ['named' => [
    ['code' => 'summer-2026', 'start' => '2026-06-21', 'end' => '2026-09-22'],
]],
```

## Les saisons passées

Chaque gain garde le code de la saison où son activité est tombée : une saison commence donc à zéro sans qu'aucune
tâche s'exécute à sa fin. `$progression->history($subject, 'player')` retourne un `SeasonResult` (la `Season` et le
`Level` atteint) pour chaque saison terminée, la plus ancienne en premier. La saison en cours est omise, tout comme une
saison que la piste n'a plus : une saison nommée retirée de la configuration quitte l'historique.
