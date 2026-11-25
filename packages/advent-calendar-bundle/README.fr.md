<!-- phil-dev source-sha256=70993bb9d94eec1ca151b58ed0af814ad8893b72fe5cb06ab0dc5ae3b2ae344a -->
# tilttek/advent-calendar-bundle

**Des calendriers de l'avent dans une application Symfony : une case par jour, un écran qui fonctionne sans JavaScript,
une célébration pour chaque première ouverture, et un jour manqué qui n'est pas perdu.**

tilttek/advent-calendar-bundle intègre `tilttek/advent-calendar` à Symfony. Un site déclare ses calendriers dans la
configuration, ou les modifie dans la base de données par l'ORM avec le formulaire du bundle ; chaque calendrier choisit
combien de temps une case reste ouvrable une fois son jour venu — jusqu'à la fermeture du calendrier par défaut, de
sorte qu'un membre qui a manqué un jour ouvre sa case plus tard, le jour même seulement, ou pendant quelques jours de
rattrapage. Les ouvertures sont gardées dans Doctrine DBAL, dans une table que le bundle ajoute au schéma que génère
l'ORM, dont la clé primaire fait qu'une ouverture n'arrive qu'une fois même quand deux requêtes se disputent. L'écran
d'un calendrier est construit avec les recettes d'un kit de UX Toolkit et fonctionne sans JavaScript : chaque case
ouvrable est un formulaire simple. Un contrôleur Stimulus ouvre une case en arrière-plan, met la case ouverte à sa
place, y déplace le focus et l'annonce aux lecteurs d'écran. La première ouverture d'une case est signalée par
l'événement `BoxOpened` de la bibliothèque, à partir duquel l'application accorde la surprise, et célébrée par la
`Celebration` du kit — confettis ou feux d'artifice là où le mouvement n'est pas réduit, un message dans une région
vivante partout. Des commandes listent les calendriers, montrent les cases de l'un d'eux et vérifient celles de la base
de données ; les ouvertures d'un membre sont exportées et effacées avec son compte par `phil/privacy-bundle`.

Il s'adresse à une communauté ou à une boutique qui veut un rituel saisonnier sans pression : rien à acheter, aucune
case qui disparaît par défaut, aucun classement de qui a ouvert en premier. Les récompenses restent dans l'application,
qui sait déjà comment créditer des points ou décerner un badge.

Il exige `tilttek/advent-calendar`, Doctrine DBAL, les composants de formulaire, HTTP, de routage et de CSRF de Symfony,
et Twig ; l'ORM, la console, les composants Twig avec un kit de UX Toolkit et `phil/privacy-bundle` sont facultatifs, et
le bundle n'enregistre ce qui les utilise que lorsqu'ils sont installés. Les membres sont des références que donne
l'application, `member:44` : le bundle ne lit jamais un nom ni une adresse courriel, et refuse une référence qui en a
l'air.

## État

| Partie | État |
|---|---|
| Configuration du magasin, de la connexion, du gestionnaire d'entités, du membre, de la mise en page, du kit, de la célébration, des calendriers et du genre de vie privée | Implémenté et testé |
| Calendriers de la configuration, et de la base de données par l'ORM avec `CalendarType` | Implémenté et testé |
| `DbalOpeningStore` et sa table, ajoutée au schéma par `OpeningsSchemaListener` ; `InMemoryOpenings` pour les tests | Implémenté et testé |
| L'écran d'un calendrier et l'ouverture d'une case, avec et sans JavaScript, en anglais et en français | Implémenté et testé |
| La célébration d'une première ouverture par `BoxOpened` et la `Celebration` du kit | Implémenté et testé |
| Le contrôleur Stimulus `tilttek--advent-calendar--boxes` | Implémenté et testé |
| Les commandes `tilttek:advent-calendar:list`, `:show` et `:check` | Implémenté et testé |
| Export et effacement d'un membre pour `phil/privacy-bundle` | Implémenté et testé |
| La recette Symfony Flex, dans `recipe/` | Préparée, non publiée |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation, les routes, la table, le membre et la recette. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Calendriers](doc/fr/calendars.md) | Les calendriers de la configuration et de la base de données, les politiques d'ouverture, le formulaire et l'octroi des surprises. |
| [Écrans](doc/fr/screens.md) | Les routes, les gabarits, les recettes du kit, le contrôleur Stimulus, la célébration et l'accessibilité. |
| [Base de données](doc/fr/database.md) | Les deux tables et leurs clés. |
| [Console](doc/fr/console.md) | Les commandes qui listent, montrent et vérifient les calendriers. |
| [Vie privée](doc/fr/privacy.md) | Ce qui est gardé d'un membre, son export et son effacement. |
| [Points d'extension](doc/fr/extension-points.md) | Remplacer le membre, un catalogue, le magasin, les gabarits. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Tilttek\AdventCalendarBundle\TilttekAdventCalendarBundle::class => ['all' => true],
```

```yaml
# config/packages/tilttek_advent_calendar.yaml
tilttek_advent_calendar:
    member_resolver: App\Advent\CurrentMember
    celebration: { enabled: true }
    calendars:
        advent-2026:
            title: Advent 2026
            timezone: America/Toronto
            first_date: '2026-12-01'
            surprises: [{ kind: points, quantity: 10 }, { kind: badge, reference: advent-week }]

# config/routes/tilttek_advent_calendar.yaml
tilttek_advent_calendar:
    resource: '@TilttekAdventCalendarBundle/config/routes.php'
```

Un membre ouvre alors `/advent-calendar/advent-2026`, et l'application accorde chaque surprise à partir de
`BoxOpened`.

## Prérequis

PHP 8.5 ou une version plus récente avec `ext-intl`, Symfony 8.2, Doctrine DBAL 4.6, Twig 3.31.1.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
