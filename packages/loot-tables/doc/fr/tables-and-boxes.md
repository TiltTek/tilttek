<!-- phil-dev source-sha256=5c67ebfa9ce053c9e3c8938cb564d26c373760bf795c5615ae9fc95582d2bac2 -->
# Tables et boîtes

## Entrées

Une `LootEntry` est une chose qu'une table peut donner :

| Propriété | Contrat |
|---|---|
| `key` | 1 à 64 lettres minuscules, chiffres, `_` ou `-`, commençant par une lettre ou un chiffre |
| `name` | 1 à 1000 caractères UTF-8, non vide : ce qu'affiche un reçu, ou le texte entier d'une entrée de liste |
| `image` | le chemin ou l'URL de son image, 1 à 2048 caractères ASCII visibles, ou `null` |
| `slot` | où elle va sur ce qui en est fait (`head`, `left-hand`, `belt`), une clé, ou `null` |

## Tables

Une `LootTable` est lancée avec un seul dé : elle a autant d'entrées que le dé a de faces, de 2 à 1000, l'entrée de la
face 1 en premier. Six entrées lancent un d6, dix un d10, vingt un d20. Sa clé suit la règle de celle d'une entrée, son
nom compte 1 à 200 caractères, et chaque clé d'entrée n'y paraît qu'une fois.

- `sides()` : les faces de son dé.
- `entry($face)` : l'entrée d'une face, `InvalidLoot` pour une face que le dé n'a pas.
- `LootTable::ofTexts($key, $name, $texts)` : une table de textes, chacun étant une entrée dont la clé est sa face ; la
  liste dans laquelle un texte est tiré, comme une épitaphe parmi celles qu'offre un produit.

## Boîtes

Une `LootBox` est ce qu'un produit déclare lancer : 1 à 10 tables, dans l'ordre, chacune lancée une fois avec son
propre dé. Six tables de six entrées lancent 6d6 ; une table d20 et deux tables d8 lancent `1d20, 1d8, 1d8`. Une table
peut revenir plus d'une fois. Dix est le nombre d'instructions que `tilttek/dice` lance ensemble par défaut.

- `dice()` : les faces de chaque dé, dans l'ordre des tables.
- `pool()` : les dés tels que `tilttek/dice` les lance, une instruction par table.
- `LootBox::single($table)` : une boîte d'une seule table, pour un texte tiré d'une liste.

## Catalogues

Un `LootCatalog` contient les tables et les boîtes d'une application, par clé. Il refuse une clé énumérée deux fois,
une boîte qui lance une table qu'il ne contient pas, et une boîte dont `DrawText` ne pourrait pas écrire les tirages en
1000 caractères : les faces, les séparateurs et le plus long nom de chaque table comptent. `box()` et `table()` lèvent
`UnknownLoot` pour une clé qu'ils ne connaissent pas ; `hasBox()`, `boxes()` et `tables()` les énumèrent dans l'ordre où
elles ont été données.

`CatalogReader::read()` en construit un à partir de simples tableaux, tels que les donne un document YAML ou JSON
décodé :

```yaml
tables:
    headgear:
        name: Headgear
        entries:
            - { key: helmet, name: Helmet, image: /loot/helmet.png, slot: head }
            - { key: hood, name: Hood, slot: head }
boxes:
    starter:
        name: Starter box
        tables: [headgear, headgear]
```

Une valeur illisible est refusée avec `InvalidLoot`, qui nomme son chemin : `tables.headgear.entries.0.key is a text.`
Une clé numérique est lue comme son texte.
