<!-- phil-dev source-sha256=b23268ba32f99ca981592c0f3c80c227ce4a5199cf67554408adf4c1816774fb -->
# Exceptions

Toutes les exceptions de la bibliothèque implémentent `Exception\PredictionsException` : attrapez celle-ci pour les
attraper toutes.

| Exception | Levée quand | Message |
|---|---|---|
| `QuestionClosed` | Une prédiction arrive une fois la question close, une annulation une fois qu'elle est résolue, une résolution une fois qu'elle est annulée ou résolue autrement, un règlement avant qu'elle soit résolue | `The question "…" takes no more predictions.`, `The question "…" is resolved: it can no longer be cancelled or resolved otherwise.`, `The question "…" is cancelled: it can no longer be resolved.`, `The question "…" is not resolved yet: there is nothing to settle.` |
| `UnknownQuestion` | Aucune question n'est posée sous la clé | `No question is asked under the key "…".` |
| `InvalidPrediction` | Une clé, un résultat, un choix, des points, un réglage ou une lecture hors limites ; un choix qui ne convient pas à sa question ; une question reposée différemment ou qui se clôt dans le passé | Ce qui est hors limites, et la limite |

`QuestionClosed` et `UnknownQuestion` étendent `DomainException` ; `InvalidPrediction` étend
`InvalidArgumentException`.

Les exceptions de `tilttek/points` parviennent telles quelles à l'appelant : `MovementConflict` quand la clé d'une
prédiction est écrite dans le grand livre pour d'autres points.
