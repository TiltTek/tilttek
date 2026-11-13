<!-- phil-dev source-sha256=d82cd154d0f60a9ddc707d6d51e2a3007d6894ced2dca2c2a67b904974dc6988 -->
# Définir des ensembles

## Membres

Un `SetDefinition` contient son code, sa version et ses membres, de 1 à 1 000, chacun nommé une seule fois par un
identifiant stable que donne l'application : le code d'un badge, d'un objet, d'un autocollant. L'ordre des membres est
celui dans lequel l'ensemble est présenté, et celui de `Evaluation::$owned` et de `Evaluation::$missing`.

| Valeur | Forme |
|---|---|
| Le code d'un ensemble, d'un palier ou d'un genre de récompense | De 1 à 64 lettres minuscules, chiffres, `_`, `.` ou `-`, commençant par une lettre ou un chiffre |
| Un membre | De 1 à 255 lettres ASCII, chiffres, `_`, `.`, `:`, `/` ou `-`, commençant par une lettre ou un chiffre ; jamais de `@` |
| Une version | De 1 à 1 000 000 |

## Paliers

Un `Tier` compte les membres qu'un détenteur possède à un niveau ou à un niveau supérieur : `new Tier('bronze',
required: 5)` est *5 sur 20*, `new Tier('gold', level: 2)` est *les 20 au niveau 2*. Un ensemble déclare jusqu'à 20
paliers, du plus facile au plus difficile : le niveau et le nombre requis de chaque palier ne sont jamais inférieurs à
ceux du palier précédent, et l'un des deux au moins est supérieur, de sorte qu'un détenteur qui atteint un palier a
atteint tous les paliers qui le précèdent. Un nombre requis supérieur à la taille de l'ensemble est refusé.

Un ensemble refait à des niveaux supérieurs — une variante brillante, un deuxième tirage — est un seul ensemble avec un
palier par niveau ; l'application dit à quel niveau un détenteur possède chaque membre.

La complétion de l'ensemble lui-même consiste à posséder chaque membre, à n'importe quel niveau. Elle n'a besoin
d'aucun palier.

## Récompenses

Un `Reward` est ce qu'un ensemble ou un palier déclare, sans jamais l'accorder : un genre — `points`, `badge`, `item`,
ou tout autre sur lequel l'application se branche —, une référence opaque de 1 à 255 octets sans espace, et une
quantité de 1 à 1 000 000 000. Un ensemble et chacun de ses paliers en déclarent jusqu'à 20. Les événements les
portent ; l'application les accorde.

```php
new SetDefinition('dragons', 1, ['red', 'blue', 'green'],
    tiers: [new Tier('first', required: 1, rewards: [new Reward('points', 'xp', 5)])],
    rewards: [new Reward('badge', 'dragon-tamer'), new Reward('item', 'golden-egg')],
);
```

## Versions

`Sets::define()` ajoute une version d'un ensemble :

- une version jamais définie est ajoutée, et `define()` retourne vrai ;
- la même version avec le même contenu retourne faux : définir les ensembles de la configuration à chaque démarrage est
  sans danger ;
- la même version avec un autre contenu lance `DefinitionConflict` : un ensemble modifié reçoit une nouvelle version ;
- une version plus ancienne que la dernière lance `DefinitionConflict`, sauf si elle est déjà définie avec le même
  contenu.

`Sets::definition($code)` donne la dernière version, `Sets::definition($code, $version)` une version donnée. La
progression est toujours mesurée par rapport à la dernière version ; les complétions gardent la version sous laquelle
elles ont été obtenues.

## Lire une définition

`SetDefinition::fromArray()` lit le tableau qu'écrit `toArray()` — depuis la configuration de l'application, un
formulaire ou une colonne de base de données —, et `fromJson()` lit du JSON d'au plus 1 Mio, imbriqué au plus
profondément qu'une définition. Les deux refusent une clé inconnue, une clé manquante et une valeur du mauvais type
avec `InvalidSet`, jamais une autre exception.

```json
{
    "code": "starter-dice",
    "version": 2,
    "members": ["d4", "d6", "d8", "d10", "d12", "d20"],
    "tiers": [{"code": "half", "required": 3}, {"code": "shiny", "level": 2}],
    "rewards": [{"kind": "points", "reference": "xp", "quantity": 50}]
}
```

`level` vaut 1 par défaut, `required` vaut tous les membres, `quantity` vaut 1, et `tiers` et `rewards` valent aucun.
