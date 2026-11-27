<!-- phil-dev source-sha256=af1fb1f3cbbb6cba1df03b2dfa5aa1c8f29ac7ba6ebba76d0505034fe52fcc29 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/seasonal-events` sont consignés dans ce fichier.

Les mises à jour suivent les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.1-alpha - 2026-11-20

### Corrigé

- `Multiplier::apply()` multiplie à part les centaines et le reste d'un montant : un montant dont le produit avec le
  pourcentage dépassait `PHP_INT_MAX` alors que son résultat tient est multiplié, et celui dont le résultat ne tient pas
  est refusé avec `InvalidSeason` plutôt que d'échouer avec une `TypeError`.

## 0.1.0-alpha - 2026-11-13

### Ajouté

- `SeasonSchedule` : une saison qui revient une seule fois ou chaque année selon une règle annuelle RFC 5545, dans son
  fuseau horaire, jusqu'à 183 jours, avec ses périodes trouvées à un moment, après un moment et entre deux dates.
- `Season`, `SeasonalEvent`, `Content`, `ContentKind` et `Multiplier` : les quêtes, les objets et les décorations
  qu'apportent une saison et ses événements, et le multiplicateur d'un grand livre, seulement sur un événement annoncé
  au moins un jour à l'avance.
- `SeasonalEvents`, `Snapshot`, `ActiveSeason`, `ActiveEvent` et `Period` : ce qui a lieu à un moment, les événements
  annoncés, et la prochaine période d'une saison, chaque période codée par la date de son premier jour.
- `ActiveContent` : le port par lequel les autres paquets lisent ce qui a lieu.
- `SeasonCatalogue`, `InMemorySeasonCatalogue` et `SeasonReader` : des saisons à partir du code ou de tableaux simples,
  lues et réécrites, au plus 100 dans une liste ; des saisons d'exemple dans `resources/sample-seasons.php`.
- `Bridge\Quests\SeasonQuestSchedule` et `Bridge\Collections\SeasonalItems` : les ponts facultatifs vers
  `tilttek/quests` et `tilttek/collections`.
- `SeasonalEventsException` et `InvalidSeason`.
- Une cible de fuzzing des définitions et des bancs d'essai des questions d'un moment.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
