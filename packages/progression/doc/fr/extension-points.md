<!-- phil-dev source-sha256=364c9a9041dc3f76de39ca63ab70961ec098e3e2114eb0fa5d80a6590d9e6140 -->
# Points d'extension

| Point | Ce que donne une application | Où |
|---|---|---|
| `Store\ExperienceStore`, `Store\MilestoneStore`, `Store\StreakStore` | Le stockage de l'expérience, des jalons et des séries, à la place des magasins en mémoire | [Écrire un magasin](writing-a-store.md) |
| `Season\SeasonSchedule` | Des saisons qui lui sont propres, à côté de `CalendarSeasons` et de `NamedSeasons` | [Saisons](seasons.md) |
| `Streak\TimezoneResolver` | Le fuseau horaire dans lequel la journée d'un membre est lue | [Séries](streaks.md#la-journée-du-membre) |
| `ProgressionObserver` | Ce qui est fait quand un niveau change, qu'un jalon est atteint ou qu'une série se brise | [La projection](projection.md) |
| `Psr\Clock\ClockInterface` | L'horloge que lisent les services : celle du système en production, une horloge fixe dans les tests | [Tests](testing.md) |

Chaque point est passé au constructeur du service qui l'utilise ; les services ne gardent aucun état propre entre deux
appels.
