<!-- phil-dev source-sha256=fd815cb60d8afc6ce185a4443e7a5ff25065da956b1c9d768fe7714703c97a76 -->
# tilttek/game-night

**Organisez les soirées de jeux d'un club ou d'une boutique : les tables et leurs sièges, les inscriptions avec un
invité et une liste d'attente, les jeux suggérés selon les joueurs et le temps disponible, un vote sur les jeux, et les
parties envoyées au journal des parties.**

tilttek/game-night planifie une soirée — quand, où, son hôte — et les tables qu'elle accueille, chacune avec ses sièges
et, quand ils sont connus, son jeu et le membre qui l'anime. Une fois la soirée publiée, les membres s'inscrivent :

- un membre peut amener un invité, qui prend une place et dont rien d'autre n'est conservé ;
- quand les places sont prises, une inscription attend sur une liste d'attente simple, servie dans l'ordre où les
  membres se sont inscrits ;
- un membre qui se retire libère ses places pour le premier en attente, qui en est informé une seule fois ;
- un membre confirmé est assis à une table qui a des sièges libres.

Les jeux sont suggérés pour une table, ou pour toute la soirée, parmi les jeux que les membres peuvent apporter — lus
dans leurs étagères par un port, jamais autrement — et les jeux que le club ou la boutique conserve : un jeu est suggéré
quand son éditeur indique le nombre de joueurs et qu'il se termine dans les minutes disponibles. Les suggestions
viennent de `tilttek/game-catalog`, ordonnées selon ce que ses membres ont voté pour ce nombre de joueurs, et chacune
porte le nombre de votes sur lesquels elle repose, à afficher à côté d'elle. De BoardGameGeek, une suggestion conserve
l'identifiant et le lien, rien d'autre.

Avant la soirée, l'hôte propose au vote des jeux du catalogue ; chaque membre inscrit vote une seule fois, et peut
changer son vote jusqu'au début de la soirée. Le résultat dit combien de membres ont voté, ce que chaque jeu a reçu et
sa part, et si un jeu a gagné ou si plusieurs sont à égalité : l'égalité est laissée à l'hôte, la bibliothèque ne tire
pas au sort.

Une fois la soirée commencée, les jeux joués à ses tables vont au journal des parties de l'application par un port,
avec les membres par leurs identifiants et les invités sous forme de nombre.

Les faits d'une soirée — publiée, complète, annulée, un siège offert à un membre en attente — vont à un observateur,
chacun une seule fois, par identifiants seulement : la bibliothèque n'envoie aucun rappel, et n'en répète jamais un aux
membres absents.

C'est une bibliothèque PHP simple : des services, des ports, des objets-valeurs et un entrepôt gardé en mémoire pour
les tests.

## État

| Partie | État |
|---|---|
| `GameNight`, `Table`, `Registration` : une soirée, ses tables et ses sièges, ses inscriptions, sa liste d'attente et son vote | Implémenté et testé |
| `GameNights` : la planification, les tables, la publication, les inscriptions, les retraits, les sièges, le vote et l'annulation, avec leurs événements | Implémenté et testé |
| `Choice\GameChooser`, `Choice\Criteria` : les jeux suggérés selon les joueurs et les minutes, avec les votes sur lesquels ils reposent | Implémenté et testé |
| `Vote\VoteResult` : le vote compté et expliqué | Implémenté et testé |
| `NightPlays` : les jeux joués envoyés au journal des parties | Implémenté et testé |
| `Store\InMemoryNightStore` : l'entrepôt des tests | Implémenté et testé |
| Un bundle Symfony : l'entrepôt Doctrine, Messenger, les formulaires et les composants Twig | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Soirées et inscriptions](doc/fr/nights.md) | La planification, les tables, la publication, les inscriptions, l'invité, la liste d'attente, les sièges et l'annulation. |
| [Choisir des jeux](doc/fr/choosing-games.md) | Les critères, les étagères, l'ordre des suggestions et les votes sur lesquels elles reposent. |
| [Le vote](doc/fr/vote.md) | Proposer des jeux, voter une seule fois, et le résultat expliqué. |
| [Parties](doc/fr/plays.md) | Les jeux joués envoyés au journal des parties. |
| [Points d'extension](doc/fr/extension-points.md) | L'entrepôt, l'observateur et ses événements, les étagères et le journal des parties. |
| [Exceptions](doc/fr/exceptions.md) | `GameNightException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\GameNight\Choice\Criteria;
use Tilttek\GameNight\Choice\GameChooser;
use Tilttek\GameNight\GameNights;
use Tilttek\GameNight\Table;

$nights = new GameNights($store, $clock, $catalog, $observer); // your NightStore, PSR-20 clock, Catalog, NightObserver

$nights->plan('night-42', new DateTimeImmutable('2027-03-05 19:00'), new DateTimeImmutable('2027-03-05 23:00'),
    'venue-7', 'member-1', [new Table('table-a', 4), new Table('table-b', 6)]);
$nights->publish('night-42');                                // NightPublished

$nights->register('night-42', 'member-2', guest: true);      // two places
$nights->register('night-42', 'member-3');

$suggestions = new GameChooser($catalog, $shelves)           // your ShelfReader
    ->suggest(Criteria::forTable($nights->get('night-42'), 'table-a'));

foreach ($suggestions as $suggestion) {
    // $suggestion->name, $suggestion->choice, $suggestion->choiceVotes, $suggestion->boardGameGeekLink()
}

$nights->propose('night-42', ['lanterns', 'harbour']);
$nights->vote('night-42', 'member-2', 'harbour');
$nights->result('night-42')->winner();                       // 'harbour'
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `psr/clock` et `tilttek/game-catalog`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
