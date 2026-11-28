<!-- phil-dev source-sha256=02f581f7aa93191c34e04d70cfcc584c5a349742b8fea7424fa169a55e6b3982 -->
# Rechercher

`Search\TableSearch` trouve un texte dans les tables d'un catalogue, dans une locale :

```php
use Tilttek\ReferenceTables\Search\TableSearch;

$hits = new TableSearch()->search($catalogue, 'fantome', 'fr', limit: 20, system: 'lanterns');

$hits[0]->table;     // "wandering"
$hits[0]->position;  // 3, the row's position in the table's $rows
$hits[0]->column;    // "encounter"
$hits[0]->text;      // "Le fantôme à la lanterne"
```

## Ce qu'elle lit

Table par table, dans l'ordre du catalogue — ou les tables d'un seul système —, elle lit :

1. le nom de la table dans la locale ;
2. sa description dans la locale ;
3. pour chaque ligne, dans l'ordre, sa clé, puis ses cellules de texte dans l'ordre des colonnes, dans la locale.

Une cellule de nombre ou de dés n'est pas lue. Un texte dans la locale se replie comme le fait `Translations::in()` : la
langue de la région, puis l'anglais, puis le premier texte détenu.

Un `SearchHit` donne la clé de la table, la position de la ligne — nulle pour un nom ou une description —, la colonne
— nulle pour un nom, une description ou la clé d'une ligne — et le texte qui a correspondu.

## Comment elle trouve

La requête, espaces autour omis, correspond à un texte qui la contient, sans égard à la casse — `mb_strtolower()` — ni
aux accents des lettres latines : `é`, `è`, `ê` et `ë` se lisent `e`, `ç` se lit `c`, `œ` se lit `oe`, `ß` se lit `ss`.
Les lettres des autres écritures sont comparées telles qu'écrites, en minuscules.

## Bornes

Une requête fait de 1 à 100 caractères UTF-8, et une recherche donne de 1 à 100 résultats, 20 si on n'en demande pas ;
elle s'arrête à sa limite. Tout autre cas lève `InvalidTable`.

La recherche lit le catalogue du début à la fin, ce qui convient aux tables d'un site : 50 tables de 100 lignes en deux
langues se lisent en une dizaine de millisecondes. Une application qui cherche dans un corpus plus grand, ou dans les
règles d'un système avec ses tables, donne les mêmes textes à un moteur de recherche et garde les identifiants des
résultats.
