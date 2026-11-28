<!-- phil-dev source-sha256=f2b8020ec408d6df7a8b2c10ee82db54ac1815d0b0546d5fe700c0927ac8d322 -->
# Entrées et sources

## Une entrée

Une `Entry` est un jeu : un produit, si bien qu'une extension, un scénario ou une traduction est une entrée à part,
reliée aux autres. Elle a un identifiant que l'application choisit — opaque, commençant par une lettre, d'au plus 128
caractères —, une `Origin` et des `Details` :

| Champ | Valeur | Bornes |
|---|---|---|
| `name` | Le nom du jeu | 1 à 200 caractères, sur une ligne |
| `type` | `EntryType` : jeu de base, extension, extension autonome, promo, accessoire, extension de fan, scénario, supplément | — |
| `kind` | `GameKind` : plateau, cartes, jeu de rôle, figurines, grandeur nature | — |
| `system` | La clé du système de jeu d'un jeu de rôle, tirée de la liste de systèmes de l'application | Un identifiant |
| `publishers`, `designers` | Des noms, dans l'ordre de la boîte | Au plus 20 chacun, 1 à 120 caractères, chacun une fois |
| `year` | L'année de publication | 1 à 9999 |
| `players` | Les nombres de joueurs qu'annonce l'éditeur, en `Range` | 1 à 999 |
| `playtime` | Les minutes d'une partie qu'annonce l'éditeur | 1 à 100 000 |
| `minimumAge` | L'âge qu'annonce l'éditeur | 0 à 99 |
| `languages` | Les langues de cette édition, en étiquettes BCP 47 | Au plus 50, chacune une fois |
| `levels` | Les niveaux des personnages pour lesquels un scénario est écrit | 0 à 1000 |
| `languageDependence` | La quantité de texte que l'on lit en jouant | — |
| `description` | Une courte description | 1 à 4000 caractères, sauts de ligne conservés |
| `cover` | L'identifiant de l'image de couverture, que l'application garde | Un identifiant |

Le texte est en UTF-8, sans espaces aux extrémités, et ne contient aucun caractère de contrôle hormis les sauts de
ligne d'une description ; une valeur hors de ses bornes lève `InvalidEntry`. Ce sont les valeurs de l'éditeur. Les
votes des membres — les meilleurs nombres de joueurs, le poids, l'âge, la dépendance à la langue — sont gardés à part,
dans [les bulletins](polls.md), et montrés à côté d'elles.

`Entry::details()` donne les valeurs à montrer : celles de la source, avec [les champs corrigés](corrections.md) à leur
place.

## Saisie à la main

```php
$catalog->add('tide', new Details('Tide', players: new Range(2, 4)), [ExternalId::boardGameGeek(1234)], by: 'admin-1');
```

Une entrée du même identifiant lève `CatalogConflict` ; un identifiant qu'une autre entrée détient lève
`CatalogRefused`.

## Envoyée par une source

Une source est un port qui alimente le catalogue — les livres publiés d'un outil de rédaction, une liste que le site
garde, une base ouverte dont la licence le permet. Elle implémente `Contract\EntrySource` : un nom, et les entrées
qu'elle envoie, chacune un `SourcedEntry` sous sa propre clé.

```php
$catalog->feed($books);              // reads at most Catalog::MAX_SYNCHRONIZED entries
$catalog->synchronize($sourced);     // one entry
```

La première fois qu'une source envoie une clé, le catalogue ajoute l'entrée sous l'identifiant que donne le
`SourcedEntry`. Les fois suivantes, il retrouve l'entrée par son origine et la met à jour : les valeurs de la source
sont remplacées, les identifiants et les errata qu'elle n'avait pas sont ajoutés, et les champs que l'administration a
corrigés restent. Une entrée renvoyée sans changement n'est pas enregistrée de nouveau. Une source qui envoie une entrée
sous un autre nom que le sien lève `CatalogRefused`.

Une source alimente le catalogue sans jamais en être la seule copie : chaque entrée qu'elle a envoyée reste, et le
catalogue continue de fonctionner quand la source a disparu ou ne répond plus. La bibliothèque n'appelle elle-même aucun
service tiers.

## Identifiants de bases ouvertes

Un `ExternalId` est l'identifiant du jeu dans une base d'`IdentifierScheme` :

