<!-- phil-dev source-sha256=d185a32a4fc653176f8c8f6f7080a83341594124747356769bf95c94700d51d0 -->
# tilttek/play-log

**Journaliser qui a joué à quel jeu, quand, pendant combien de temps, avec les scores et les gagnants ; les joueurs
restent anonymes jusqu'à leur confirmation, et des statistiques par membre ou par groupe.**

tilttek/play-log garde les parties des membres et des groupes d'une communauté, sur les jeux de `tilttek/game-catalog`.
Une partie contient :

- **le jeu** — une entrée du catalogue, vérifiée à l'enregistrement de la partie ;
- **les places** — jusqu'à 100, chacune un membre par son identifiant ou un joueur anonyme, avec un score et, dans une
  partie compétitive, si la place a gagné ;
- **le résultat** — compétitif, une victoire coopérative, une défaite coopérative, ou inachevée ;
- **le reste, quand on le sait** — la date, qui peut rester inconnue plutôt que de faire croire que la partie date
  d'aujourd'hui, la durée en minutes, le groupe avec lequel elle a été jouée et le lieu.

Les joueurs restent anonymes par défaut. Le membre qui enregistre une partie est lié à sa propre place ; la place d'un
autre membre attend que ce membre la confirme, sauf si l'application dit qu'il a donné son accord d'avance. Jusque-là,
quiconque lit la partie voit cette place anonyme, et la partie ne compte pour personne d'autre que les membres qui ont
confirmé. Un membre refuse une place, qui devient anonyme avec son score conservé, et un membre qui part est oublié :
les parties qu'il a enregistrées sont supprimées et ses places dans celles des autres deviennent anonymes.

Les statistiques sont calculées sur demande, jamais conservées : pour un membre ou un groupe, sur une période, les
parties, les victoires, les minutes, chaque jeu avec ses parties, ses victoires, ses minutes, sa dernière date et son
meilleur score, les membres avec qui l'on a le plus joué, et l'indice h des jeux — le plus grand nombre de jeux joués
au moins autant de fois que leur nombre. Au plus 10 000 parties sont lues pour un résultat, qui dit quand il y en avait
davantage.

Les faits d'une partie — enregistrée, corrigée, supprimée, une place liée, en attente ou déliée — vont à un
observateur, chacun une fois, par identifiants seulement. Une bibliothèque de soirées de jeux qui envoie ses parties par
un port à elle rejoint le journal par un petit adaptateur, décrit dans les
[points d'extension](doc/fr/extension-points.md).

C'est une bibliothèque PHP simple : deux services, des objets-valeurs, des ports et un stockage gardé en mémoire pour
les tests.

## État

| Partie | État |
|---|---|
| `Play`, `Seat`, `Result` : une partie, ses places et sa fin, bornées et vérifiées | Implémenté et testé |
| `PlayLog` : enregistrer, corriger, supprimer, confirmer, refuser et oublier, avec leurs événements et le consentement des membres | Implémenté et testé |
| `PlayQuery` : les parties d'un membre, en attente d'un membre, enregistrées par un membre, ou d'un groupe, par jeu et par période | Implémenté et testé |
| `PlayStatistics` : les statistiques d'un membre ou d'un groupe, calculées sur demande et bornées | Implémenté et testé |
| `Store\InMemoryPlayStore` : le stockage des tests | Implémenté et testé |
| Un bundle Symfony : le stockage Doctrine, Messenger, les formulaires et les composants Twig | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Parties](doc/fr/plays.md) | Les parties, les places et les résultats, enregistrer et corriger, confirmer et refuser, lire et oublier. |
| [Statistiques](doc/fr/statistics.md) | Ce que contiennent les statistiques d'un membre ou d'un groupe, et leurs bornes. |
| [Points d'extension](doc/fr/extension-points.md) | Le stockage, le consentement, l'observateur et ses événements, et les adaptateurs des ports d'autres bibliothèques. |
| [Exceptions](doc/fr/exceptions.md) | `PlayLogException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\PlayLog\Play;
use Tilttek\PlayLog\PlayLog;
use Tilttek\PlayLog\PlayQuery;
use Tilttek\PlayLog\PlayStatistics;
use Tilttek\PlayLog\Result;
use Tilttek\PlayLog\Seat;

$log = new PlayLog($store, $catalog, $observer, $consent);    // your PlayStore, the Catalog, your observer and consent

$log->record(new Play(
    'play-1',
    'harbour',                                                 // the game's identifier in the catalogue
    'member-1',                                                // who records it: their own seat is linked at once
    [new Seat('member-1', 42, winner: true), new Seat('member-2', 37), new Seat(score: 30)],
    Result::Competitive,
    new DateTimeImmutable('2027-03-12 19:30'),
    minutes: 75,
    group: 'thursday-club',
));

$log->confirm('play-1', 'member-2');                           // member-2 agrees: the play counts for them

$plays = $log->plays(PlayQuery::ofMember('member-2'), 20);    // the latest first
$visible = $log->play('play-1')->seenBy('member-3');           // seats not confirmed read as anonymous

$statistics = new PlayStatistics($store)->ofMember('member-1');
$statistics->hIndex;                                           // the most games played at least that many times
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `ext-mbstring` et `tilttek/game-catalog`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
