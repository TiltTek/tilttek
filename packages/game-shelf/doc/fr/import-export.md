<!-- phil-dev source-sha256=78b60ae88b5ce93cceb848ea1627f93cc0c486153dc8b56985db556448fe8d82 -->
# Importation et exportation

## Importer un fichier de collection

`tilttek/game-catalog` lit un fichier de collection exporté d'une base de données de jeux et trouve ses jeux dans le
catalogue : son `CollectionImporter` donne une `CollectionImport`, dont les éléments sont les jeux du catalogue,
visibles, chacun une fois, avec leurs statuts, la priorité d'un souhait et les parties jouées ; il importe les jeux de
rôle si on ne lui dit pas les genres. `GameShelves::import()` les met sur l'étagère d'un membre :

```php
use Tilttek\GameCatalog\GameKind;
use Tilttek\GameCatalog\IdentifierScheme;
use Tilttek\GameCatalog\Import\CollectionImporter;
use Tilttek\GameCatalog\Import\CollectionReader;
use Tilttek\GameCatalog\Import\Columns;

$file = new CollectionReader()->read($contents, Columns::geekExport(IdentifierScheme::BoardGameGeek));
$found = new CollectionImporter($catalogStore)->import($file, [GameKind::Board, GameKind::RolePlaying]);
$result = $shelves->import('member-1', $found);

$result->copiesAdded;     // and copiesUpdated, wishesAdded, wishesUpdated, playsUpdated, unchanged
$result->refused;         // the games left out
$result->shelf;           // the shelf kept
```

Chaque jeu du fichier se partage entre un exemplaire et un souhait :

- ses statuts possédé, possédé auparavant, précommandé et à échanger font **un exemplaire dont l'identifiant est celui
  du jeu**, marqué `imported` ;
- ses statuts liste de souhaits, veut acheter, veut en échange et veut jouer font **un souhait**, au niveau que sa
  priorité donne quand elle va de 1 à 5 ;
- ses parties jouées, quand le fichier les donne, deviennent les parties jouées de la **note** du membre pour ce jeu.

L'importation entière est gardée en une seule écriture, et l'observateur apprend ce qui a changé, comme pour tout autre
changement.

## Importer de nouveau

Un membre importe de nouveau après une soirée de jeux, ou après avoir acheté un jeu. Chaque jeu du fichier ne change que
ce que le fichier a changé :

- l'exemplaire dont l'identifiant est celui du jeu prend les statuts du fichier, et garde son édition, son emplacement
  et s'il peut être apporté ; un exemplaire que le membre a placé sous un autre identifiant n'est jamais touché, et un
  exemplaire que le fichier ne nomme plus reste, sauf si l'importation [synchronise](#synchroniser) ;
- le souhait prend les genres du fichier, et son niveau quand le fichier donne une priorité ; un nouveau souhait va en
  dernier dans l'ordre du membre, et un souhait gardé garde sa place ;
- la note prend les parties jouées du fichier, et garde sa cote, son commentaire et ses étiquettes ; une note qui ne
  contient plus rien est supprimée.

Un jeu qui ne change rien est compté dans `unchanged`. Un jeu dont les statuts ne vont pas ensemble — possédé et
possédé auparavant, ou à échanger sans être possédé — est laissé de côté et nommé dans `refused`, tout comme un jeu qui
s'ajouterait à une partie de l'étagère qui est pleine.

## Synchroniser

Par défaut, une importation ajoute et met à jour : un jeu que le membre a vendu reste possédé sur l'étagère jusqu'à ce
que le membre le change. `ImportMode::Synchronize` fait du fichier le compte de la collection :

```php
use Tilttek\GameShelf\ImportMode;

$result = $shelves->import('member-1', $found, ImportMode::Synchronize);

$result->copiesPreviouslyOwned; // the imported copies moved to previously owned
```

En plus de ce que fait une importation, chaque exemplaire importé dont le jeu n'est plus nommé par le fichier comme
exemplaire — sans aucun des statuts possédé, possédé auparavant, précommandé ou à échanger — passe à « possédé
auparavant », et garde son édition, son emplacement, s'il peut être apporté et sa marque. Sa note et son souhait
restent ; un exemplaire qui était à échanger est annoncé comme retiré de l'échange. Un exemplaire déjà possédé auparavant
n'est pas compté de nouveau.

Une importation synchronisée ne touche jamais :

- un exemplaire que le membre a saisi à la main, quel que soit son identifiant ;
- l'exemplaire d'un jeu que le fichier nomme avec des statuts qui ne vont pas ensemble : le jeu est dans `refused` ;
- l'exemplaire d'un jeu que le fichier nomme mais que l'importation a laissé de côté, parce que le catalogue le cache ou
  qu'il est d'un genre non importé : les identifiants de `unmatched` et de `otherKinds` de la `CollectionImport` sont
  cherchés dans le catalogue, une lecture chacun.

Une importation de certains genres de jeux ne déplace les exemplaires des autres genres que lorsque le fichier ne les
nomme plus du tout : un fichier qui les liste encore les garde par `otherKinds`.

## L'exportation en CSV

`Export\ShelfCsv` écrit un `ShelfView` en CSV, un fichier par partie :

| Méthode | Colonnes |
|---|---|
| `copies()` | exemplaire, jeu, statuts, édition, emplacement, apportable |
| `wishlist()` | rang, jeu, genres, niveau |
| `notes()` | jeu, cote, parties, étiquettes, commentaire |

Chaque fichier commence par son en-tête ; les lignes se terminent par CRLF et chaque cellule est entre guillemets, un
guillemet étant doublé. Les statuts et les genres sont leurs valeurs séparées par des espaces, les étiquettes sont
séparées par « ; », et le niveau est son numéro de 1 à 5. Une cellule qui commence par « = », « + », « - » ou « @ » est
écrite après une apostrophe, pour qu'un tableur la lise comme du texte, jamais comme une formule.

L'exportation écrit ce que la vue contient : la vue du membre pour l'exportation d'un membre, jamais davantage. Elle
nomme les jeux par leurs identifiants dans le catalogue ; l'application ajoute leurs noms quand elle en a besoin.
