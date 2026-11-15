<!-- phil-dev source-sha256=8915334a43d9d2dbf903a72f4bc2621a7c79eb5e841d9e024f6060455d7c598e -->
# Référence

| Type | Rôle |
|---|---|
| `Sets` | Définit des versions, mesure la progression, enregistre les complétions et répartit leurs événements jusqu'à leur remise, pour un détenteur ou pour les détenteurs qui attendent depuis le plus longtemps |
| `SetDefinition` | Une version d'un ensemble : membres, paliers et récompenses ; évalue des possessions ; lue depuis des tableaux et du JSON et écrite vers eux |
| `Tier` | Une étape d'un ensemble : un nombre de membres à un niveau ou à un niveau supérieur, avec ses récompenses |
| `Reward` | Une récompense opaque qu'un ensemble ou un palier déclare : genre, référence et quantité |
| `Holdings` | Ce qu'un détenteur possède : chaque membre avec son niveau le plus élevé |
| `Evaluation` | Un ensemble mesuré par rapport à des possessions : possédés, manquants, pourcentage, paliers atteints |
| `Progress` | Une évaluation avec les complétions du détenteur, celles conservées et celles que l'appel a enregistrées |
| `Completion` | Un ensemble ou un palier complété une fois par un détenteur, avec la version et le moment en UTC |
| `Identifiers` | Les formes des codes, des membres et des détenteurs, que d'autres paquets réutilisent |
| `UtcClock` | L'horloge du système en UTC, l'horloge par défaut |
| `DeliveryBackoff` | Combien de temps `Sets::deliverPending()` attend avant d'informer de nouveau un détenteur dont la remise a échoué, et à partir de combien d'échecs de suite chacun est signalé |
| `Contract\DefinitionStore` | Où les versions des ensembles sont gardées |
| `Contract\CompletionStore` | Où les complétions sont gardées |
| `Contract\PendingDeliveries` | Ce qu'un stockage de complétions ajoute pour `Sets::deliverPending()` : les détenteurs qui ont des complétions non remises et les échecs de leur remise |
| `Store\InMemoryDefinitionStore`, `Store\InMemoryCompletionStore` | Les stockages en mémoire ; le stockage de complétions implémente aussi `PendingDeliveries` |
| `Event\SetCompleted` | Réparti une seule fois, quand un détenteur complète un ensemble, avec ses récompenses |
| `Event\TierReached` | Réparti une seule fois, quand un détenteur atteint un palier, avec ses récompenses |
| `Event\DeliveryKeepsFailing` | Réparti quand la remise des complétions d'un détenteur a échoué de nouveau, à partir du nombre d'échecs que le recul signale |
| `Exception\SetsException` | L'interface de toutes les exceptions de la bibliothèque |
| `Exception\InvalidSet`, `UnknownSet`, `DefinitionConflict`, `DeliveryFailed`, `UnsupportedStore` | Voir [Exceptions](exceptions.md) |

`ArrayInput` est interne.
