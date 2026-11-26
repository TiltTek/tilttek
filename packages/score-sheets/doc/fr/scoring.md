<!-- phil-dev source-sha256=488e3a5a55c27da6589819b6c354757608d9a4b8130591355af6f34467cb7ee7 -->
# Pointage

## Saisies

`Entries` contient les participants d'une partie — de 1 à 100, chacun une seule fois, dans l'ordre où la feuille les
montre —, ses équipes — jusqu'à 50, chaque membre étant participant d'une seule équipe au plus — et les valeurs saisies
jusqu'ici. C'est une valeur :

```php
$entries = new Entries([$ada, $bo], [new Team(new TeamId('harbour-masters'), [$ada, $bo])]);
$entries = $entries->with($sheet, $ada, 'boats', 5);     // entered, or corrected
$entries = $entries->without($sheet, $ada, 'boats');     // erased, as if never entered
```

`with()` refuse, avec `InvalidEntry`, un participant ou une catégorie que la feuille ne connaît pas, une catégorie
calculée, et une valeur hors de ses bornes. Une valeur non saisie compte pour 0 ; `isComplete()` dit si chaque
participant a une valeur dans chaque catégorie saisie.

Les identifiants de participant, d'équipe et de partie sont opaques : de 1 à 64 lettres ASCII, chiffres, points,
soulignés et traits d'union, en commençant par une lettre ou un chiffre. Ils renvoient aux comptes de l'application et
ne contiennent jamais un nom.

## Le tableau des scores

`Scoring\Scorer::score($sheet, $entries)` recalcule tout, à chaque saisie :

1. Chaque catégorie dans l'ordre déclaré : une valeur saisie, ou l'opération sur les valeurs calculées avant elle.
2. Le total de chaque participant : la somme des catégories qui comptent.
3. Les rangs : le total le plus élevé d'abord, puis les bris d'égalité dans l'ordre ; les participants encore à égalité
   partagent leur rang, et les rangs qu'ils occupent sont sautés — 1, 1, 3.
4. Le score de chaque équipe, classé de la même façon par son total.

```php
$board = $scorer->score($sheet, $entries);

foreach ($board->participants as $score) {           // in rank order
    echo $score->rank, ' ', $score->participant->value, ' ', $score->total, "\n";
}
```

Le total d'un participant est toujours la somme des valeurs des catégories qui comptent ; les tests le vérifient sur des
saisies au hasard.

## Explications

Chaque `ParticipantScore` porte une `Step` par catégorie : l'opération, ce qu'elle a lu, et la valeur. Les catégories
sont nommées par leur clé ; les constantes de la déclaration, par un nom qui commence par `#`, qu'aucune clé ne peut
prendre.

```php
['category' => 'set_points', 'operation' => 'product', 'operands' => ['sets' => 2, '#factor' => 4], 'value' => 8]
['category' => 'fleet', 'operation' => 'majority', 'operands' => ['boats' => 5, '#place' => 2], 'value' => 3]
['category' => 'lighthouse', 'operation' => 'bonus', 'operands' => ['lanterns' => 12, '#threshold' => 10], 'value' => 5]
```

L'application transforme les étapes en la phrase qu'elle affiche, dans la langue du joueur.

## Scores d'équipe

Chaque `TeamScore` contient le total de chaque membre, dans l'ordre de l'équipe, la façon dont ils se combinent — somme
ou meilleur — et le résultat : tout ce qu'il faut pour afficher `harbour-masters: 58 = u-101 33 + u-102 25`.
