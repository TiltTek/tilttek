<!-- phil-dev source-sha256=9d3f9c562f08c01c8972d7216d9f8d62961f854dc538b2f0f7cda86dafaf4c77 -->
# tilttek/seasonal-events

**Les saisons et leurs événements : les quêtes, les objets et les décorations d'une période, dans le fuseau horaire du
site, qui reviennent chaque année.**

tilttek/seasonal-events garde les saisons d'un site — un festival d'hiver, une saison de l'épouvante, une fête des
récoltes — et les événements qu'elles contiennent. Une saison vient une seule fois, entre deux dates, ou chaque année
pour un nombre de jours à partir d'une date que donne une règle annuelle de la RFC 5545 : le 15 décembre, le quatrième
jeudi de novembre. Ses jours se comptent dans son fuseau horaire, à partir de minuit à l'horloge murale, malgré les
changements d'heure. Une saison apporte des quêtes, des objets et des décorations tant qu'elle dure ; un événement qui
s'y trouve ajoute les siens pour certains de ses jours, et peut multiplier les gains d'un registre — seulement s'il est
annoncé au moins un jour d'avance, afin que rien ne se multiplie par surprise ; le plus haut multiplicateur s'applique
quand des événements se recoupent. Chaque venue d'une saison a un code, la date de son premier jour, de sorte que ce
qu'un membre gagne pendant elle se distingue de l'année suivante.

Les autres paquets lisent ce qui est en cours par un seul port, `ActiveContent` : les quêtes ouvertes maintenant, les
objets offerts, les décorations affichées, le multiplicateur d'un registre. Ils ne lisent jamais les saisons
elles-mêmes, et la bibliothèque ne dépend d'aucun d'eux : des passerelles facultatives ouvrent une quête de
`tilttek/quests` avec une saison et listent les objets de `tilttek/collections` qu'elle apporte, quand ces paquets sont
installés.

Elle s'adresse à une communauté ou à un jeu qui veut du contenu saisonnier sans l'activer et le désactiver à la main :
les saisons sont déclarées d'avance, dans le code, une configuration ou une base de données, et répondent pour
n'importe quel moment. Il n'y a pas de passe de saison à acheter ni d'objet qui ne revient jamais : une saison qui
vient chaque année apporte de nouveau ses objets.

C'est une bibliothèque PHP simple dont les dépendances sont `phil/recurrence`, qui lit les règles annuelles,
`phil/resource`, les références des quêtes, des objets et des décorations, et `psr/clock`. Elle ne garde aucun état
entre deux questions.

## État

| Partie | État |
|---|---|
| `SeasonSchedule` : une fois, ou chaque année par une règle annuelle de la RFC 5545, dans un fuseau horaire, jusqu'à 183 jours | Implémenté et testé |
| `Season`, `SeasonalEvent`, `Content`, `ContentKind`, `Multiplier` : ce qu'apportent une saison et ses événements | Implémenté et testé |
| `SeasonalEvents`, `Snapshot`, `ActiveSeason`, `ActiveEvent`, `Period` : ce qui est en cours à un moment, et ce qui est annoncé | Implémenté et testé |
| `ActiveContent` : le port que lisent les autres paquets | Implémenté et testé |
| `SeasonCatalogue`, `InMemorySeasonCatalogue`, `SeasonReader` : les saisons depuis le code ou des tableaux simples, et le retour | Implémenté et testé |
| Passerelles vers `tilttek/quests` et `tilttek/collections` | Implémenté et testé |
| Exceptions sous une seule interface `SeasonalEventsException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le raccordement de la bibliothèque. |
| [Saisons et événements](doc/fr/seasons-and-events.md) | Les calendriers, les fuseaux horaires, la récurrence, les contenus, les événements, les annonces et les multiplicateurs. |
| [Ce qui est en cours](doc/fr/what-is-on.md) | Les questions d'un moment, l'instantané, les événements annoncés et le port `ActiveContent`. |
| [Définitions](doc/fr/definitions.md) | Des saisons écrites en tableaux simples — une configuration, du JSON ou du YAML —, lues et réécrites. |
| [Passerelles](doc/fr/bridges.md) | Les quêtes, les collections et un calendrier de l'avent pendant une saison. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque : le catalogue, l'horloge. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, le fuzzing et les bancs d'essai, et comment tester le code qui lit les saisons. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle. |

## Démarrage rapide

```sh
composer require tilttek/seasonal-events
```

```php
use Symfony\Component\Clock\Clock;
use Tilttek\SeasonalEvents\{Content, Multiplier, Season, SeasonalEvent, SeasonalEvents, SeasonSchedule};
use Tilttek\SeasonalEvents\Store\InMemorySeasonCatalogue;

$winter = new Season(
    'winter-festival',
    SeasonSchedule::yearly('America/Toronto', '2026-12-15', 23),
    [Content::quest('quest:winter-hunt'), Content::decoration('decoration:snowflakes')],
    [new SeasonalEvent('gift-week', fromDay: 8, days: 7, contents: [Content::item('item:wrapped-die')], multiplier: new Multiplier('points', 200), announceDays: 7)],
);

$seasons = new SeasonalEvents(new InMemorySeasonCatalogue([$winter]), new Clock());

$seasons->at()->decorations();          // [decoration:snowflakes] while the festival is on
$seasons->multiplier('points')?->apply(10); // 20 during the gift week
```

## Prérequis

PHP 8.5 ou une version plus récente.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
