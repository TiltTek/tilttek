<!-- phil-dev source-sha256=96846f7defe70e8731dbb10035757f1c87ec8792ce5a2113be0fe7ae4ea2d69c -->
# tilttek/guilds

**Guildes de membres, ouvertes ou sur demande, menées par un fondateur et des administrateurs, avec une capacité et un objectif de groupe que leurs membres atteignent ensemble.**

tilttek/guilds garde les communautés durables d'une application : une guilde, un club, une compagnie de joueurs. Un
membre fonde une guilde sous une référence que l'application donne, `guild:44` ; les autres y entrent aussitôt quand
elle est ouverte, ou par une demande que son fondateur ou un administrateur approuve. Le fondateur nomme les
administrateurs, remet la guilde à un autre membre ou la dissout ; les administrateurs approuvent et refusent les
demandes, retirent des membres et modifient la guilde. Une guilde compte au plus sa capacité, de 2 à 1000 membres, et
quelques entrées de métadonnées que l'application lit : une devise, une bannière, une langue. Personne n'est jamais
retiré pour cause d'inactivité : un membre part, ou est retiré par quelqu'un qui en a le droit.

Une guilde est une communauté durable, non un groupe pour une seule quête. Ses membres forment le cercle que lisent les
autres paquets : l'équipe d'une quête d'équipe de `tilttek/quests`, par `GuildTeamMembership`, et le cercle parmi lequel
un classement de `tilttek/leaderboards` les range. À côté, la bibliothèque garde la **piste de groupe** de la guilde :
un objectif que son fondateur ou un administrateur ouvre — *jouer 200 parties en décembre* — que les contributions de
points de chaque membre font avancer ensemble, jusqu'à ce que sa cible soit atteinte, que son échéance passe ou qu'il
soit fermé. La piste ne classe personne dans la guilde : elle donne le total, le nombre de membres qui ont apporté des
points, et à chaque membre sa propre part.

Elle convient à un site communautaire, à un site de jeux ou à une plateforme de clubs. C'est une bibliothèque PHP
simple dont les dépendances sont `phil/resource`, la référence opaque d'un membre ou d'une guilde, `psr/clock`, et
`tilttek/quests`, dont elle implémente le contrat d'équipe. Les membres sont des références que l'application donne,
`member:44` : la bibliothèque ne lit jamais un nom, un courriel ni un compte. Les points sont des compteurs de
l'application, jamais de l'argent.

## État

| Partie | État |
|---|---|
| `Guild`, `Admission`, `Metadata`, `Role`, `Membership`, `JoinRequest` : les guildes, leur admission, leur capacité, leurs métadonnées et leurs rôles | Implémenté et testé |
| `Guilds` : fonder, rejoindre, demander, approuver, refuser, quitter, retirer, promouvoir, rétrograder, transférer, modifier, dissoudre, lire, effacer | Implémenté et testé |
| `Goal`, `Contribution`, `GoalProgress`, `Goals` : la piste de groupe vers un objectif commun, avec clés, échéances et effacement | Implémenté et testé |
| `GuildTeamMembership` : les guildes comme équipes de `tilttek/quests` | Implémenté et testé |
| `Contract\GuildStore`, `Contract\GoalStore`, avec leurs implémentations en mémoire | Implémenté et testé |
| `Contract\GuildObserver`, `GuildChanged`, `GoalReached` : ce qu'on annonce, des identifiants seulement | Implémenté et testé |
| `Limits` : guildes par membre, demandes en attente par guilde, objectifs ouverts par guilde | Implémenté et testé |
| Un bundle Symfony : dépôts Doctrine, services, formulaires et traductions | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Guildes et membres](doc/fr/guilds.md) | Admission, rôles, capacité, métadonnées, demandes, transfert, dissolution, effacement. |
| [La piste de groupe](doc/fr/goals.md) | Objectifs, contributions, clés, échéances, progression et ce qu'on annonce. |
| [Quêtes et classements](doc/fr/quests-and-leaderboards.md) | Une guilde comme équipe d'une quête, et comme cercle d'un classement. |
| [Points d'extension](doc/fr/extension-points.md) | Les magasins, les observateurs, les limites et l'horloge. |
| [Exceptions](doc/fr/exceptions.md) | `GuildsException` et ses exceptions, avec les refus et leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, et les tests du code qui utilise des guildes. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/guilds
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Guilds\Admission;
use Tilttek\Guilds\Goals;
use Tilttek\Guilds\Guilds;
use Tilttek\Guilds\Store\InMemoryGoalStore;
use Tilttek\Guilds\Store\InMemoryGuildStore;

$guilds = new Guilds(new InMemoryGuildStore(), $clock);
$goals = new Goals(new InMemoryGoalStore(), $guilds, $clock);

$guild = ResourceReference::of('guild', '44');
$founder = ResourceReference::of('member', '1');
$member = ResourceReference::of('member', '2');

$guilds->found($guild, 'The Lantern Bearers', $founder, Admission::OnRequest);
$guilds->join($guild, $member);                  // a JoinRequest
$guilds->approve($guild, $member, $founder);     // a Membership

$goals->open('lanterns-december', $guild, 'Play 200 games in December', 200, $founder);
$goals->contribute('lanterns-december', $member, 3, 'game:9812');
$goals->progress('lanterns-december')->percent(); // 1
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
