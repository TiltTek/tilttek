<!-- phil-dev source-sha256=a7ce68757a3586811e73b14b73b146df9843bcc8f1c855a6ec6b11af9c8c74e5 -->
# Importations

Deux importateurs transforment des générateurs écrits pour d'autres outils en documents de la bibliothèque : des
grammaires Tracery, et des tables dans le sous-ensemble de la syntaxe de Perchance qui n'a pas de JavaScript. Chacun
retourne un `Imported` : son `document` (des listes et des générateurs, tels que `Reader` les lit et que les tiendrait
un fichier YAML ou JSON) et son `start`, le générateur par où commencer les tirages.

```php
use Symfony\Component\Yaml\Yaml;
use Tilttek\Generator\Definition\Attribution;
use Tilttek\Generator\Generator;
use Tilttek\Generator\Import\TraceryImporter;

$imported = new TraceryImporter('story.', new Attribution(source: 'A grammar shared by its author', licence: 'CC0-1.0'))
    ->import(file_get_contents('grammar.json'));

$generator = new Generator($imported->catalog());
$generator->draw($imported->start)->first();

file_put_contents('config/generators/story.yaml', Yaml::dump($imported->document, 6));
```

Tous deux prennent un préfixe placé devant chaque nom, pour qu'une importation ne rencontre pas les noms du catalogue,
et une `Attribution` donnée à chaque liste et à chaque générateur qu'ils font, que les tirages donnent ensuite avec leurs
résultats ([Listes et générateurs](lists-and-generators.md#dou-vient-le-texte)). Ce qu'ils font est vérifié comme tout
autre document à la construction du `Generator`, et tiré dans les [limites](drawing.md#limites) ; une grammaire ou des
tables venues de quelqu'un d'autre s'importent comme n'importe quelle autre donnée.

Un problème est une `InvalidDefinition` qui dit où il est : `tracery > origin > 0: "#" is not closed`,
`perchance > line 12: "[a.joinItems(1)]" is not supported: …`.

## Tracery

Une grammaire Tracery est un objet JSON de symboles, chacun avec une règle ou une séquence de règles :

```json
{
    "origin": ["#[hero:#name#]story#"],
    "story": ["#hero.capitalize# met #animal.a#. #hero# was glad."],
    "name": ["ada", "bo"],
    "animal": ["owl", "ox"]
}
```

| Tracery | Ce qu'il devient |
|---|---|
| Un symbole dont les règles sont du texte brut | Une liste, chaque règle étant une entrée. |
| Tout autre symbole, et le symbole par où commencer | Un générateur, chaque règle étant un gabarit de poids 1, dans la langue de l'importation (`en` par défaut). |
| `#symbol#` | `pick('symbol')` pour une liste, `generate('symbol')` pour un générateur. |
| `.capitalize`, `.capitalizeAll`, `.a`, `.s` | Les filtres `upper_first`, `title`, `article('indefinite')` et `plural`. |
| `[name:#rule#]`, `[name:one,other]` | `{% set v_name = … %}` : la variable prend le texte de la règle, ou l'une de ses règles au hasard, pour le reste de la règle. |
| `#name#` après une action | La variable ; elle prend la place d'un symbole du même nom. |
| `\#`, `\[`, `\\` | Le caractère tel qu'il est écrit. |

Un générateur tiré après une action reçoit les variables qu'il lit (lui-même, ou tout générateur qu'il tire) par
`generate('story', with: {v_hero: v_hero})` : l'importation les trouve à travers tous les niveaux, si bien que `#hero#`
dans un symbole tiré trois niveaux plus bas est le héros fixé au sommet. L'importation commence à `origin`, ou au
symbole que nomme le quatrième argument.

Refusés : un symbole inconnu, une variable que le symbole de départ lit avant qu'une action la fixe, `POP`, une action
sans valeur (les fonctions de Tracery), une action dans la valeur d'une autre action, les autres modificateurs
(`.ed`, `.possessive`, `.replace(…)` et leurs semblables), un nom qui n'est pas un nom de la bibliothèque, un document
de plus de `TraceryImporter::MAX_BYTES` octets ou `MAX_SYMBOLS` symboles, un symbole de plus de `MAX_SYMBOLS` règles, et
des actions et des balises imbriquées sur plus de `MAX_DEPTH` niveaux. Une variable garde sa valeur pour le reste de sa
règle : une action dans une balise ne se termine pas avec la balise comme dans Tracery.

## Perchance

Les tables lues sont le sous-ensemble de la syntaxe de Perchance qui n'a pas de JavaScript, tel que la spécification du
projet sous Apache-2.0 le délimite peut-être :

```text
title
  Beasts

animal
  dog
  cat ^2
  owl ^0.5      // a comment

coat colour
  red
  blue

output
  A {big|small^3} [coat colour] [animal] with {1-6} legs and 2d6+1 teeth.
  [animal.selectUnique(2)] and [animal.selectMany(3)]
```

| Perchance | Ce qu'il devient |
|---|---|
| Une ligne sans indentation | Une section ; `title` nomme les tables et n'est pas une section. |
| Une ligne indentée d'une tabulation ou de deux espaces | Une entrée de la section au-dessus. |
| Une section dont les entrées sont du texte brut | Une liste, chaque entrée avec son poids. |
| Toute autre section, et celle qui est tirée en premier (`output`, ou la première) | Un générateur, chaque entrée étant un gabarit avec son poids. |
| `^2`, `^0.5` à la fin d'une entrée ou d'une partie d'un choix | Son poids : un nombre positif d'au plus `MAX_WEIGHT` ; les poids d'une section ou d'un choix sont ramenés à des nombres entiers dans les mêmes proportions, trois chiffres après la virgule gardés. |
| `[name]` | `pick('name')` ou `generate('name')`. |
| `[name.selectUnique(3)]` | Trois entrées différentes, jointes par des virgules. |
| `[name.selectMany(3)]` | Trois entrées qui peuvent se répéter, jointes par des virgules. |
| `{a\|b^2\|c}` | Une partie, choisie selon le poids ; les parties peuvent contenir des références et des choix, imbriqués sur `MAX_DEPTH` niveaux au plus. |
| `{1-6}` | `between(1, 6)`. |
| `2d6+1`, `d20` | `roll('2d6+1')`, quand l'expression se lance avec `tilttek/dice` ; sinon, du texte. |
| `//` au début d'une ligne ou après une espace | Un commentaire, jusqu'à la fin de la ligne ; `https://` garde ses barres obliques. |
| `\[`, `\{`, `\|`, `\^` | Le caractère tel qu'il est écrit. |

Les noms se comparent sans tenir compte de la casse, leurs espaces étant lues comme des traits d'union : `[Coat Colour]`
est la section `coat-colour`. Un fichier Markdown se lit dans ses blocs délimités marqués `perhaps` ou `perchance`
quand il en a, et en entier sinon.

Refusés : une section inconnue, une référence à une section sans entrées, un crochet ou une accolade qui n'est pas
fermé, toute autre expression entre crochets (variables, affectations, autres méthodes), un choix d'une seule partie,
un intervalle vide, une entrée hors d'une section ou indentée d'une seule espace, un poids qui n'est pas un nombre
positif, une sélection de moins de un ou de plus de `MAX_SELECT` entrées, un texte qui n'est pas en UTF-8, et des tables
de plus de `PerchanceImporter::MAX_BYTES` octets, `MAX_SECTIONS` sections ou `MAX_SECTIONS` entrées dans une section.
