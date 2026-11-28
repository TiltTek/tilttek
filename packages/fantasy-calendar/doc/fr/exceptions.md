<!-- phil-dev source-sha256=d05f9a6c45d1fac049412a7b66a653d4b866469b3b3e4e9f88f3f9bc430e77d0 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\FantasyCalendar\Exception\FantasyCalendarException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidCalendar` | Un calendrier, une date ou une valeur est refusé : une définition incohérente, une date qui n'existe pas, un tableau qui ne se lit pas — avec alors l'emplacement de la valeur | `months.2.days: is a whole number.` |
| `OutOfRange` | Un calcul sortirait des bornes : une année au-delà d'un million, une plage de jours trop longue, un registre plein | `A year lies from -1000000 to 1000000; it is 1000001.` |
| `CalendarMismatch` | Deux jours de calendriers différents sont comparés ou combinés sans qu'on en ait d'abord converti un | `A day of the calendar "elves" is not a day of the calendar "empire": convert it first.` |
| `UnknownCalendar` | Un calendrier que le registre ne contient pas, ou deux calendriers qu'aucune chaîne de liens ne joint | `No calendar "moon" is registered.` |
