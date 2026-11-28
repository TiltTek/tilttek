<!-- phil-dev source-sha256=eba52d73267baef1e71b72d993f901f143520a563d31f670c633d65bb545ca3f -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Étagères

| Type | Rôle |
|---|---|
| `GameShelves` | Le service : exemplaires, souhaits et leur ordre, notes, visibilité, importation et jeux que les membres peuvent apporter. |
| `Shelf` | L'étagère d'un membre : ses exemplaires, ses souhaits dans l'ordre du membre, ses notes, sa visibilité et sa version. |
| `Copy` | Une boîte : son jeu, ses statuts, son édition, son emplacement, si elle peut être apportée et si une importation l'a placée. |
| `CopyStatus` | Possédé, possédé auparavant, précommandé ou à échanger. |
| `Wish` | Un jeu souhaité, ses genres et son niveau. |
| `WishKind` | Liste de souhaits, veut acheter, veut en échange ou veut jouer. |
| `WishLevel` | De « à posséder absolument » à « ne pas acheter ». |
| `Note` | La cote, le commentaire, les étiquettes et les parties jouées d'un membre pour un jeu. |
| `Visibility` | Privée, membres ou publique. |
| `Viewer` | Le membre, un autre membre, ou n'importe qui. |
| `ShelfView` | Ce qu'un observateur voit d'une étagère. |
| `BringableGame` | Un jeu qu'un membre peut apporter à une soirée de jeux. |
| `ShelfImport` | Ce qu'une importation a changé. |
| `ImportMode` | Si une importation ajoute seulement, ou synchronise les exemplaires importés avec le fichier. |

## Exportation

| Type | Rôle |
|---|---|
| `Export\ShelfCsv` | Écrit une vue en CSV : les exemplaires, la liste de souhaits et les notes. |

## Ports et stockages

| Type | Rôle |
|---|---|
| `Contract\ShelfStore` | Garde les étagères à une version. |
| `Contract\ShelfObserver` | Informé des événements d'une étagère. |
| `Store\InMemoryShelfStore` | Les étagères en mémoire, pour les tests. |

## Événements

| Type | Rôle |
|---|---|
| `Event\ShelfEvent` | Chaque événement : le membre et le jeu. |
| `Event\CopyAdded` | Un exemplaire a été placé. |
| `Event\CopyRemoved` | Un exemplaire a été retiré. |
| `Event\CopyOfferedForTrade` | Un exemplaire est devenu à échanger. |
| `Event\CopyWithdrawnFromTrade` | Un exemplaire n'est plus à échanger. |
| `Event\GameWished` | Un jeu a rejoint la liste de souhaits. |
| `Event\WishWithdrawn` | Un jeu a quitté la liste de souhaits. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\ShelfException` | Chaque exception de la bibliothèque. |
| `Exception\InvalidShelf` | Une valeur hors de sa forme ou de ses bornes. |
| `Exception\ShelfRefused` | Un changement que l'étagère n'autorise pas. |
| `Exception\ShelfConflict` | L'étagère a changé depuis sa lecture. |

## Interne

| Type | Rôle |
|---|---|
| `Internal\Identifier` | La forme des identifiants. Interne. |
| `Internal\Text` | La forme des textes qu'un membre écrit. Interne. |
