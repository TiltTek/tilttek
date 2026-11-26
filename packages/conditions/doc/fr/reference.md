<!-- phil-dev source-sha256=4aee8dedca7a2b37fd7bdc77816d5c2a28a338ec5a4d78f07dda89861a811552 -->
# Référence

Chaque type public du paquet, avec son rôle. Les types marqués internes peuvent changer dans n'importe quelle version.

## Le catalogue

| Type | Rôle |
|---|---|
| `ConditionCatalogue` | Les conditions d'un système de jeu, et celles que chacune entraîne. |
| `ConditionDefinition` | Une condition : cumul, valeur, déclin, modificateurs, conditions entraînées et durée. |
| `Stacking` | Ce qu'une nouvelle application fait à celle en place. |
| `Decay` | Comment une valeur baisse aux tours du personnage touché. |
| `Duration` | La durée d'une condition, et ce qu'il en reste. |
| `DurationKind` | Rounds, temps de jeu, temps réel, ou jusqu'au retrait. |
| `Boundary` | Le début ou la fin d'un tour ou d'un round. |
| `Anchor` | Les tours que compte une durée : ceux de la cible, de la source, ou les rounds. |
| `Modifier` | Ce qu'une condition fait à une statistique. |
| `Operation` | Multiplier, ajouter, relever, abaisser ou remplacer. |

## Les conditions d'un personnage

| Type | Rôle |
|---|---|
| `Conditions` | Le service : appliquer, retirer, fixer une valeur, tours, rounds, temps de jeu, expiration, vue et effets. |
| `CharacterConditions` | Les applications d'un personnage, le dernier tic compté et la version. |
| `ActiveCondition` | Une application : instance, condition, valeur, source, durée restante et fin. |
| `EndReason` | Retirée, expirée, déclinée ou remplacée. |
| `View\ConditionsView` | Les conditions d'un personnage à montrer à la table, et la prochaine fin en temps réel. |
| `View\ConditionState` | Une condition dans la vue, appliquée ou entraînée. |

## Effets

| Type | Rôle |
|---|---|
| `Effect\EffectCalculator` | Les statistiques une fois les modificateurs comptés. |
| `Effect\AppliedModifier` | Un modificateur en vigueur, sa condition et son montant. |
| `Effect\StatValue` | La base d'une statistique, sa valeur et les modificateurs qui ont fait la différence. |
| `Rounding` | Comment une multiplication arrondit. |

## Ports et magasins

| Type | Rôle |
|---|---|
| `Contract\ConditionStore` | Garde les conditions de chaque personnage à une version. |
| `Contract\ConditionObserver` | Reçoit les événements des conditions. |
| `Store\InMemoryConditionStore` | Les conditions en mémoire, pour les tests. |

## Événements

| Type | Rôle |
|---|---|
| `Event\ConditionEvent` | Chaque événement : le personnage, l'instance et la condition. |
| `Event\ConditionApplied` | Une condition a été appliquée. |
| `Event\ConditionChanged` | Une application a changé. |
| `Event\ConditionEnded` | Une application s'est terminée, et pourquoi. |

## Exceptions

| Type | Rôle |
|---|---|
| `Exception\ConditionsException` | Chaque exception de la bibliothèque. |
| `Exception\InvalidCondition` | Une valeur hors de sa forme ou de ses bornes. |
| `Exception\ConditionRefused` | Un changement que les conditions du personnage n'autorisent pas. |
| `Exception\ConditionNotFound` | Aucune condition ni instance n'a cet identifiant. |
| `Exception\ConditionConflict` | Les conditions ont changé depuis leur lecture. |

## Interne

| Type | Rôle |
|---|---|
| `Internal\Identifier` | La forme d'un identifiant. |
| `Internal\Millis` | Des instants en millisecondes depuis l'époque. |
