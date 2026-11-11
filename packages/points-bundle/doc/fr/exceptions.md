<!-- phil-dev source-sha256=a8b0e11eadf98a6432a14ad79a5ca5f15b3d181eb7a279b097810300e894fa86 -->
# Exceptions

Le bundle lève les exceptions de la bibliothèque (`Tilttek\Points\Exception\PointsException`) : `InvalidPoints` et
`UnknownCurrency` pour une mauvaise devise, règle ou fuseau horaire dans la configuration, à la compilation du
conteneur, et `MovementConflict`, `InsufficientPoints`, `UnknownCurrency` et `UnknownMovement` comme le fait la
bibliothèque. Les siennes :

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `UnexpectedValueException` | Une ligne du registre est illisible | `An integer column holds string.` |
| `LogicException` | Un mouvement n'est ni écrit ni trouvé après son insertion, ou la ligne de verrou d'un paiement ne peut pas être mise à jour, ce que seule une table abîmée peut causer | `The movement "gift:1" is neither written nor found.` |
| `InvalidConfigurationException` | Une clé de `tilttek_points` est hors de ses bornes | Le message de Symfony |
