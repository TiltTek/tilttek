<!-- phil-dev source-sha256=49f0379213b6b304272e237e30df8e3b407d3567c4ec95ae5c16cd9f6ca3f0de -->
# Exceptions

Le bundle lève les exceptions de la bibliothèque (`Tilttek\Activity\Exception\ActivityException`) : `InvalidActivity`
pour un motif de verbe erroné dans la configuration, à la compilation du conteneur, ainsi que `UnknownVerb`,
`IdempotencyConflict`, `UnknownProjection` et `InvalidProjection`, comme le fait la bibliothèque. Les siennes :

| Exception | Levée quand | Message, par exemple |
|---|---|---|
| `UnexpectedValueException` | Une ligne du journal ou un point de reprise ne se lit pas | `A context column holds JSON that is not an object.` |
| `LogicException` | Une entrée n'est ni écrite ni trouvée après son insertion, ce que seule une table brisée peut causer | `The recorded entry of the key "k" is neither written nor found.` |
| `InvalidConfigurationException` | `store` n'est ni `doctrine` ni `memory`, ou `batch_size` est inférieur à 1 | Le message de Symfony |
