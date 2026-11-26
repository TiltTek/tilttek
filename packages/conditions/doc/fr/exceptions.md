<!-- phil-dev source-sha256=cda58649c4144d859f5808e01e6907c3d5f79cdd91781cba30572ffc729e55b5 -->
# Exceptions

Chaque exception de la bibliothèque implémente `Exception\ConditionsException` : attrapez-la pour les attraper toutes.

| Exception | Étend | Quand |
|---|---|---|
| `Exception\InvalidCondition` | `InvalidArgumentException` | Une valeur hors de sa forme ou de ses bornes : un identifiant, une valeur manquante, donnée ou trop haute, une durée, un déclin, un modificateur, une définition dont les règles se contredisent, un catalogue avec un identifiant en double, une condition entraînée manquante ou dotée d'une valeur, ou une boucle, trop de conditions, de statistiques ou de participants, une avance du temps hors de ses bornes, une durée sur les tours de la source sans source. |
| `Exception\ConditionRefused` | `DomainException` | Un changement que l'état du personnage n'autorise pas : le maximum de conditions, le maximum d'instances d'une condition, une valeur fixée sur une condition qui n'en a pas ou que le catalogue n'a plus. |
| `Exception\ConditionNotFound` | `RuntimeException` | Le catalogue n'a aucune condition de cet identifiant, ou le personnage n'a aucune instance de ce nom. |
| `Exception\ConditionConflict` | `RuntimeException` | Les conditions du personnage ont changé depuis leur lecture : le changement n'est pas gardé, et il est refait sur ce qu'on relit. |
