<!-- phil-dev source-sha256=48c1c7cd92b6804fbac5cb897d2eaac61f641cebe0f24856c31b09809b922fa0 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Calendar` | Un calendrier : dates et jours, jours de la semaine, phases de lune, années dans les ères, saisons, années entre deux jours |
| `Month` | Un mois : ses jours, ses jours bissextiles, et s'il compte dans la semaine |
| `LeapRule`, `LeapCycle` | Quelles années sont bissextiles, par cycles et exceptions |
| `Moon`, `Phase` | Une lune avec son propre cycle, et ses huit phases |
| `Era`, `EraYear` | Une ère qui numérote de nouveau les années, et une année telle qu'une ère la numérote |
| `Season`, `SeasonAt` | Une saison, et la saison d'un jour avec sa zone climatique |
| `Day` | Un jour d'un calendrier comme nombre entier, comparé par ses méthodes |
| `Date` | Une année, un mois et un jour du mois |
| `CalendarCodec` | Écrit un calendrier dans un tableau et le relit |
| `Template\Gregorian` | Le calendrier grégorien proleptique, comme modèle à copier |
| `Calendars`, `Link` | Les calendriers d'un monde, leurs liens, leurs parents et leurs conversions |
| `Reminder`, `Recurrence`, `Occurrence` | Un rappel qui lie une entrée à un jour, la façon dont il revient, et l'un de ses jours |
| `Agenda` | Les rappels d'une plage de jours, héritiers compris |
| `CurrentDate`, `Pace` | Le jour courant d'un monde et le rythme auquel il avance |
| `Lifespan` | La durée d'une entrée et son âge un jour donné |
| `Limits` | Les bornes de chaque définition et de chaque calcul |
| `Contract\SeasonSource` | Le port des saisons et des zones climatiques, pour la météo |
| `Contract\ReminderSource` | Le port des rappels |
| `Store\InMemoryReminders` | Les rappels en mémoire, pour les tests |
| `Exception\FantasyCalendarException` et ses quatre exceptions | [Exceptions](exceptions.md) |
