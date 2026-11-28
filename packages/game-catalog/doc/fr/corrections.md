<!-- phil-dev source-sha256=dc3799ca412097bdb8ff214294061586eedcdc1ee986409458765fa7dcc9027c -->
# Corrections

Un membre propose une correction d'une entrée sur n'importe quel champ ; l'administration l'accepte ou la refuse.
Jusque-là, la correction est gardée à côté de l'entrée et ne change rien.

```php
$corrections->propose('c-1', 'tide', 'm-104', $detailsAsTheMemberWouldHaveThem);
$corrections->waiting('tide');                    // the corrections waiting, in the order they were proposed
$corrections->accept('c-1', 'admin-1');
$corrections->refuse('c-2', 'admin-1', 'The box says 2026.');
$corrections->revert('tide', Field::Year, 'admin-1');
```

## Proposer

Le membre envoie les `Details` entiers tels qu'il les voudrait, lus à partir des détails montrés. La correction garde
les détails que le membre a lus et les champs qu'il a changés ; une correction qui ne change rien lève `CatalogRefused`.
Un membre a au plus 20 corrections en attente ; l'observateur est informé de chacune qui est proposée.

## Accepter

Accepter donne aux champs de la correction les valeurs que le membre a proposées. Quand un champ qu'elle change a été
changé sur l'entrée depuis la lecture du membre — par une autre correction, ou par la source — pour une troisième
valeur, accepter écraserait ce changement sans qu'on le voie : `CorrectionConflict` nomme les champs, et
l'administration lit les deux valeurs, puis accepte avec `force: true` ou refuse. Un champ changé depuis pour la valeur
que le membre a proposée n'est pas un conflit.

Les valeurs d'une correction sont gardées à côté des valeurs de la source, qui restent telles que la source les a
envoyées : la source qui renvoie l'entrée change ses propres valeurs, et les champs corrigés gardent les leurs.
L'historique note les champs, l'administrateur et la correction ; l'observateur est informé, pour le membre.

Accepter enregistre la correction, puis l'entrée : une application qui garde les deux dans une base de données exécute
l'appel dans une transaction.

## Refuser

Refuser garde la correction avec une note d'au plus 1000 caractères pour le membre, et ne change rien à l'entrée. Une
correction n'est tranchée qu'une fois : la trancher de nouveau lève `CatalogRefused`.

## Rendre un champ à sa source

`revert()` rend un champ corrigé à la valeur de la source, et le note dans l'historique ; un champ qui n'est pas corrigé
lève `CatalogRefused`.
