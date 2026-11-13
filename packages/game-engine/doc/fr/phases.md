<!-- phil-dev source-sha256=2305e770709b9cf8af6a97aeda48d08b0f11e6a25b18565f4af9098aafb95d80 -->
# Les phases et la machine à états

Les phases d'un jeu et les transitions entre elles forment une machine à états de Symfony Workflow.
`GameDefinition` la construit dans `Phases`, exposée comme `$definition->machine` :

```php
new GameDefinition('scene', [
    new Phase('exploration'),
    new Phase('encounter', TurnOrder::Initiative),
    new Phase('aftermath'),
], [
    new Transition('start-encounter', ['exploration', 'aftermath'], 'encounter'),
    new Transition('end-encounter', ['encounter'], 'aftermath'),
    new Transition('explore', ['encounter', 'aftermath'], 'exploration'),
]);
```

Une `Transition` a un nom, les phases qu'elle peut quitter et la phase où elle mène. Le `StateMachineValidator` de
Workflow vérifie la définition — une machine à états n'a qu'une phase à la fois, et une transition qui quitte une phase
a un nom bien à elle —, et une définition qu'il refuse est refusée avec `InvalidGame`.

| Méthode | Ce qu'elle donne |
|---|---|
| `can()` | Si une transition peut quitter une phase |
| `apply()` | La phase où mène une transition, ou `IllegalMove` |
| `from()` | Les transitions qui peuvent quitter une phase |
| `mermaid()` | La machine comme organigramme Mermaid, la phase courante marquée quand on en donne une |

Un siège change la phase avec le coup du moteur `engine.transition`, `Move::transition('start-encounter', $seat)` ;
l'`allows()` du jeu décide qui peut le faire, et son `enter()` donne les données de la phase entrée. Le tour recommence
dans la nouvelle phase, selon son ordre de tour, à la ronde 1.

## Pourquoi Symfony Workflow convient au rejeu

Rejouer un journal applique de nouveau ses coups : un changement de phase ne doit donc dépendre que de la phase qu'il
quitte et de la transition qu'il nomme. La machine à états de Workflow le fait exactement quand on ne lui donne aucun
répartiteur d'événements : aucune garde, aucun écouteur, aucune horloge n'est consulté, et appliquer les mêmes
transitions depuis la même phase initiale mène toujours à la même phase. Le moteur construit donc la machine sans
répartiteur, et demande à l'`allows()` du jeu — une fonction pure de l'état — ce que les gardes de Workflow
décideraient autrement.

Ce qu'apporte Workflow est conservé : sa définition et son validateur, les transitions activées depuis une phase, ses
exceptions pour une transition qui ne s'applique pas, et ses exporteurs, qui dessinent la machine d'un jeu pour sa
documentation. Le marquage vit dans un curseur créé pour chaque coup et abandonné après lui : la phase elle-même est
gardée dans `GameState`, une valeur que le journal reconstruit.
