<!-- phil-dev source-sha256=a2652fa6d5cd09ffb6d529229fcef7d65f0852472f26502522e1883473a02bc7 -->
# tilttek/tournament

**Menez des tournois à rondes suisses, en poules ou à élimination, avec des appariements équitables, des participants
qui abandonnent et des bris d'égalité déclarés dans l'ordre.**

tilttek/tournament mène un tournoi de sa première ronde à son podium. Un tournoi a des participants, connus par des
identifiants opaques et jamais par leur nom, et de une à quatre étapes : des rondes suisses appariées par groupes de
points, des poules où chacun rencontre chacun selon les tables de Berger, en un ou deux tours, et un tableau à
élimination simple avec têtes de série et exemptions, qui termine le tournoi. Une étape suisse ou des poules envoient
leurs meilleurs à l'étape suivante ; le tableau d'une deuxième étape est préparé avant que ses qualifiés soient connus,
ses places montrant le rang et la poule qui les rempliront.

Chaque ronde est préparée avec les raisons de ses appariements — l'exemption et sa raison, qui joue en premier et
pourquoi, la revanche évitée, le participant déplacé vers un autre groupe de points — pour qu'un organisateur les lise
et les montre avant de la publier, ou pour qu'il place les participants autrement. Le numéro de la ronde est gardé à part
de sa date, de sorte qu'une ronde déplacée à un autre jour garde sa place. Un résultat est saisi une seule fois par
table : le même résultat saisi de nouveau par l'adversaire ne change rien, et un résultat contraire attend qu'un
organisateur tranche. Le résultat d'une ronde passée se corrige, le classement est recalculé et la différence est
renvoyée. Un participant qui se retire n'est plus apparié, et ses tables laissées sans résultat sont des forfaits. Le
classement départage les égalités avec les bris d'égalité que le tournoi déclare, dans l'ordre — Buchholz et Buchholz
tronqué, Sonneborn-Berger, confrontation directe, victoires, points de mission —, puis avec le tirage d'une graine
publiée, jamais avec un hasard qu'on ne peut pas rejouer. Quand le tournoi a son podium, la bibliothèque renvoie un
événement `TournamentFinished`, le moment d'une célébration.

Elle s'adresse à une application qui mène des tournois pour un magasin de jeux, un congrès ou un club : échecs, jeux de
cartes, jeux de société, jeux de figurines avec leurs points de mission. C'est une bibliothèque PHP simple, sans
dépendance ; les forces d'un premier ordre passent par un port, et `Seeding\RatingSeeding` les lit dans
`tilttek/rating` quand il est installé.

## État

| Partie | État |
|---|---|
| `Tournament`, `Stage`, `Scoring`, `Round`, `Pairing`, `Result` : un tournoi déclaré, ses étapes, son pointage en nombres entiers, ses rondes et ses tables, ses résultats avec points de mission | Implémenté et testé |
| `Tournaments` : démarrer, préparer et publier les rondes, saisir, trancher et corriger les résultats, retirer un participant, déplacer une ronde, classement, tableau, calendrier, explications | Implémenté et testé |
| `Swiss\SwissPairer` : rondes suisses par groupes de points, exact jusqu'à 16 participants, recherche bornée au-delà, déterministe | Implémenté et testé |
| `RoundRobin\BergerTable`, `RoundRobin\Pools` : poules par les tables de Berger, repos dans le calendrier, matchs retour avec les côtés échangés, poules remplies en serpentin | Implémenté et testé |
| `Elimination\Bracket`, `Elimination\BracketState` : un tableau à élimination simple par têtes de série, exemptions à la première ronde, préparé avant que ses qualifiés soient connus | Implémenté et testé |
| `Tiebreak\Tiebreakers` : Buchholz, Buchholz tronqué, Sonneborn-Berger, confrontation directe, victoires, points de mission, et ceux de l'application | Implémenté et testé |
| `Contract\SeedingSource`, `Seeding\FixedSeeding`, `Seeding\RatingSeeding` : le premier ordre par force, par une liste ou par les cotes de `tilttek/rating` | Implémenté et testé |
| `Event\TournamentFinished` : le gagnant et le podium | Implémenté et testé |
| Double élimination, match pour la troisième place | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis et l'installation. |
| [Tournois et étapes](doc/fr/formats.md) | Déclarer un tournoi, ses étapes et son pointage, et le mener ronde par ronde. |
| [Appariements](doc/fr/pairings.md) | Comment les rondes suisses, les poules et les tableaux sont appariés, les raisons données et les limites de l'appariement suisse. |
| [Résultats, corrections et retraits](doc/fr/results.md) | Saisir un résultat une seule fois, les conflits, les corrections, les retraits et la fin du tournoi. |
| [Bris d'égalité](doc/fr/tiebreakers.md) | Le classement, les bris d'égalité de la bibliothèque, leur ordre et le dernier tirage. |
| [Points d'extension](doc/fr/extension-points.md) | Le dépôt, la source de classement initial, les bris d'égalité et l'événement. |
| [Exceptions](doc/fr/exceptions.md) | `TournamentException` et ses exceptions. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\Tournament\Result;
use Tilttek\Tournament\Stage;
use Tilttek\Tournament\Tournament;
use Tilttek\Tournament\Tournaments;

$tournaments = new Tournaments($store);             // your TournamentStore
$tournaments->start(new Tournament(
    id: 'spring-open',
    seed: 'spring-open-2027',                       // published: anyone can compute the pairings again
    participants: ['m-104', 'm-221', 'm-318', 'm-402', 'm-517'],
    stages: [Stage::swiss('swiss', rounds: 3, qualifiers: 4), Stage::singleElimination('top-4')],
    tiebreakers: ['buchholz', 'sonneborn-berger', 'wins'],
));

$round = $tournaments->prepareRound('spring-open'); // the tables, with their reasons, to show first
$tournaments->publishRound('spring-open', $round);

$tournaments->record('spring-open', $round->key(1), Result::firstWins(), 'm-104');
$tournaments->record('spring-open', $round->key(1), Result::firstWins(), 'm-318'); // Unchanged: counted once

$tournaments->standings('spring-open')->participants();
```

## Prérequis

PHP 8.5 ou une version plus récente. `tilttek/rating` est suggéré, pour `Seeding\RatingSeeding`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