| Schéma | Valeur | Lien |
|---|---|---|
| `BoardGameGeek` | Des chiffres | `https://boardgamegeek.com/boardgame/<id>` |
| `RpgGeek` | Des chiffres | `https://rpggeek.com/rpgitem/<id>` |
| `Wikidata` | `Q` et des chiffres | `https://www.wikidata.org/wiki/<id>` |

De BoardGameGeek et de RPGGeek, le catalogue garde l'identifiant et construit le lien, et rien d'autre : l'usage de
leurs données relève de leurs propres conditions. Wikidata publie ses données sous CC0, ce qui fait de son identifiant
une ancre libre d'une entrée. Une entrée a au plus un identifiant de chaque base, et un identifiant appartient à une
seule entrée : `Catalog::addIdentifier()` en ajoute un, `Catalog::findByIdentifier()` trouve l'entrée.

## Errata

Un `Erratum` a un identifiant, l'endroit où il s'applique — une page, une carte, une règle —, son texte et sa date de
publication. `Catalog::publishErratum()` l'ajoute à l'entrée, à la fin ; une entrée en garde au plus 500.
L'observateur est informé de chaque erratum publié, par `publishErratum()` ou par une source qui en envoie un nouveau :
c'est le moment d'informer les membres qui suivent le jeu.

## Liens

Un `Link` relie une entrée à une autre par un `LinkType`, avec un numéro d'ordre facultatif — l'ordre de parution des
extensions, par exemple. `Catalog::link()` le garde, ou change son numéro d'ordre ; les deux entrées sont dans le
catalogue, une entrée n'est jamais reliée à elle-même, et une entrée a au plus 200 liens. Un lien `integrates_with` se
lit de la même façon depuis les deux entrées. Les types sont dans [le vocabulaire](vocabulary.md).

## Codes-barres

Un `Gtin` est le code-barres d'une boîte : un EAN-13, un UPC-A, un EAN-8, un GTIN-14 ou l'ISBN-13 d'un livre, son
chiffre de contrôle vérifié, gardé sur 14 chiffres pour que la même boîte lue comme UPC ou comme EAN donne le même
code. `Catalog::assignBarcode()` le donne à une entrée ; un code-barres n'est donné qu'à une seule entrée.

`Catalog::identify()` trouve l'entrée d'une boîte numérisée : le code-barres déjà donné à une entrée, sinon
l'identifiant qu'un `Contract\BarcodeResolver` lui trouve, quand une entrée détient cet identifiant. Le code-barres est
alors donné à l'entrée, si bien que l'on n'interroge le résolveur qu'une fois par code-barres, et que la boîte suivante
de la même édition est identifiée localement — un inventaire de trois boîtes d'une même édition n'interroge qu'une
fois. Le nombre d'exemplaires reste avec l'inventaire, jamais dans le catalogue.

## Masquage

`Catalog::hide()` masque une entrée et `Catalog::show()` la montre de nouveau. Une entrée masquée garde sa page et ses
liens ; l'application décide des endroits où elle ne la montre pas, et l'import de collection la traite comme une entrée
que le catalogue ne détient pas. La règle d'un site — ne montrer par défaut que les jeux de certains créateurs, par
exemple — est un filtre que l'application écrit sur ses propres requêtes, pas une option de la bibliothèque.

## La page d'un jeu

`Catalog::sheet()` renvoie une `Sheet` : l'entrée, `details()` tels que corrigés, les `PollResults` des membres, et ses
liens, depuis elle et vers elle, avec `linksOf()` par type.

## L'historique

Chaque changement d'une entrée ajoute un `Change` à son historique : son `ChangeKind`, les champs changés, le moment,
l'auteur — un membre ou le nom d'une source — et ce dont il s'agit — l'erratum, l'identifiant, le code-barres, la
correction. `Catalog::history()` donne les derniers, le plus récent d'abord, 50 par défaut.

## L'expérience d'un critique

`Reviews::experience()` donne les jeux joués et animés par l'auteur d'une critique, lus dans un `Contract\PlayHistory`
la première fois qu'on la demande et gardés par le `Contract\ReviewSystem` de l'application ; elle reste la même
ensuite, quoi que l'auteur joue depuis. Les critiques elles-mêmes sont à l'application : la bibliothèque ne dépend
d'aucun paquet de critiques.
