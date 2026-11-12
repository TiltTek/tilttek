<!-- phil-dev source-sha256=26fe74783c8fc103cb37320501ac8f08c788a7309326b838b2fbd55f48388da1 -->
# Points d'extension

| Point | Ce qu'une application donne | Où |
|---|---|---|
| `Contract\GoalStore`, `Contract\LogStore` | Le stockage des objectifs et des comptes, à la place des magasins en mémoire | [Écrire un magasin](writing-a-store.md) |
| `Psr\EventDispatcher\EventDispatcherInterface` | Le répartiteur par où passe `GoalReached`, vers un écouteur qui décerne un insigne ou lance une célébration | [Consigner](logging.md#lobjectif-atteint) |
| `Psr\Clock\ClockInterface` | L'horloge où se lit le jour d'aujourd'hui de l'auteur : celle du système en production, une horloge fixe dans les tests | [Tests](testing.md) |

Chaque point est passé au constructeur de `WritingGoals`, qui ne garde aucun état propre entre deux appels. `Planner`
n'en a besoin d'aucun.

Les types publics et les contrats des magasins sont stables au sein d'une version mineure de `0.x` ; un changement qui
les touche vient avec une nouvelle version mineure et une ligne dans le journal des modifications.
