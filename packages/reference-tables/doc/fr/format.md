<!-- phil-dev source-sha256=a80655189a2e0ea54e353399559f53730efb974ebb4ac9ee915a556ab141e067 -->
# Le format

Un site déclare ses tables dans des documents du format `tilttek-reference-tables/1`, écrits en YAML ou en JSON.
`Format\TableReader` les lit :

| Appel | Lit |
|---|---|
| `load($path)` | Un fichier, `.yaml`, `.yml` ou `.json`, sa taille vérifiée avant sa lecture. |
| `parse($text, $syntax)` | Un texte, dans la syntaxe `Syntax::Yaml` ou `Syntax::Json` donnée. |
| `decode($text, $syntax)` | Un texte décodé en tableau, pas encore lu comme des tables. |
| `catalogue($document)` | Un tableau décodé. |

Un document fait 2 Mio au plus, ou la taille donnée au constructeur du lecteur. Le YAML est lu par `symfony/yaml`, les
objets et les autres types PHP refusés ; le JSON est lu jusqu'à une profondeur de 64 au plus.

## Le document

```yaml
format: tilttek-reference-tables/1
locale: en
system: lanterns
source:
  title: Lanterns Quick Reference
  licence: CC0-1.0
  author: The Lanterns writers
tables:
  - key: reaction
    names: {en: Reaction, fr: Réaction}
    category: social
    page: p. 12
    die: 2d6
    columns:
      - {key: attitude, names: {en: Attitude, fr: Attitude}}
    rows:
      - {range: "≤2", attitude: {en: Hostile, fr: Hostile}}
      - {range: 3-11, attitude: {en: Uncertain, fr: Incertain}}
      - {range: 12+, attitude: {en: Helpful, fr: Serviable}}
```

| Champ | Obligatoire | Ce qu'il contient |
|---|---|---|
| `format` | oui | `tilttek-reference-tables/1`. |
| `locale` | non | La locale d'un texte écrit en simple chaîne ; `en` si elle est omise. |
| `system` | non | La clé du système de jeu de chaque table qui n'en nomme aucun. |
| `source` | non | La source de chaque table qui n'en nomme aucune. |
| `tables` | oui | De 1 à 2 000 tables. |

## Une table

| Champ | Obligatoire | Ce qu'il contient |
|---|---|---|
| `key` | oui | La clé de la table. |
| `names` | oui | Un texte, ou des textes par locale. |
| `description` | non | Un texte, ou des textes par locale. |
| `system` | non | La clé de son système de jeu, à la place de celle du document. |
| `category` | non | La clé de sa catégorie. |
| `page` | non | Où la source l'imprime ; un nombre est lu comme son texte. |
| `source` | non | Sa source, à la place de celle du document ; l'une des deux est obligatoire. |
| `lookup` | non | `key` ou `range` ; `range` si omis. |
| `die` | non | Le dé d'une table trouvée par plage : `2d6`, `d%`, `1d20+1`. |
| `columns` | oui | De 1 à 32 colonnes. |
| `rows` | oui | De 1 à 1 000 lignes. |

Une colonne a une `key`, des `names` — un texte ou des textes par locale — et un `kind`, `text`, `number` ou `dice` ;
`text` si omis.

Une ligne a une `key`, dans une table trouvée par clé, ou une `range`, dans une table trouvée par plage, et un champ
pour chaque cellule qu'elle contient, nommé par la clé de sa colonne :

| Type | Écrit comme |
|---|---|
| `text` | Un texte, ou des textes par locale ; un nombre est lu comme son texte. |
| `number` | Un entier. |
| `dice` | Une expression de dés ; un nombre est lu comme son texte, une constante. |

Une plage s'écrit comme la liste [Tables](tables.md#lignes-clés-et-plages). Quand le dé de la table est un d100, `00`
vaut 100. En YAML, mettez entre guillemets une plage qui commence par `≤`, `<` ou `-`, pour qu'elle soit lue comme un
texte.

## Une source

| Champ | Obligatoire | Ce qu'il contient |
|---|---|---|
| `title` | oui | Le titre de la source. |
| `licence` | oui | L'une de `CC-BY-4.0`, `CC-BY-3.0`, `CC-BY-SA-4.0`, `CC0-1.0`, `ORC` et `owned`, la liste fermée de `tilttek/rpg-systems`. |
| `attribution` | pour une licence qui en demande une | La mention que la page montre. |
| `author` | non | Qui détient la source. |
| `url` | non | Où elle est publiée, une adresse http ou https absolue. |

## Refus

Chaque refus est une `InvalidDocument` dont le message commence par l'emplacement du champ, puis ce qui ne va pas à cet
endroit :

```text
tables[0].rows[3].range: the range 5–3 runs backwards.
tables[1]: the rows "1–3" and "3–4" of the table "wandering" overlap.
source.licence: the licence is one of CC-BY-4.0, CC-BY-3.0, CC-BY-SA-4.0, CC0-1.0, ORC, owned.
```

Un champ que le format ne nomme pas est refusé, à tous les niveaux : un `colums` mal écrit est une erreur, pas une table
sans colonnes. Le refus d'une table, d'une colonne, d'une ligne, d'une cellule ou d'une source garde l'exception qu'il
remplace comme exception précédente.
