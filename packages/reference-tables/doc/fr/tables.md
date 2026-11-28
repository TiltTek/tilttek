<!-- phil-dev source-sha256=2ada126b3951742e877f53857c7c1586a1ac843203364b2806738a3c427f8c77 -->
# Tables

## Une table

Une `ReferenceTable` est une table d'un système de jeu telle qu'un maître de jeu la lit :

| Champ | Ce qu'il contient |
|---|---|
| `key` | La clé de la table dans son catalogue, `reaction` : des lettres minuscules, des chiffres, `_`, `-` et des points isolés, 96 caractères au plus. |
| `names` | Son nom par locale, une `Translations` de `tilttek/rpg-systems` ; `name($locale)` le lit avec les replis de `Translations::in()`. |
| `provenance` | Sa source : titre, détenteur, adresse, licence et attribution, une `Provenance` de `tilttek/rpg-systems`. |
| `columns` | De 1 à 32 `Column`, chaque clé une seule fois. |
| `lookup` | `Lookup::Key` ou `Lookup::Range` : comment ses lignes sont trouvées. |
| `rows` | De 1 à 1 000 `Row`, dans l'ordre où la table les imprime. |
| `die` | Le dé avec lequel une table trouvée par plage se lance, une instruction de `tilttek/dice` gardée dans sa notation canonique : `d%` devient `1d100`, `d20 adv` devient `2d20kh1`. Nul pour une table seulement consultée. |
| `system` | La clé du système de jeu auquel la table appartient, `lanterns` ; facultative. |
| `category` | Une clé qui regroupe les tables à l'écran, `combat`, `social` ; facultative. |
| `page` | Où la source l'imprime, `p. 254` : une ligne de 200 caractères au plus ; facultative. |
| `description` | À quoi sert la table, par locale ; facultative. |

Une table qui enfreint une règle est refusée avec `InvalidTable` quand elle est construite : un dé sur une table trouvée
par clé, un dé de deux instructions, une ligne du mauvais type, une cellule d'une colonne que la table n'a pas ou d'un
autre type que sa colonne, une clé en double, deux plages qui se chevauchent.

## Colonnes et cellules

Une `Column` a une clé, qui commence par une lettre et n'est jamais `key` ni `range` — les noms que donne un document aux
champs propres de la ligne —, un titre par locale et un `ColumnKind` :

| Type | Une cellule contient | Se lit avec |
|---|---|---|
| `Text` | Un texte par locale, de 4 000 caractères et 64 locales au plus. | `translations()`, `display($locale)` |
| `Number` | Un entier de −1 000 000 000 à 1 000 000 000. | `toInt()`, `display()` |
| `Dice` | Une expression de dés de `tilttek/dice`, gardée dans sa notation canonique : `d4+1` devient `1d4+1`. | `notation()`, `pool()`, `display()` |

`Cell::text()`, `Cell::number()` et `Cell::dice()` les fabriquent ; lire une cellule comme un autre type lève
`LookupRefused`. Une ligne peut omettre une cellule : `Row::has()` dit si elle en contient une, `Row::cell()` lève
`UnknownTable` pour une cellule absente, et `Row::display()` donne une chaîne vide.

## Lignes, clés et plages

Une `Row` a soit une clé, dans une table trouvée par clé, soit une `Range`, dans une table trouvée par plage — jamais
les deux — et ses cellules par clé de colonne. `Row::keyed()` et `Row::ranged()` les fabriquent ; `label()` donne la
clé, ou la plage comme une table l'imprime.

Une `Range` couvre ses deux bornes et tous les entiers entre elles, dans ±1 000 000 :

| Écrite | Couvre | Imprimée |
|---|---|---|
| `12` | 12 | `12` |
| `3-5`, `3–5`, `3—5`, `3 – 5` | 3 à 5 | `3–5` |
| `-3--1` | −3 à −1 | `-3–-1` |
| `21+` | 21 et plus | `21+` |
| `≤0`, `<= 0` | 0 et moins | `≤0` |
| `96-00` sur un d100 | 96 à 100 | `96–100` |

`Range::parse()` lit une plage de 32 octets au plus ; son deuxième argument dit que la table lance un d100, sur lequel
`00` vaut 100 — sur tout autre dé, `00` vaut 0. `new Range($min, $max)` et `Range::of($value)` en construisent une en
code ; `contains()` et `overlaps()` les comparent.

## Consultations

| Consultation | Appel | Donne | Refuse |
|---|---|---|---|
| Par clé | `row('plate')` | La ligne de cette clé. | `UnknownRow` quand aucune ligne ne l'a ; `LookupRefused` sur une table trouvée par plage. |
| Par clé | `hasRow('plate')` | Si une ligne a cette clé ; faux sur une table trouvée par plage. | |
| Par plage | `lookup(7)` | La ligne dont la plage couvre la valeur. | `UnknownRow` quand aucune ne la couvre ; `LookupRefused` sur une table trouvée par clé. |
| Par plage | `position(7)` | La position de la ligne dans `$rows`, à partir de 0, ou nul. | `LookupRefused` sur une table trouvée par clé. |
| Par plage | `bounds()` | La plus basse et la plus haute valeur que couvrent les plages. | `LookupRefused` sur une table trouvée par clé. |
| Par plage | `gaps()` | Les valeurs entre la plus basse et la plus haute plage qu'aucune ligne ne couvre, en plages, dans l'ordre. | `LookupRefused` sur une table trouvée par clé. |

Une consultation par clé lit un index ; une consultation par plage cherche dans les plages, triées une fois à la
construction de la table, par dichotomie : une table de 1 000 lignes répond en dix comparaisons. Les lignes se donnent
dans n'importe quel ordre ; `rows` garde cet ordre.

Une table de niveaux qui saute un niveau exprès et une table lancée qui a un trou par erreur se ressemblent : `gaps()`
liste les trous, pour une vérification à l'écriture des tables, et un lancer qui tombe dans l'un d'eux est refusé.

## Le catalogue

Un `TableCatalogue` détient jusqu'à 2 000 tables, chaque clé une seule fois, dans l'ordre où elles ont été données :

- `get()` et `has()` trouvent une table par clé, `get()` levant `UnknownTable` ;
- `tables()` les donne, celles d'un système ou d'une catégorie sur demande ;
- `categories()` donne les catégories, chacune une fois, dans l'ordre de leur première apparition, d'un système sur
  demande ;
- `with()` donne un catalogue avec d'autres tables après les siennes, à partir des documents de plusieurs sources ;
- `provenances()` donne les sources de toutes les tables, ou des tables qu'une page montre, chacune une fois :
  l'`AttributionRenderer` de `tilttek/rpg-systems` les transforme en avis que la page affiche.

```php
use Tilttek\RpgSystems\Attribution\AttributionRenderer;

$notices = new AttributionRenderer()->html($catalogue->provenances(['reaction', 'armor']));
```
