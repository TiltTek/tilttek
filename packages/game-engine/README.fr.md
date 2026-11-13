<!-- phil-dev source-sha256=ee7dc6849f462470669243f1413a136de186b4e62d569154434372d585479a98 -->
# tilttek/game-engine

**Une machine à états pour les jeux de table en ligne et les scènes de jeu de rôle, sans une seule règle de jeu :
chaque table est un journal de coups avec son germe, et son état un cache reconstruit en rejouant le journal.**

tilttek/game-engine fait tourner les tables de jeux au tour par tour en ligne — un jeu de plateau ou de cartes, un jeu
de taverne, un mini-jeu — ainsi que les tours et les phases d'une scène de jeu de rôle : l'exploration, puis une
rencontre dans l'ordre d'initiative, puis ses suites. Il ne contient aucune règle : un jeu implémente un seul contrat
et déclare ses phases, les transitions entre elles, le nombre de sièges d'une table, les options qu'il offre, qui agit
dans chaque phase — n'importe qui, un siège après l'autre, par initiative, ou chaque siège une fois par ronde — et
combien de coups terminent un tour. Le moteur vérifie les tours, change la phase par une machine à états de Symfony
Workflow, ordonne les sièges par initiative et permet à un siège de retarder son tour, vérifie les options de la table
avec l'OptionsResolver de Symfony, et demande au jeu si un coup est permis et ce qu'il change.

La vérité d'une table est son journal : le jeu et la version de ses règles, un germe de 256 bits, les sièges et les
options, et chaque coup dans l'ordre, avec la mention qu'il a tiré au hasard ou non. L'état est un cache : rejouer le
journal — la mise en place, puis chaque coup avec le hasard de sa place dans le journal — redonne le même état, ce que
les tests vérifient et qu'un rejeu contrôle, en refusant un journal dont les coups ne s'appliquent plus ou tirent
autrement ; on peut montrer aux joueurs un engagement sur le germe à l'ouverture de la table, avec lequel chacun vérifie
le rejeu une fois la partie terminée. Le dernier coup peut être repris quand le jeu le permet, que la partie n'est pas
terminée et que le coup n'a rien révélé. Ajouter un coup vérifie le nombre de coups et la révision du journal que le
joueur a vus, de sorte que deux changements faits en même temps n'atterrissent jamais tous les deux ; une lecture coûte
l'en-tête du journal et les coups faits depuis l'état en cache, lus fenêtre par fenêtre, une fois par requête ; et
chaque changement est communiqué à un port d'éditeur — un concentrateur Mercure, par le bundle — avec des identifiants
seulement ; chaque joueur lit ensuite la vue de son siège, qui omet les
secrets des autres et suit ses propres préférences. Une partie qui se termine rapporte les statistiques qu'elle déclare,
pour la table et pour chaque siège.

Il s'adresse à une application qui héberge des parties ou des scènes jouées à distance et veut que chaque table soit
vérifiable, rejouable et protégée contre un joueur qui agit hors de son tour. C'est une bibliothèque PHP simple dont
les dépendances sont `symfony/workflow`, la machine des phases, et `symfony/options-resolver`, les options d'une
table. Un jeu prêt à l'emploi, `RoleplayingScene`, fait tourner les phases d'une scène sans les règles d'aucun système.

## État

| Partie | État |
|---|---|
| `Contract\Game`, `GameDefinition`, `Phase`, `Transition`, `TurnOrder` : les règles d'un jeu derrière un seul contrat, ses phases et ses tours déclarés | Implémenté et testé |
| `Phases` : les phases comme machine à états de Symfony Workflow, vérifiée par son validateur et dessinée par son exporteur Mermaid | Implémenté et testé |
| `Option`, `OptionKind`, `TableOptions` : les options d'une table, vérifiées par l'OptionsResolver | Implémenté et testé |
| `Preferences` : les préférences de chaque joueur, données à la vue de son siège, hors du journal | Implémenté et testé |
| `Statistic`, `StatisticScope`, `Statistics` : les statistiques de la fin d'une partie, déclarées et vérifiées | Implémenté et testé |
| `Engine`, `Table`, `GameState`, `Turn`, `Outcome` : tables ouvertes, coups appliqués, tours, initiative, retards, phases, fins | Implémenté et testé |
| `GameLog`, `LoggedMove`, `Move`, `Randomness` : le journal comme vérité, écrit et relu, hasard déterministe, rejeu vérifié par l'engagement sur le germe, et reprise | Implémenté et testé |
| `GameState::toArray()`, `GameState::fromArray()` : l'état en valeurs simples, pour un cache partagé | Implémenté et testé |
| `Tables`, `Games`, `Contract\LogStore`, `Contract\StateCache`, `Contract\TablePublisher`, `TableUpdate` : tables conservées, mises en cache, protégées contre les coups concurrents et publiées | Implémenté et testé |
| `LogHeader`, `Snapshot`, `Engine::advance()` : le journal lu sans ses coups et par fenêtres, l'état en cache lié à la révision de son journal, chaque table lue une fois par requête | Implémenté et testé |
| `Store\InMemoryLogStore`, `Store\InMemoryStateCache`, `Store\NullStateCache`, `Store\NullTablePublisher` | Implémenté et testé |
| `Scene\RoleplayingScene` : exploration, rencontre par initiative et suites, menées par le premier siège | Implémenté et testé |
| Exceptions sous une seule interface `GameEngineException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Écrire un jeu](doc/fr/writing-a-game.md) | Le contrat `Game`, la définition, les phases, les ordres de tour, les options, les préférences, les vues, les fins et les statistiques. |
| [Les phases et la machine à états](doc/fr/phases.md) | Les transitions sur Symfony Workflow, pourquoi elle convient au rejeu, et le diagramme d'un jeu. |
| [Tables, coups et tours](doc/fr/tables-and-moves.md) | Ouvrir une table, les coups du moteur, les tours, l'initiative et les retards, les coups hors tour. |
| [Le journal et le rejeu](doc/fr/log-and-replay.md) | Le journal comme vérité, le germe et le hasard, l'engagement sur le germe, rejouer, vérifier, reprendre, et l'état en valeurs simples. |
| [Conserver et publier](doc/fr/storing-and-publishing.md) | `Tables`, le magasin de journaux, le cache d'états, les coups concurrents et le port de publication. |
| [La scène de jeu de rôle](doc/fr/roleplaying-scene.md) | Le jeu prêt à l'emploi pour les phases d'une scène. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Exceptions](doc/fr/exceptions.md) | `GameEngineException` et ses exceptions, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, sa cible de fuzzing et ses bancs d'essai, et comment tester un jeu. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\GameEngine\{Games, Move, Tables};
use Tilttek\GameEngine\Scene\RoleplayingScene;
use Tilttek\GameEngine\Store\InMemoryLogStore;

$tables = new Tables(new Games(new RoleplayingScene()), new InMemoryLogStore());

$tables->open('roleplaying-scene', 'scene:12', ['seat:gm', 'seat:ann', 'seat:bob']);
$tables->play('scene:12', Move::initiative(['seat:ann' => 14, 'seat:bob' => 9], 'seat:gm'));
$state = $tables->play('scene:12', Move::transition('start-encounter', 'seat:gm'), expected: 1);

$state->turn->toAct();                       // ['seat:ann']
$tables->view('scene:12', 'seat:bob');       // what Bob sees
$tables->commitment('scene:12');             // shown to the players from the opening
$tables->finishedLog('scene:12');            // seed included, once the game is over, to verify every draw
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
