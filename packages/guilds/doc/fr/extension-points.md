<!-- phil-dev source-sha256=2054831e85675fbd3dbf39459763c2e03a65474fd04f295ec9a4e12ead238b26 -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Point | Ce que l'application fournit | Valeur par défaut |
|---|---|---|
| `Contract\GuildStore` | Où l'on garde les guildes, les adhésions et les demandes en attente | `Store\InMemoryGuildStore` dans les tests |
| `Contract\GoalStore` | Où l'on garde les objectifs et les contributions | `Store\InMemoryGoalStore` dans les tests |
| `Contract\GuildObserver` | Ce qu'on fait d'un changement de guilde ou d'un objectif atteint | Aucune |
| `Limits` | Ses bornes sur les guildes par membre, les demandes en attente et les objectifs ouverts | 10, 100 et 10 |
| `Psr\Clock\ClockInterface` | Le moment de chaque acte | — |

## Écrire un magasin de guildes

Un `GuildStore` désigne les guildes et les membres par leurs références écrites `kind:identifier`. Il garde :

- les guildes, par référence — `guild()`, `saveGuild()` et `removeGuild()`, qui retire avec la guilde ses membres et ses
  demandes ;
- les adhésions, une par guilde et par membre — `membership()`, `saveMembership()`, qui remplace la précédente,
  `removeMembership()`, `countMembers()`, ainsi que `memberships()` et `membershipsOf()`, la plus ancienne entrée
  d'abord ;
- les demandes en attente, une par guilde et par membre — `request()`, `saveRequest()`, `removeRequest()`,
  `countRequests()`, ainsi que `requests()` et `requestsOf()`, la plus ancienne d'abord.

Les moments à égalité se lisent dans l'ordre des octets de la référence de la guilde, puis de celle du membre. La
bibliothèque vérifie la capacité d'une guilde avant d'écrire une adhésion : une application qui sert des requêtes
concurrentes exécute chaque acte dans une transaction, ou sous un verrou sur la guilde.

## Écrire un magasin d'objectifs

Un `GoalStore` garde les objectifs par clé — `goal()`, `saveGoal()`, qui remplace l'objectif de même clé, et
`goalsOf()`, le plus ancien ouvert d'abord, les égalités départagées par la clé —, et leurs contributions —
`addContribution()`, `contribution($goal, $key)`, celle d'une clé, et les sommes `points()`, `pointsOf()` et
`contributors()`, qui compte ensemble les membres effacés comme un seul. `forget($member)` met à null le membre de
leurs contributions et garde les points. Une contribution s'exécute dans une transaction, ou sous un verrou sur
l'objectif, afin que l'objectif soit atteint une seule fois.

## Observateurs

Un `GuildObserver` reçoit un `GuildChanged` après chaque acte et un `GoalReached` quand un objectif atteint sa cible,
une fois que le magasin les a écrits. Les événements ne portent que des identifiants ; un observateur les relaie — une
notification, un message sur un bus — et ne garde aucun état d'un appel à l'autre.
