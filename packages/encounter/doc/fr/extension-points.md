<!-- phil-dev source-sha256=0e4183d60a9b60dc30a75293d6fce60a1d8c8e2915c641ebde372fcc9f10325e -->
# Points d'extension

Le paquet est en `0.x` : ses points d'extension sont stables seulement à partir de sa version 1.0. D'ici là, une
version mineure peut les changer, et son CHANGELOG dit comment.

| Pour | Faites |
|---|---|
| Garder les rencontres | Implémentez `Contract\EncounterStore`, et passez-le à `EncounterPlanner`. |
| Agir sur ce qui s'est passé | Implémentez `Contract\EncounterObserver`, et passez-le à `EncounterPlanner`. |
| Déclarer la difficulté d'un système de jeu | Donnez au système des nombres de difficulté `encounter` dans `tilttek/rpg-systems`, lus par `Rulebooks::fromSystems()` ; ou lisez des données propres avec `Difficulty\Declaration::read()`, remplissez `Difficulty\BudgetRules` ou `Difficulty\LevelRules`, ou implémentez `Contract\DifficultyRules`, et passez-les dans `Rulebooks`, où elles prennent la place de celles d'un système. |
| Mener la rencontre dans un suivi d'initiative | Implémentez `Contract\InitiativeHandOff`, et passez-le à `EncounterPlanner`. |
| Dire qui appelle | Passez le membre à chaque changement ; le service vérifie que c'est le meneur de jeu. |

## Le magasin

`Contract\EncounterStore` garde chaque `Encounter` à une révision :

- `encounter()` donne une rencontre, ou null quand aucune de cet identifiant n'est gardée ;
- `save()` garde la rencontre à la révision suivante et la retourne telle que gardée. Une rencontre à la révision 0 est
  nouvelle : quand le magasin en a une de cet identifiant, `EncounterConflict` est levée. Toute autre n'est gardée que
  si le magasin la détient à cette révision, et `EncounterConflict` est levée sinon, sans rien garder ;
- `remove()` retire la rencontre quand le magasin la détient à sa révision, et lève `EncounterConflict` sinon.

`Encounter` contient des chaînes, un booléen, un entier et jusqu'à 100 `Participant`, chacun fait de chaînes, d'une
énumération dont les valeurs sont ses cas en chaîne, d'un entier, d'un booléen et d'un entier facultatif : une rangée et
une colonne JSON, ou une table de participants en ordre.

`Store\InMemoryEncounterStore` les garde en mémoire, aussi longtemps que l'objet dure : il sert aux tests.

## L'observateur et ses événements

`Contract\EncounterObserver::notify()` est informé de chaque fait une fois la rencontre gardée, par identifiants
seulement :

| Événement | Quand | Porte |
|---|---|---|
| `Event\EncounterPrepared` | Une nouvelle rencontre a été préparée. | son meneur de jeu |
| `Event\EncounterRetitled` | Son titre a changé ou a été retiré. | — |
| `Event\ParticipantAdded` | Un participant a été ajouté. | le participant |
| `Event\ParticipantChanged` | Un participant a été modifié. | le participant |
| `Event\ParticipantMoved` | Un participant s'est déplacé dans l'ordre. | le participant |
| `Event\ParticipantRemoved` | Un participant a été retiré. | le participant |
| `Event\EncounterLaunched` | La rencontre a été lancée et remise au suivi. | le nombre de combattants |
| `Event\EncounterForgotten` | La rencontre a été retirée du magasin. | — |

Chaque événement implémente `Event\EncounterEvent` : la rencontre, un identifiant. Un changement qui ne change rien
n'annonce aucun événement. Une application en envoie un message — par Messenger, avec les identifiants seulement — et lit
le reste dans le magasin.

## Les règles de difficulté

`Contract\DifficultyRules` donne la clé de son système de jeu avec `system()`, et évalue une liste de participants avec
`assess()`, comme le décrit [Difficulté](difficulty.md). Une implémentation ne garde aucun état entre les appels, refuse
une cote qu'elle ne connaît pas avec `InvalidEncounter`, et une rencontre qu'elle ne peut pas évaluer avec
`EncounterRefused`. Ses paliers vont de 1 à 12, leurs seuils en ordre : `Difficulty\Assessment` les refuse autrement.

## Le suivi d'initiative

`Contract\InitiativeHandOff::handOff()` démarre la rencontre dans le suivi avec les combattants de la liste, une fois le
lancement gardé. Il refuse une rencontre qu'il mène déjà, de sorte que remettre la même liste est sans danger.
[Initiative](initiative.md) montre un adaptateur pour `tilttek/initiative-cards`.
