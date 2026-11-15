<!-- phil-dev source-sha256=c86869f0d64d5dedc46e99892fa38e89b3315e6a34c5799c559de1453b321da3 -->
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
| `Contract\DefinitionStore` | Où les versions des ensembles sont gardées |
| `Contract\CompletionStore` | Où les complétions sont gardées |
| `Contract\PendingDeliveries` | Ce qu'un stockage de complétions ajoute pour `Sets::deliverPending()` : les détenteurs qui ont des complétions non remises |
| `Store\InMemoryDefinitionStore`, `Store\InMemoryCompletionStore` | Les stockages en mémoire ; le stockage de complétions implémente aussi `PendingDeliveries` |
| `Event\SetCompleted` | Réparti une seule fois, quand un détenteur complète un ensemble, avec ses récompenses |
| `Event\TierReached` | Réparti une seule fois, quand un détenteur atteint un palier, avec ses récompenses |
| `Exception\SetsException` | L'interface de toutes les exceptions de la bibliothèque |
| `Exception\InvalidSet`, `UnknownSet`, `DefinitionConflict`, `DeliveryFailed`, `UnsupportedStore` | Voir [Exceptions](exceptions.md) |

## Intégration Symfony

Facultative, sur des paquets suggérés ; voir [Reprendre les remises avec Symfony](installation.md#reprendre-les-remises-avec-symfony).

| Type | Rôle |
|---|---|
| `Symfony\DeliverCommand` | La commande `tilttek:sets:deliver` : `Sets::deliverPending()`, ou `Sets::deliver()` avec `--holder` |
| `Symfony\DeliverPendingCompletions` | Le message qui demande une remise en attente ; il porte seulement la limite, 100 par défaut |
| `Symfony\DeliverPendingCompletionsHandler` | Son *handler*, qui appelle `Sets::deliverPending()` |
| `Symfony\DeliverySchedule` | Le planificateur `tilttek_sets` : le message à intervalles, cinq minutes par défaut, avec un verrou facultatif |

`ArrayInput` est interne.
