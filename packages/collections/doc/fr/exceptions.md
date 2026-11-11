<!-- phil-dev source-sha256=451e2e13ab147d71e7355b1aeb57f291a9b9ec0796cb88c23b1e33743bae9285 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Tilttek\Collections\Exception\CollectionsException`.

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `InvalidCollection` | Un code, un texte, une rareté, un dé, une récompense, un objet, un ensemble, un paquet, un catalogue, une clé, un jour, une date, un germe ou une liste de verbes est refusé | `"Pirate" is not a item code: 1 to 64 lower-case letters, digits, "_", "." or "-", starting with a letter.` |
| `NotFound` | Une rareté, un objet, un ensemble, un paquet ou un exemplaire est demandé par un code qu'aucun ne porte | `No item is named "parrot".` |
| `CollectionRefused` | Une clé a déjà remis autre chose, un exemplaire n'appartient pas au membre qui le donne ou a changé de mains entre-temps, un exemplaire choisi n'est pas un dé | `The key "order:1" already gave another copy.` |

Les motifs de verbes de la projection sont vérifiés par `tilttek/activity`.
