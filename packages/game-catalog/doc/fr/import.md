<!-- phil-dev source-sha256=16500140c14d78cecdcb48af82a44fd26f0195bd08168d2fb17aff45cb2afc65 -->
# Import de collection

Un membre importe sa collection depuis le fichier qu'il exporte d'un autre catalogue. L'import lit le fichier, y trouve
ses jeux dans le catalogue par identifiant, et les renvoie pour que l'application les garde sur l'étagère du membre.
Rien n'est ajouté au catalogue à partir du fichier, et aucune API n'est appelée : un fichier seulement.

```php
use Tilttek\GameCatalog\IdentifierScheme;
use Tilttek\GameCatalog\Import\CollectionImporter;
use Tilttek\GameCatalog\Import\CollectionReader;
use Tilttek\GameCatalog\Import\Columns;

$file = new CollectionReader()->read($upload, Columns::geekExport(IdentifierScheme::RpgGeek));
$import = new CollectionImporter($store)->import($file);   // the role-playing games

$import->items;        // CollectionItem: the entry, its statuses, the priority of a wish, the plays
$import->unmatched;    // the identifiers no visible entry holds, for the member to propose the game
$import->otherKinds;   // the games of other kinds than those imported
$import->file->errors; // the rows left out, with their line and why
```

## Le fichier

Un CSV selon la RFC 4180 : une ligne d'en-tête d'abord, en UTF-8 avec ou sans marque d'ordre des octets, des champs
entre `"` quand ils contiennent le délimiteur, un guillemet ou un saut de ligne. `Columns` dit quelles colonnes sont
lues, d'après leur en-tête, apparié sans égard à la casse et sans les espaces autour :

| Colonne | Lue comme |
|---|---|
| L'identifiant | Un identifiant de la base, obligatoire |
| La base, ou une seule base pour le fichier | `boardgamegeek`, `rpggeek` ou `wikidata` |
| Chaque colonne de statut | `1`, `yes`, `true` ou `x` donnent le statut ; `0`, `no`, `false` ou le vide ne le donnent pas |
| La priorité | La priorité d'un souhait, de 1 à 5 |
| Les parties | Les parties jouées, de 0 à 100 000 |

Toute autre colonne n'est pas lue : le nom d'un jeu, une note, un commentaire. `Columns::geekExport()` donne les
colonnes de la collection exportée de BoardGameGeek ou de RPGGeek — `objectid`, `own`, `prevowned`, `preordered`,
`fortrade`, `want`, `wanttobuy`, `wanttoplay`, `wishlist`, `wishlistpriority`, `numplays` —, toutes d'une même base. Le
délimiteur est `,`, `;`, une tabulation ou `|`.

## Bornes

Le fichier est téléversé par un membre et personne ne l'a vérifié : `Limits` le borne.

| Limite | Valeur par défaut | Au-delà |
|---|---|---|
| `bytes` | 2 Mio | Le fichier est refusé |
| `rows` | 20 000 | Le fichier est refusé |
| `columns` | 100 | Le fichier est refusé quand son en-tête en a davantage ; une ligne qui en a davantage est écartée |
| `cellBytes` | 4 Kio | La ligne est écartée |
| `errors` | 100 | Les erreurs de ligne suivantes sont comptées dans `moreErrors`, pas gardées |

Un fichier qui n'est pas en UTF-8, n'a pas d'en-tête ou n'a pas une colonne obligatoire lève `InvalidCollectionFile`.
Une ligne qui ne peut pas être lue — un identifiant hors de sa forme, un statut qui n'est ni oui ni non, une priorité
ou des parties hors des bornes, un jeu déjà présent sur une ligne antérieure — est écartée avec son numéro, l'en-tête
étant la ligne 1, et le lecteur continue.

## Trouver les jeux

`CollectionImporter::import()` trouve l'entrée de chaque ligne par son identifiant. Il importe les jeux de rôle, sauf
si on lui donne d'autres `GameKind` ; une entrée masquée ou absente du catalogue va dans `unmatched`, une entrée d'un
autre genre dans `otherKinds`, et une entrée déjà importée d'une ligne antérieure — le même jeu sous un autre
identifiant — est comptée dans `duplicates`.
