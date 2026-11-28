<!-- phil-dev source-sha256=7229c56d3e671bb6487567ae14a08ac36a6e950dc6738a02267041148bf16650 -->
# tilttek/celebrations

**Une boîte de récompenses : chaque réussite qu'un membre obtient, de n'importe quel paquet, y attend, est groupée avec
son genre et est célébrée une seule fois.**

tilttek/celebrations rassemble ce qu'un membre obtient sur un site — un badge, un niveau, une quête accomplie, un
ensemble complété — dans une seule boîte. Les écouteurs de l'application transforment les événements de ses paquets en
`Achievement` : le membre, la source qui l'a donné, `badges`, son genre, `badge`, ce qui a été obtenu,
`badge.first-roll`, et l'occurrence qui le rend unique, `badge.first-roll#1`. Une réussite signalée deux fois — un avis
livré de nouveau, un message repris — est gardée une seule fois : le membre n'est donc jamais félicité deux fois pour la
même chose.

Les entrées en attente sont **groupées** par source et par genre : trois badges obtenus pendant une séance font un seul
« 3 nouveaux badges », non trois fenêtres. La fenêtre montre le **prochain** groupe, le plus ancien obtenu en premier,
puis le suivant, jusqu'à ce que le membre les ait tous vus ; la cloche lit le **compte**. Une récompense reprise — un
badge révoqué — est **retirée** et n'est jamais montrée. Une réussite peut porter le moment après lequel elle ne vaut
plus la peine d'être montrée — un trophée saisonnier après sa saison — : son entrée devient alors **caduque**, alors que
la récompense demeure, car la boîte décide seulement de ce qui est célébré, jamais de ce qui est détenu.

Chaque membre **choisit** : désactiver la fenêtre et garder la boîte, combien elle bouge — pleinement, de façon réduite
ou pas du tout —, et si elle joue un son, désactivé tant que le membre ne l'active pas. Un appareil qui demande de
réduire les mouvements (`prefers-reduced-motion`) les réduit toujours.

Il s'adresse à un site de communauté, à un site de jeu ou à toute application qui donne des récompenses par plusieurs
paquets. C'est une bibliothèque PHP simple dont les dépendances sont `phil/resource`, la référence opaque d'un membre,
et `psr/clock` ; elle ne dépend d'aucun paquet de récompenses, que ses réussites ne font que nommer. Les membres sont
des références que l'application donne : la bibliothèque ne lit jamais un nom, un courriel ni un compte, et ses
événements ne portent que des identifiants.

## État

| Partie | État |
|---|---|
| `Achievement` : ce qu'un membre a obtenu, de quelle source, avec ses paramètres et son moment d'arrêter d'être montré | Implémenté et testé |
| `Celebrations` : recevoir une fois, retirer, grouper, compter, prochain, marquer comme vu, historique, préférences, effacer | Implémenté et testé |
| `Entry`, `EntryState` : en attente, vue, retirée ou caduque, avec l'ordre de réception | Implémenté et testé |
| `Group`, `Celebration` : les entrées d'une source et d'un genre, et ce que la fenêtre montre ensuite | Implémenté et testé |
| `Preferences`, `Motion` : la fenêtre active ou non, le mouvement, le son, la réduction des mouvements de l'appareil | Implémenté et testé |
| `Contract\CelebrationStore`, avec `Store\InMemoryCelebrationStore` | Implémenté et testé |
| `Contract\CelebrationObserver`, `CelebrationChanged` : ce qui est signalé, identifiants seulement | Implémenté et testé |
| `Limits` : entrées en attente et entrées par membre, entrées montrées par groupe | Implémenté et testé |
| Un bundle Symfony : dépôt Doctrine, écouteurs pour les paquets de récompenses, la fenêtre en contrôleur Stimulus, traductions | Pas commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [La boîte](doc/fr/inbox.md) | Recevoir les réussites une fois, les groupes, les entrées vues, retirées et caduques, les limites, l'historique et l'effacement. |
| [Célébrer](doc/fr/celebrating.md) | La prochaine célébration, les préférences des membres, le mouvement et le son, et les écouteurs pour d'autres paquets. |
| [Points d'extension](doc/fr/extension-points.md) | Le dépôt, les observateurs, les limites et l'horloge. |
| [Exceptions](doc/fr/exceptions.md) | `CelebrationException` et son exception, avec leurs messages. |
| [Tests](doc/fr/testing.md) | Les tests et le banc d'essai de la bibliothèque, et le test du code qui utilise la boîte. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/celebrations
```

```php
use Phil\Resource\ResourceReference;
use Tilttek\Celebrations\Achievement;
use Tilttek\Celebrations\Celebrations;
use Tilttek\Celebrations\Store\InMemoryCelebrationStore;

$celebrations = new Celebrations(new InMemoryCelebrationStore(), $clock);
$member = ResourceReference::of('member', '42');

$celebrations->receive(new Achievement($member, 'badges', 'badge', 'badge.first-roll', 'badge.first-roll#1', $earnedAt));
$celebrations->receive(new Achievement($member, 'badges', 'badge', 'badge.night-owl', 'badge.night-owl#1', $earnedAt));
$celebrations->receive(new Achievement($member, 'progression', 'level', 'track.reader', 'track.reader#5', $earnedAt, ['level' => 5]));

$celebrations->count($member);                         // 3, for the bell

$next = $celebrations->next($member, deviceReducesMotion: false);
$next->group->count;                                   // 2: "2 new badges"
$next->remaining;                                      // 1 more group after this one
$celebrations->markSeen($member, $next->group->sequences());
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
