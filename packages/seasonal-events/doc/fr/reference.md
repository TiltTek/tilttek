<!-- phil-dev source-sha256=3fc9c80e7d033b5fd024327e9705704a8b3e9cc9c6a2dcfe1aad8c6532206de8 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `SeasonSchedule` | Quand vient une saison, dans son fuseau horaire : une fois, ou chaque année par une règle annuelle. |
| `Period` | Une venue d'une saison, avec son code, la date de son premier jour. |
| `Season` | Une saison : son identifiant, son calendrier, ses contenus et ses événements. |
| `SeasonalEvent` | Un événement dans une saison, avec ses contenus, son annonce et son multiplicateur. |
| `Content` | Une chose qu'apporte une saison ou un événement : un genre et une référence. |
| `ContentKind` | `Quest`, `Item` ou `Decoration`. |
| `Multiplier` | Un multiplicateur des gains d'un registre, en pourcentage, arrondi à l'inférieur. |
| `SeasonalEvents` | Les questions d'un moment : ce qui est en cours, ce qui est annoncé, la prochaine venue d'une saison ; implémente `ActiveContent`. |
| `Snapshot` | Ce qui est en cours à un moment. |
| `ActiveSeason` | Une saison pendant la venue où elle est en cours. |
| `ActiveEvent` | Un événement dans une venue de sa saison, avec quand il se déroule et quand il est annoncé. |
| `Days` | L'arithmétique de calendrier de la bibliothèque : les dates en nombres de jours, et le début d'un jour dans un fuseau horaire. |
| `SeasonReader` | Des saisons lues depuis des tableaux simples et réécrites. |
| `Contract\SeasonCatalogue` | D'où viennent les saisons. |
| `Contract\ActiveContent` | Le port par lequel les autres paquets lisent ce qui est en cours. |
| `Store\InMemorySeasonCatalogue` | Des saisons données une fois, dans le code ou lues depuis une configuration. |
| `Bridge\Quests\SeasonQuestSchedule` | Le calendrier d'une quête de `tilttek/quests` qui s'ouvre avec une saison ou un événement. |
| `Bridge\Collections\SeasonalItems` | Les objets de `tilttek/collections` qu'apportent les saisons à un moment. |
| `Exception\SeasonalEventsException` | Toutes les exceptions de la bibliothèque. |
| `Exception\InvalidSeason` | Une valeur que la bibliothèque refuse, avec la raison. |
