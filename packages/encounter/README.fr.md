<!-- phil-dev source-sha256=356ddd7089c23f2ad7645b7a147ee0600d663d88a39fd00165864fe1400cac88 -->
# tilttek/encounter

**Préparez des rencontres de jeu équilibrées selon les règles de difficulté que déclare chaque système de jeu, puis
remettez les combattants à n'importe quel suivi d'initiative.**

tilttek/encounter est l'établi du meneur de jeu pour une rencontre avant qu'elle soit jouée : qui y participe, quelle
difficulté elle présente pour ces personnages, et qui agit ensuite dans quel ordre. Il ne code en dur aucun jeu : les
règles de difficulté sont celles que déclare le système de jeu, et le suivi d'initiative est celui que l'application
branche.

- **Participants.** Des personnages, des adversaires et des alliés, chacun avec la cote que lui donne le système — un
  niveau, un défi —, le membre qui le joue, la visibilité pour les joueurs, son bonus d'initiative et l'identifiant de
  la fiche de l'application. Plusieurs copies d'un même modèle tiennent sur une ligne : trois loups deviennent
  `wolves.1` à `wolves.3`.
- **Un ordre fixé par le meneur de jeu.** Un participant est ajouté en dernier, ou juste avant ou après un autre, et
  se déplace de la même façon : aucun numéro à garder en séquence.
- **Une difficulté selon les règles du système.** Chaque système de jeu déclare ses règles : par budget — chaque
  personnage apporte un montant à chaque palier, chaque adversaire coûte un montant, un multiplicateur s'applique selon
  leur nombre — ou par niveau — un budget pour un groupe standard, ajusté selon sa taille, et un coût selon l'écart de
  chaque adversaire au-dessus du groupe. L'évaluation donne le coût, le palier atteint et ce qui peut encore s'ajouter
  avant le palier suivant. Les règles sont lues dans les nombres de difficulté du système de `tilttek/rpg-systems` — ceux
  de la SRD 5.2 et de Pathfinder deuxième édition viennent avec leurs profils d'importation —, dans des données que
  l'application apporte, ou écrites comme une classe de l'application.
- **Une remise à n'importe quel suivi.** Lancer garde la rencontre lancée — elle ne change plus — et remet sa liste à
  un suivi d'initiative par un port : un jeu de cartes d'initiative, un suivi de jets. La bibliothèque n'en dépend
  d'aucun.

Les faits d'une rencontre — préparée, modifiée, lancée, oubliée — vont à un observateur, chacun une seule fois, par
identifiants seulement. Chaque valeur est bornée : 100 combattants, 12 paliers, 1 000 cotes par table.

C'est une bibliothèque PHP simple : un service, deux sortes de règles déclarées et leur lecteur, des objets-valeurs,
des ports et un magasin gardé en mémoire pour les tests.

## État

| Partie | État |
|---|---|
| `Participant`, `Encounter` : les participants et la rencontre, bornés et vérifiés | Implémenté et testé |
| `EncounterPlanner` : préparer, retitrer, ajouter, modifier, déplacer, retirer, évaluer, lancer et oublier | Implémenté et testé |
| `Difficulty\BudgetRules`, `Difficulty\LevelRules` et `Difficulty\Declaration` : les règles qu'un système déclare | Implémenté et testé |
| `Initiative\InitiativeRoster` et le port `Contract\InitiativeHandOff` | Implémenté et testé |
| `Store\InMemoryEncounterStore` : le magasin des tests | Implémenté et testé |
| Un bundle Symfony : le magasin Doctrine, les messages Messenger et les écrans du meneur de jeu | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le branchement. |
| [Rencontres et participants](doc/fr/encounters.md) | Préparer une rencontre, ses participants, leur ordre, le lancement et l'oubli. |
| [Difficulté](doc/fr/difficulty.md) | Les règles qu'un système déclare, par budget ou par niveau, leurs données et l'évaluation. |
| [Initiative](doc/fr/initiative.md) | La liste remise à un suivi, et un adaptateur pour `tilttek/initiative-cards`. |
| [Points d'extension](doc/fr/extension-points.md) | Le magasin, l'observateur et ses événements, les règles de difficulté et le suivi. |
| [Exceptions](doc/fr/exceptions.md) | `EncounterException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et sa cible de fuzzing. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\Encounter\Difficulty\Declaration;
use Tilttek\Encounter\EncounterPlanner;
use Tilttek\Encounter\Kind;
use Tilttek\Encounter\Participant;
use Tilttek\Encounter\Rulebooks;

$rulebooks = Rulebooks::fromSystems($systems);                      // the difficulty numbers of each system $rulebooks
= Rulebooks::fromSystems($systems, Declaration::RECORD, Declaration::read($rulesOfMySystem)); // and data of its own
$planner = new EncounterPlanner($store, $rulebooks, $observer, $handOff);

$planner->prepare('ambush', 'gm-1', 'my-system', 'The ambush on the road', [
    new Participant('ayla', Kind::Character, '3', 'player-4', initiativeBonus: 2, ref: 'sheet:12'),
    new Participant('bran', Kind::Character, '3', 'player-5', ref: 'sheet:13'),
]);
$planner->add('ambush', 'gm-1', new Participant('wolves', Kind::Adversary, '1', 'gm-1', 3, ref: 'stat:wolf'));
$planner->add('ambush', 'gm-1', new Participant('alpha', Kind::Adversary, '3', 'gm-1', hidden: true), before: 'wolves');

$assessment = $planner->assess('ambush');
// $assessment->difficulty is the band reached; $assessment->remainingTo('high'), what may still be added before it

$roster = $planner->launch('ambush', 'gm-1');   // handed to the tracker; the encounter no longer changes
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `tilttek/rpg-systems`,
dont les systèmes de jeu servent à trouver les livres de règles.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
