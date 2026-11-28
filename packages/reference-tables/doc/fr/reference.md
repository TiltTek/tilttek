<!-- phil-dev source-sha256=428e507184b6404c737588df0aabd573e36ade209ea37f59faebe7cd454d1441 -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Tables

| Type | Rôle |
|---|---|
| `ReferenceTable` | Une table : colonnes, lignes trouvées par clé ou par plage, dé, source, système, catégorie et page. |
| `Column` | Une colonne : clé, titre par locale et type. |
| `ColumnKind` | Texte, nombre ou dés. |
| `Cell` | Une valeur d'une ligne : un texte par locale, un entier ou une expression de dés. |
| `Row` | Une ligne : sa clé ou sa plage, et ses cellules par colonne. |
| `Lookup` | Par clé ou par plage. |
| `Range` | Les valeurs qu'une ligne couvre, les deux bornes comprises, lue et imprimée. |
| `TableCatalogue` | Les tables d'un site, par clé, système et catégorie, avec leurs sources. |

## Lancer

| Type | Rôle |
|---|---|
| `Rolling\TableRoller` | Le service : lance une table avec son dé et un modificateur, entre un dé lancé à la table, lance une cellule. |
| `Rolling\TableRoll` | Un lancer : la valeur du dé, le modificateur, le total, la ligne, sa position et les dés gardés. |
| `Contract\TableObserver` | Informé de chaque lancer. |
| `Event\TableRolled` | Un lancer, par la clé de la table, la position de la ligne, le total et s'il a été entré. |

## Rechercher

| Type | Rôle |
|---|---|
| `Search\TableSearch` | Le service : trouve un texte dans les noms, les descriptions, les clés de ligne et les cellules de texte. |
| `Search\SearchHit` | Où un texte a été trouvé : table, position de la ligne, colonne et texte. |

## Lire des documents

| Type | Rôle |
|---|---|
| `Format\TableReader` | Le service : lit un document YAML ou JSON en un `TableCatalogue`. |
| `Format\Syntax` | YAML ou JSON, et la syntaxe d'un fichier selon son extension. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\ReferenceTablesException` | L'interface de toutes les exceptions de la bibliothèque. |
| `Exception\InvalidTable` | Une valeur qui enfreint une règle. |
| `Exception\InvalidDocument` | Un document qui ne peut pas être lu, à son emplacement. |
| `Exception\UnknownTable` | Une table, une colonne ou une cellule qui n'existe pas. |
| `Exception\UnknownRow` | Une clé ou une valeur à laquelle aucune ligne ne répond. |
| `Exception\LookupRefused` | Une consultation ou un lancer que la table n'autorise pas. |

## Interne

| Type | Rôle |
|---|---|
| `Internal\Check` | Les vérifications des clés et des libellés. Interne. |
| `Internal\Fields` | Un tableau associatif d'un document, lu champ par champ. Interne. |
| `Internal\Fold` | Un texte normalisé pour une recherche. Interne. |
