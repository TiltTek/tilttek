<!-- phil-dev source-sha256=6a69e289a29251fd2550bfc24d4724ce5db59357194699eb2c27be5ea4928504 -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Le catalogue

| Type | Rôle |
|---|---|
| `Catalog` | Le service : entrées à la main et par des sources, identifiants, errata, liens, codes-barres, masquage, page d'un jeu et historique. |
| `Entry` | Un jeu du catalogue : ses détails venus de sa source, les champs corrigés à côté, ses identifiants, ses errata, masqué ou non, et sa version. |
| `Details` | Ce qu'une entrée dit d'un jeu, comme son éditeur l'annonce. |
| `Field` | Un champ des détails : ce qu'une correction change et que l'historique nomme. |
| `EntryType` | Ce qu'une entrée est comme produit. |
| `GameKind` | La façon dont on joue à un jeu. |
| `LanguageDependence` | La quantité de texte qu'un joueur lit en jouant, sur cinq niveaux. |
| `Range` | Un intervalle de nombres entiers, bornes comprises. |
| `Origin` | Saisie à la main, ou envoyée par une source sous sa clé. |
| `SourcedEntry` | Une entrée telle qu'une source l'envoie. |
| `ExternalId` | L'identifiant d'un jeu dans une base ouverte, et le lien vers sa page. |
| `IdentifierScheme` | BoardGameGeek, RPGGeek ou Wikidata. |
| `Gtin` | Le code-barres d'une boîte, gardé sur 14 chiffres. |
| `Erratum` | Une correction du jeu publiée par son auteur ou son éditeur. |
| `Link` | Une entrée reliée à une autre. |
| `LinkType` | La façon dont une entrée se rapporte à une autre. |
| `Sheet` | La page d'un jeu : l'entrée, ses votes et ses liens. |
| `Change` | Une ligne de l'historique d'une entrée. |
| `ChangeKind` | Ce qui a changé une entrée. |

## Corrections

| Type | Rôle |
|---|---|
| `Corrections` | Le service : propose, accepte et refuse des corrections, et rend des champs à leur source. |
| `Correction` | Une correction qu'un membre propose : les détails lus, les détails proposés, les champs changés et la décision. |
| `CorrectionStatus` | En attente, acceptée ou refusée. |

## Votes et critiques

| Type | Rôle |
|---|---|
| `Polls` | Le service : garde un bulletin par membre et par entrée, et les compte. |
| `Ballot` | Les votes d'un membre sur une entrée. |
| `PlayerCountChoice` | Idéal, recommandé ou non recommandé. |
| `PollResults` | Les bulletins d'une entrée, comptés. |
| `PlayerCountResult` | Les votes sur un nombre de joueurs. |
| `Reviews` | Le service : l'expérience de l'auteur d'une critique, figée. |
| `Experience` | Les jeux joués et animés par l'auteur d'une critique quand il l'écrit. |

## Import de collection

| Type | Rôle |
|---|---|
| `Import\CollectionReader` | Lit un fichier de collection dans ses bornes. |
| `Import\Columns` | Les colonnes du fichier que l'on lit, d'après leur en-tête. |
| `Import\Limits` | Les bornes d'un fichier de collection. |
| `Import\CollectionFile` | Un fichier de collection lu : ses lignes et ses erreurs de ligne. |
| `Import\CollectionRow` | Une ligne lue : l'identifiant, les statuts, la priorité et les parties jouées. |
| `Import\RowError` | Une ligne écartée, avec son numéro et sa raison. |
| `Import\CollectionImporter` | Trouve dans le catalogue les jeux d'un fichier. |
| `Import\CollectionImport` | Ce qu'un import a trouvé. |
| `Import\CollectionItem` | Un jeu de la collection d'un membre trouvé dans le catalogue. |
| `CollectionStatus` | La place d'un jeu dans la collection d'un membre. |

## Ports et entrepôt

| Type | Rôle |
|---|---|
| `Contract\CatalogStore` | L'endroit où l'on garde le catalogue. |
| `Contract\EntrySource` | Un port qui alimente le catalogue. |
| `Contract\BarcodeResolver` | Un port qui identifie un code-barres que le catalogue ne connaît pas. |
| `Contract\CatalogObserver` | Informé des errata et des corrections, par identifiants. |
| `Contract\PlayHistory` | Un port vers les parties que l'application enregistre. |
| `Contract\ReviewSystem` | Un port vers les critiques que l'application garde. |
| `Store\InMemoryCatalogStore` | Le catalogue en mémoire, pour les tests. |

## Exceptions et éléments internes

| Type | Rôle |
|---|---|
| `Exception\*` | Les exceptions, énumérées dans [Exceptions](exceptions.md). |
| `Internal\Identifier` | Interne : la forme de chaque identifiant. |
| `Internal\Text` | Interne : le texte qu'une entrée garde. |
| `Internal\Average` | Interne : les moyennes en centièmes entiers. |
