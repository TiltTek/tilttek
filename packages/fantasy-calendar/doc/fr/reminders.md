<!-- phil-dev source-sha256=95f54dd8adea3335e8dec8eec02596e1685472d205d149969232d8a717a3adac -->
# Rappels

## Rappels

Un `Reminder` lie une entrée de l'application à un jour d'un calendrier : son identifiant, l'identifiant de l'entrée —
un personnage, une ville, une bataille, opaque pour la bibliothèque —, le jour, et sa `Recurrence` :

| Récurrence | Revient |
|---|---|
| `Once` | Jamais |
| `Yearly` | Chaque année à son mois et à son jour, à partir de son jour ; une année sans ce jour le saute |
| `Monthly` | Chaque mois à son jour, à partir de son jour ; un mois sans ce jour le saute |

```php
new Reminder('r-founding', 'city-17', $calendar->day(1, 4, 15), Recurrence::Yearly);
```

## L'agenda

`Agenda` liste les rappels qui tombent dans une plage de jours d'un calendrier — les siens, et ceux de ses parents
convertis :

```php
$agenda = new Agenda($world, $reminders);
foreach ($agenda->between($from, $to) as $occurrence) {
    $occurrence->reminder;      // the reminder
    $occurrence->day;           // its day in the calendar asked for
    $occurrence->isInherited(); // whether it belongs to a parent calendar
}
```

- Les occurrences viennent par jour, puis celles du calendrier même avant celles de ses parents, puis par identifiant de
  rappel.
- Une plage compte de 1 à 3 660 jours, les deux extrémités comprises, dans un seul calendrier.
- L'agenda ne garde aucun état entre deux appels.

## Le port des rappels

`Contract\ReminderSource` est l'endroit où les rappels sont gardés — la base de données de l'application, d'ordinaire.
L'agenda lui demande, pour chaque calendrier, les rappels qui peuvent tomber dans la plage :

```php
public function reminders(string $calendar, Day $from, Day $to): iterable;
```

La source renvoie au moins chaque rappel du calendrier un jour de la plage, et chaque rappel récurrent du calendrier
dont le premier jour est au plus tard la fin de la plage. Elle peut en renvoyer plus : l'agenda ne garde que les jours
qui correspondent.

`Store\InMemoryReminders` garde des rappels en mémoire, pour les tests et pour un calendrier lu en entier dans un
fichier : `add()`, `remove()`, et le port.
