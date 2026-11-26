<!-- phil-dev source-sha256=58c11c19bb4ded3affa0ca30bfb24948486d92b1962228b382bc5f5019dd6172 -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Handouts` | Crée, révèle, planifie, cache, lit et efface les documents et ce que les joueurs ont vu |
| `Handout` | Un document : référence, table, titre, texte, pièces jointes, révision, moments |
| `Attachment` | Un fichier du stockage de l'application : référence, type de média, description |
| `MediaTypes` | Les types de média que peuvent avoir les pièces jointes |
| `Audience` | Toute la table, ou des joueurs nommés |
| `Disclosure` | Un document révélé à toute la table ou à un joueur : révision montrée, moment, référence qui agit |
| `Trigger` | Un moment ou un signal |
| `StagedReveal` | Une révélation préparée d'avance pour toute la table ou un joueur, sur son déclencheur |
| `Sighting` | Ce qu'un joueur a ouvert d'un document : révision, premier et dernier moments |
| `Visible` | Un document tel que le joueur le trouve : première révélation, fraîcheur, vue |
| `Freshness` | `Unseen`, `Outdated` ou `Seen` |
| `Limits` | Documents par table, pièces jointes par document, audiences par document, révélations par déclenchement |
| `HandoutChanged` | Communiqué après chaque acte, identifiants seulement |
| `Change` | Ce qui est arrivé à un document |
| `Refusal` | Pourquoi un acte est refusé |
| `Contract\HandoutStore` | Où sont gardés les documents et ce qu'ils contiennent |
| `Contract\HandoutObserver` | Ce que l'application fait des événements |
| `Store\InMemoryHandoutStore` | Le dépôt en mémoire |
| `Exception\HandoutException` | Toutes les exceptions de la bibliothèque |
| `Exception\HandoutRefused` | Un acte refusé, avec son `Refusal` |
| `Exception\UnknownHandout` | Aucun document sous une référence |
| `Exception\InvalidHandout` | Une valeur hors des bornes |

`Text` est interne : il vérifie les clés, les titres et les textes, et ne fait pas partie de l'API promise.
