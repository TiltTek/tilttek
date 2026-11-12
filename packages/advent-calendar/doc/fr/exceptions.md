<!-- phil-dev source-sha256=a4c2714c4b8f50cca103d793d676c59428995db8aad5cb363f0cc3c2d0243bbf -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\AdventCalendar\Exception\AdventCalendarException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidCalendar` | Un identifiant, un fuseau horaire, une date, un nombre de cases, une date de fermeture, un rattrapage, la référence ou la quantité d'une surprise est refusé, ou une case est demandée par un jour dont le calendrier n'a pas | `The calendar "advent-2026" has boxes 1 to 24; 25 is not one.` |
| `BoxNotOpenable` | Une case jamais ouverte est ouverte avant son jour, ou après sa fenêtre | `The box 4 of the calendar "advent-2026" opens at 2026-12-04T00:00:00+01:00.` |
