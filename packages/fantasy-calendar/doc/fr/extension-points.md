<!-- phil-dev source-sha256=068528767242ac46909f32bd8ec3fda1147760ff19206acc5009c00e35d1436c -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application donne | Où |
|---|---|---|
| Les calendriers de son monde | Leurs définitions, construites ou lues dans des tableaux | [Définir un calendrier](defining-a-calendar.md) |
| `Contract\ReminderSource` | Le magasin de ses rappels, dans sa propre base de données | [Rappels](reminders.md#le-port-des-rappels) |
| `Contract\SeasonSource` | Ses propres saisons et zones climatiques — par région, par plan —, pour sa météo | [Lunes, ères et saisons](moons-eras-seasons.md#le-port-des-saisons) |
| La date courante | Le jour, le moment et le rythme qu'elle garde, et l'heure réelle | [Temps et âges](time-and-ages.md) |

Les calendriers, les jours et les dates sont des valeurs immuables ; `Agenda` ne garde aucun état entre deux appels.
