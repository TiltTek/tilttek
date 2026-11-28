<!-- phil-dev source-sha256=055ce398c9fb5fe653c24b587a4afdbcb2c27e457f385fa1477014beb9d2a1fe -->
# Référence

Chaque type public du paquet, avec son rôle.

| Type | Rôle |
|---|---|
| `Parties` | Forme, dirige, lit et efface les groupes, leurs membres, leur bourse, leur butin et leurs notes |
| `Party` | Un groupe : référence, campagne, nom, système de jeu, moment de formation |
| `Member` | Un membre d'un groupe : sa référence, ses rôles, son statut et son arrivée |
| `Standing` | `Active` ou `Away` |
| `Purse` | Les pièces du groupe par devise, bornées, comme valeur |
| `Split` | La part de chaque membre pour une devise, et le reste |
| `LootItem` | Du butin sous une clé : nom, quantité, porteur ou réserve, moment d'ajout |
| `Note` | Une note sous une clé : texte, auteur, moments d'écriture et de modification |
| `Limits` | Membres par groupe, groupes par membre, entrées de butin et notes par groupe |
| `PartyChanged` | Communiqué après chaque acte, identifiants seulement |
| `Change` | Ce qui est arrivé à un groupe |
| `Refusal` | Pourquoi un acte est refusé |
| `Composition\Role` | Un rôle que déclare un système : clé, nom, minimum |
| `Composition\RoleCatalogue` | Les rôles d'un système, tirés de ses rôles de groupe ou construits à la main ; vide pour un jeu sans rôles |
| `Composition\CompositionAdvisor` | Lit des membres d'après un catalogue |
| `Composition\Composition` | Les couvertures, les membres sans rôle, les rôles inconnus, l'équilibre |
| `Composition\RoleCoverage` | Un rôle, les membres actifs qui le remplissent, sa couverture |
| `Composition\Coverage` | `Missing`, `Covered` ou `Crowded` |
| `Contract\PartyStore` | Où sont gardés les groupes et ce qu'ils contiennent |
| `Contract\PartyObserver` | Ce que l'application fait des événements |
| `Store\InMemoryPartyStore` | Le dépôt en mémoire |
| `Exception\PartyException` | Toute exception de la bibliothèque |
| `Exception\PartyRefused` | Un acte refusé, avec son `Refusal` |
| `Exception\UnknownParty` | Aucun groupe sous une référence, aucun butin ni aucune note sous une clé |
| `Exception\InvalidParty` | Une valeur hors des bornes |

`Text` est interne : il vérifie les clés, les noms et les textes, et ne fait pas partie de l'API promise.
