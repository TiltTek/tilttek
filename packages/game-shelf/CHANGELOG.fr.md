<!-- phil-dev source-sha256=3b0b097cfedc30574a23a3cf60c39a197cae913b0e7937c21bd80bdc20722f37 -->
# Journal des modifications

[English](CHANGELOG.md)

Tous les changements notables de `tilttek/game-shelf` seront consignés dans ce fichier.

Les mises à jour devraient suivre les principes de [Keep a CHANGELOG](https://keepachangelog.com/).

## 0.1.0-alpha - 2026-11-27

### Ajouté

- `Shelf` : l'étagère d'un membre, ses exemplaires, ses souhaits dans l'ordre du membre et ses notes privées, bornée et
  vérifiée en entier à chaque changement.
- `Copy` et `CopyStatus` : une boîte par son propre identifiant, possédée, possédée auparavant, précommandée ou à
  échanger, avec son édition, son emplacement et si elle peut être apportée à une soirée de jeux.
- `Wish`, `WishKind` et `WishLevel` : un jeu souhaité, voulu à l'achat, voulu en échange ou voulu pour y jouer, à un
  niveau allant de « must have » à « don't buy this », déplacé avant ou après un autre souhait.
- La liste de souhaits, par niveau puis dans l'ordre propre au membre.
- `Note` : la note, le commentaire, les étiquettes et les parties d'un membre pour un jeu, privés pour lui.
- `Visibility`, `Viewer` et `ShelfView` : l'étagère et la liste de souhaits privées jusqu'à ce que le membre les montre,
  les notes et l'emplacement d'un exemplaire jamais montrés à un autre lecteur.
- `GameShelves` : les exemplaires, les souhaits et leur ordre, les notes, la visibilité, et les jeux du catalogue
  vérifiés ; il ne garde aucun état entre les appels.
- `GameShelves::import()` et `ShelfImport` : un fichier de collection lu par `tilttek/game-catalog`, importé en
  exemplaires, en souhaits et en parties, et importé de nouveau sans toucher aux exemplaires saisis à la main.
- `ImportMode::Synchronize` et `Copy::$imported` : une importation qui fait passer à « possédé auparavant » les
  exemplaires importés que le fichier ne nomme plus, jamais les exemplaires saisis à la main ni ceux des jeux que
  l'importation a laissés de côté.
- `GameShelves::bringable()` et `BringableGame` : les jeux que les membres peuvent apporter à une soirée de jeux, à
  adapter par le port d'une soirée de jeux.
- Les événements `CopyAdded`, `CopyRemoved`, `CopyOfferedForTrade`, `CopyWithdrawnFromTrade`, `GameWished` et
  `WishWithdrawn`, par identifiants seulement, annoncés une fois à un `Contract\ShelfObserver` ; un fait sur lequel
  l'observateur échoue n'arrête pas les autres, et la première exception est levée de nouveau une fois chaque fait
  annoncé.
- `Export\ShelfCsv` : les exemplaires, la liste de souhaits et les notes d'une vue en CSV, les cellules de formule étant
  écrites comme du texte.
- `Contract\ShelfStore`, gardé à une version, et `Store\InMemoryShelfStore` pour les tests.
- Les exceptions `InvalidShelf`, `ShelfRefused` et `ShelfConflict`, sous `ShelfException`.
- La documentation dans `doc/`, un fichier par sujet, indexée par le README.
- `CONTRIBUTING.md`, `SECURITY.md` et `CODE_OF_CONDUCT.md`.
