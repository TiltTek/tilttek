<!-- phil-dev source-sha256=42dc145dc40d19bca3463a52a07a3f1a8b4465c5a06403f14d3d4a18826dd542 -->
# Cartes

Un générateur doté d'une `map` retourne des cases ou des nœuds que l'application dessine. Chacun est rempli par la même
`cell` (un gabarit, ou `{generate: name}`), qui voit son emplacement et les variables du générateur.

## Cartes carrées et hexagonales

```yaml
generators:
  village:
    map: { layout: square, width: 4, height: 3, cell: "{{ pick('village.place') }}" }
  region:
    map: { layout: hex, width: 8, height: 6, cell: { generate: region.terrain } }
```

Une carte carrée donne à chaque case `x` et `y`. Une carte hexagonale est faite de rangées où chaque rangée impaire est
décalée d'une demi-case vers la droite. Elle donne à chaque case ses coordonnées décalées `col` et `row` et ses
coordonnées axiales `q` (`col - floor(row / 2)`) et `r` (`row`). Le gabarit de la case voit ces variables.

```php
[
    'layout' => 'hex', 'width' => 8, 'height' => 6,
    'cells' => [['col' => 0, 'row' => 0, 'q' => 0, 'r' => 0, 'value' => 'forest'], …],
]
```

## Graphes

```yaml
generators:
  dungeon:
    map: { layout: graph, nodes: '1d4+3', extra_edges: 2, cell: { generate: dungeon.room } }
```

Un graphe a un nombre de nœuds (un nombre, ou une expression de dés lancée pour chaque carte, au moins un), chacun
rempli par la case, qui voit `index` et `count`. Chaque nœud après le premier est relié à un nœud antérieur choisi au
hasard, pour que chaque nœud soit accessible. Ensuite, `extra_edges` passages de plus relient deux nœuds pas encore
reliés et forment des boucles, autant que le graphe peut encore en accueillir. Les arêtes sont des paires
d'identifiants de nœuds, le plus petit d'abord, en ordre.

```php
[
    'layout' => 'graph',
    'nodes' => [['id' => 0, 'value' => ['kind' => 'hall', 'feature' => 'a locked chest']], …],
    'edges' => [[0, 1], [0, 3], [1, 2], …],
]
```

Une carte a au plus `Limits::$cells` cases ou nœuds. Le placement des nœuds dans l'espace, le terrain qui suit ses
voisins et le dessin sont laissés à l'application ou à des paquets plus spécialisés.
