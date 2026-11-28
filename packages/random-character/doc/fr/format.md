<!-- phil-dev source-sha256=32a7db4533a217432bb23b6099b88c1a6797f66d718568ae9ab83e023d984cee -->
# Le format

Un document de règles de création est un dictionnaire YAML ou JSON qui nomme le format `tilttek-random-character/1`.
`Format\RulesReader` le lit en un `RulesCatalogue` :

```php
use Tilttek\RandomCharacter\Format\RulesReader;
use Tilttek\RandomCharacter\Format\Syntax;

$catalogue = new RulesReader()->load('rules/lanterns.yaml');          // .yaml, .yml or .json
$catalogue = new RulesReader()->parse($text, Syntax::Json);
$catalogue = new RulesReader()->catalogue($decodedDocument);
```

```yaml
format: tilttek-random-character/1
system: lanterns
rules:
  - key: lanterns.wanderer
    stats:
      method: rolled-array
      roll: 1d4+1
      attributes: [grit, wits, glow]
    fields:
      - { key: lineage, generator: lanterns.lineage }
      - { key: name, generator: 'lanterns.name.{lineage}' }
      - { key: traits, generator: lanterns.trait, count: 2, unique: true }
      - { key: age, roll: 2d10+14 }
```

## Le document

| Champ | Requis | Contient |
|---|---|---|
| `format` | Oui | `tilttek-random-character/1`. |
| `system` | Non | Le système des règles qui ne nomment pas le leur. |
| `rules` | Oui | Une liste de 1 à 1 000 jeux de règles. |

## Les règles

| Champ | Requis | Contient |
|---|---|---|
| `key` | Oui | La clé des règles. |
| `system` | Quand le document n'en nomme aucun | La clé du système dans `tilttek/rpg-systems`. |
| `stats` | Quand il n'y a pas de champs | Les statistiques, ci-dessous. |
| `fields` | Quand il n'y a pas de statistiques | Une liste de 1 à 64 champs, ci-dessous, dans l'ordre où ils sont tirés. |

## Les statistiques

| Champ | Requis | Contient |
|---|---|---|
| `method` | Oui | `each`, `rolled-array` ou `array`. |
| `attributes` | Oui | Une liste de 1 à 32 clés d'attributs du système. |
| `roll` | Pour `each` et `rolled-array` | Le lancer de chaque valeur, dans une notation de `tilttek/dice`. |
| `values` | Pour `array` | Un nombre entier par attribut. |

## Les champs

| Champ | Requis | Contient |
|---|---|---|
| `key` | Oui | La clé du champ. |
| `generator` | Celui-ci ou `roll` | Le nom du générateur, avec ses marqueurs : `names.{lineage}`. |
| `roll` | Celui-ci ou `generator` | Le lancer, dans une notation de `tilttek/dice`. |
| `count` | Non | Le nombre de valeurs, de 1 à 16 ; 1 par défaut. |
| `unique` | Non | Pour un champ tiré, `true` pour des résultats différents ; `false` par défaut. |

## Refus et bornes

Chaque refus est une `InvalidDocument` dont le message commence par l'emplacement du champ : `rules[0].fields[2].count: a
whole number is expected.`, `rules[1].stats: the stats of a fixed array have no roll, and one value per attribute.`
Un dictionnaire ne contient que les champs de son tableau ; les étiquettes YAML qui construisent des objets et le JSON
imbriqué sur 64 niveaux sont refusés. Un document est lu jusqu'à 1 Mio par défaut, `RulesReader::MAX_BYTES` ; le
constructeur accepte une autre borne.

Le lecteur vérifie ce que dit un document, pas ce qu'il nomme : si le système a les attributs, et si les générateurs
existent, c'est `CharacterRoller::check()` qui le vérifie contre les systèmes et les générateurs de l'application.
