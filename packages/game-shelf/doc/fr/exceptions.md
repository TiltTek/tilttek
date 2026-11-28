<!-- phil-dev source-sha256=ac5a3aac6ab66f4b2a42e5e773d4a6c48099ab3bcceb4ed761a8bda3516e8ce4 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\ShelfException` : attrapez-la pour les attraper toutes.

| Exception | Levée quand |
|---|---|
| `Exception\InvalidShelf` | Une valeur sort de sa forme ou de ses bornes : un identifiant, un texte, une cote, des statuts incompatibles, une liste trop longue. |
| `Exception\ShelfRefused` | L'étagère n'autorise pas le changement : l'exemplaire ou le souhait n'y est pas, un souhait se déplace à côté de lui-même, l'étagère est pleine, ou le jeu est caché dans le catalogue. |
| `Exception\ShelfConflict` | L'étagère a changé depuis sa lecture : lisez-la de nouveau et refaites le changement. |

Un jeu que le catalogue ne contient pas lève l'`EntryNotFound` de `tilttek/game-catalog`. Rien n'est gardé quand une
exception est levée.
