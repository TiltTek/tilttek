<!-- phil-dev source-sha256=fb626a885f8e4db0ad3718bb797d32b6544615ffdc8e0722d9bf6ef77736d698 -->
# Progression et complétions

## Possessions

`Holdings` est ce qu'un détenteur possède, tel que l'application le lit dans son propre stockage :
`Holdings::of($identifiers)` possède chacun au niveau 1, `Holdings::withLevels(['d4' => 2, 'd6' => 1])` au niveau
donné, 0 signifiant non possédé. Les membres qui ne sont pas dans l'ensemble sont ignorés. Les possessions contiennent
au plus 100 000 membres, chacun à un niveau d'au plus 1 000.

## L'évaluation

`SetDefinition::evaluate($holdings)` mesure un ensemble par rapport à des possessions sans rien enregistrer :

| De `Evaluation` | Donne |
|---|---|
| `$owned`, `$missing` | Les membres possédés et manquants, dans l'ordre de l'ensemble |
| `total()`, `ownedCount()` | *12 sur 20* |
| `percent()` | La part possédée, arrondie à l'inférieur : 100 seulement quand l'ensemble est complet |
| `isComplete()` | Chaque membre possédé, à n'importe quel niveau |
| `$tiersReached`, `hasReached($code)`, `nextTier()` | Les paliers atteints, dans l'ordre, et le premier palier non atteint |

## Enregistrement

`Sets::progress($set, $holder, $holdings)` donne un `Progress` et n'enregistre rien. `Sets::record()` donne la même
chose, après avoir enregistré chaque complétion atteinte pour la première fois — celle de l'ensemble, puis ses paliers
dans leur ordre — et réparti, pour chacune de celles enregistrées :

- `Event\SetCompleted`, avec le `Completion` et les récompenses de l'ensemble ;
- `Event\TierReached`, avec le `Completion` du palier et ses récompenses.

Un `Completion` contient l'ensemble, le détenteur, le palier ou null pour l'ensemble lui-même, la version de l'ensemble
sous laquelle elle a été obtenue, et son moment en UTC. Le stockage en garde une par ensemble, palier et détenteur :
une complétion est donc annoncée une seule fois, et un détenteur qui échange un membre puis le récupère n'est informé
de rien de plus. L'application appelle `record()` quand les possessions d'un détenteur changent — un badge obtenu, un
objet reçu —, et accorde les récompenses quand elle entend les événements.

| De `Progress` | Donne |
|---|---|
| `$evaluation` | L'évaluation par rapport à la dernière version |
| `$completions`, `$recorded` | Chaque complétion du détenteur dans l'ensemble, et celles que cet appel a écrites |
| `completion()`, `completedOnce()` | La complétion de l'ensemble lui-même, sous n'importe quelle version |
| `isComplete()` | Chaque membre de la dernière version possédé en ce moment |
| `completedUnderEarlierVersion()` | Complété une fois, sous une version antérieure, et non complet sous la dernière |
| `tierReachedOnce($code)` | Le palier atteint une fois, sous n'importe quelle version |

## Versions et complétions

Une complétion n'est jamais reprise : quand un ensemble gagne des membres dans une nouvelle version, un détenteur qui
a complété la version antérieure garde sa complétion et n'est pas informé de nouveau quand il complète la nouvelle.
`completedUnderEarlierVersion()` permet à l'application d'afficher côte à côte la complétion et les nouveaux membres.

## Détenteurs

Un détenteur est un `Phil\Resource\ResourceReference` que donne l'application — `member:1042`, `team:7` —, un
identifiant stable : un identifiant contenant `@`, brut ou encodé en pourcentage, est refusé, de sorte qu'une adresse
courriel ne devienne jamais la clé d'une complétion.

## Effacement

`Sets::eraseHolder($holder)` supprime toutes les complétions d'un détenteur, pour l'effacement d'un membre, et retourne
combien. Les possessions vivent dans les stockages de l'application, qui les efface.

## Concurrence

Deux processus qui enregistrent la même complétion au même moment appellent tous deux `CompletionStore::add()` ; le
stockage en ajoute une et retourne faux à l'autre, qui n'annonce rien. Un stockage en base de données obtient ce
résultat par une clé unique sur l'ensemble, le palier et le détenteur.
