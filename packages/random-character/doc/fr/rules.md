<!-- phil-dev source-sha256=ed5d1c61d85f57d537d7c1ff77d4c841bf25533951ab386e6505808760631717 -->
# Règles de création

`CreationRules` dit comment un système fait un personnage au hasard : la clé des règles, la clé du système dans
`tilttek/rpg-systems`, les `Stats` qui remplissent ses attributs, et les `Field` tirés ensuite, dans l'ordre. Des règles
ont des statistiques, des champs, ou les deux.

```php
use Tilttek\RandomCharacter\CreationRules;
use Tilttek\RandomCharacter\Field;
use Tilttek\RandomCharacter\StatMethod;
use Tilttek\RandomCharacter\Stats;

$rules = new CreationRules('lanterns.wanderer', 'lanterns', new Stats(StatMethod::RolledArray, ['grit', 'wits', 'glow'], '1d4+1'), [
    Field::generated('lineage', 'lanterns.lineage'),
    Field::generated('name', 'lanterns.name.{lineage}'),
    Field::generated('traits', 'lanterns.trait', count: 2, unique: true),
    Field::rolled('age', '2d10+14'),
]);
```

Les clés des règles, des systèmes et des attributs s'écrivent comme `tilttek/rpg-systems` les écrit : de 1 à 96 lettres
ASCII minuscules, chiffres, `_`, `-` et points simples entre eux. Des règles contiennent au plus 64 champs, chaque clé
une seule fois.

## Les statistiques

`Stats` nomme les attributs qu'elles remplissent, de 1 à 32, chacun une seule fois, dans l'ordre où le personnage les
liste, et la manière :

| `StatMethod` | Écrit | Les valeurs |
|---|---|---|
| `Each` | `each` | Le lancer est fait pour chaque attribut à tour de rôle, dans l'ordre des règles : 3d6 dans l'ordre. |
| `RolledArray` | `rolled-array` | Le lancer est fait une fois par attribut, et les valeurs sont distribuées aux attributs au hasard : 4d6 en retirant le plus bas, arrangé par la chance. |
| `Array` | `array` | Les valeurs fixes, une par attribut, sont distribuées aux attributs au hasard : un tableau standard. |

Les deux méthodes lancées acceptent un lancer dans toute notation que lit `tilttek/dice` — `3d6`, `4d6kh3`, `1d4+1` —
conservé sous sa forme canonique, `Stats::$roll` ; elles n'acceptent aucune valeur fixe. Un tableau fixe n'accepte aucun
lancer et une valeur par attribut, chacune de -1 000 000 à 1 000 000. Un lancer doit donner un nombre : un lancer dont la
première instruction donne un texte est refusé quand il est fait.

Les bornes appartiennent au système : un attribut de `tilttek/rpg-systems` peut avoir un minimum et un maximum, et une
valeur hors de ces bornes est refusée — quand les règles sont vérifiées pour un tableau fixe, dont chaque valeur doit
convenir à chaque attribut, et quand le personnage est tiré pour les autres méthodes.

## Les champs

Un `Field` est une valeur du personnage en plus de ses attributs, sous une clé formée d'une lettre ASCII minuscule suivie
d'au plus 63 lettres minuscules, chiffres ou `_`. Il donne une valeur, ou une liste de 2 à 16 quand son `count` en
demande davantage.

- `Field::generated($key, $generator, $count = 1, $unique = false)` tire d'un générateur de `tilttek/generator`. Un
  résultat est un texte, ou un tableau pour un générateur qui donne des résultats structurés ; plusieurs résultats forment
  une liste. Un champ unique demande au générateur des résultats différents, et le tirage échoue quand il en trouve trop
  peu.
- `Field::rolled($key, $notation, $count = 1)` lance des dés : un lancer est un entier, plusieurs forment une liste
  d'entiers.

### Générateurs nommés par des champs précédents

Le nom du générateur d'un champ peut contenir des marqueurs, chacun remplacé par la valeur d'un champ précédent quand le
personnage est tiré :

| Marqueur | Lit |
|---|---|
| `{lineage}` | La valeur du champ `lineage` : un texte, ou un entier écrit en chiffres. |
| `{lineage.key}` | La valeur `key` du résultat structuré que le champ `lineage` a tiré. |

`names.{lineage}` tire de `names.river` quand la lignée est sortie `river`. Un marqueur nomme un champ qui précède celui
qui le contient, et un champ qui donne une seule valeur ; tout autre cas est refusé à la construction des règles. Une
fois remplacé, le nom doit être celui d'un générateur — lettres, chiffres, `_` et `-` en parties jointes par des points —
et d'un générateur du catalogue ; une valeur qui ne donne aucun texte, qui contient une espace ou qui nomme un générateur
inexistant fait échouer le tirage. Écrivez le générateur qu'un marqueur lit de sorte qu'il donne des clés, `river`, ou des
résultats structurés avec une clé, plutôt que le texte qu'un joueur lit.

## Le catalogue

`RulesCatalogue` contient jusqu'à 1 000 jeux de règles par clé, chaque clé une seule fois : `get()`, `has()`, `all()` dans
l'ordre donné, et `forSystem()` pour les règles d'un système. C'est une valeur immuable que les requêtes partagent.
