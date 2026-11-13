<!-- phil-dev source-sha256=ffedb62c8a6c7f266ebffbd55696edc9714c6b641b672f0a8b389c9854d6b044 -->
# Référence

| Type | Rôle |
|---|---|
| `Sets` | Définit des versions, mesure la progression, enregistre les complétions et répartit leurs événements |
| `SetDefinition` | Une version d'un ensemble : membres, paliers et récompenses ; évalue des possessions ; lue depuis des tableaux et du JSON et écrite vers eux |
| `Tier` | Une étape d'un ensemble : un nombre de membres à un niveau ou à un niveau supérieur, avec ses récompenses |
| `Reward` | Une récompense opaque qu'un ensemble ou un palier déclare : genre, référence et quantité |
| `Holdings` | Ce qu'un détenteur possède : chaque membre avec son niveau le plus élevé |
| `Evaluation` | Un ensemble mesuré par rapport à des possessions : possédés, manquants, pourcentage, paliers atteints |
| `Progress` | Une évaluation avec les complétions du détenteur, celles conservées et celles que l'appel a enregistrées |
| `Completion` | Un ensemble ou un palier complété une fois par un détenteur, avec la version et le moment en UTC |
| `Identifiers` | Les formes des codes, des membres et des détenteurs, que d'autres paquets réutilisent |
| `UtcClock` | L'horloge du système en UTC, l'horloge par défaut |
| `Contract\DefinitionStore` | Où les versions des ensembles sont gardées |
| `Contract\CompletionStore` | Où les complétions sont gardées |
| `Store\InMemoryDefinitionStore`, `Store\InMemoryCompletionStore` | Les stockages en mémoire |
| `Event\SetCompleted` | Réparti une seule fois, quand un détenteur complète un ensemble, avec ses récompenses |
| `Event\TierReached` | Réparti une seule fois, quand un détenteur atteint un palier, avec ses récompenses |
| `Exception\SetsException` | L'interface de toutes les exceptions de la bibliothèque |
| `Exception\InvalidSet`, `UnknownSet`, `DefinitionConflict` | Voir [Exceptions](exceptions.md) |

`ArrayInput` est interne.
