<!-- phil-dev source-sha256=209520d7afd72c8955000b0356618bd659faf8178567ce3372194bf3cb9d6278 -->
# tilttek/progression-bundle

**Des niveaux, des saisons et des séries pour une application Symfony, à partir du journal de l'activité de ses
membres.**

tilttek/progression-bundle intègre `tilttek/progression` à Symfony. Les pistes (leurs courbes, règles, saisons et
jalons) et les séries sont écrites dans la configuration et vérifiées à la compilation du conteneur. L'expérience, les
jalons atteints et les séries sont gardés dans Doctrine DBAL, dans cinq tables que le bundle ajoute au schéma que
génère l'ORM, pour que les migrations de l'application les créent. La projection qui transforme l'activité des membres
en expérience et en séries est étiquetée pour `tilttek/activity-bundle`, qui la tient à jour à chaque geste, la
rattrape et la reconstruit. Une montée ou une baisse de niveau, un jalon atteint et une série brisée deviennent des
événements du répartiteur de l'application. Des fonctions Twig et deux gabarits montrent la barre de progression d'une
piste et une série. Une commande balaie les séries brisées, une autre montre la progression d'un sujet. La progression
d'un membre est exportée et effacée avec son compte par `phil/privacy-bundle`.

Il s'adresse à une application Symfony qui récompense ce que font ses membres et leur montre le chemin parcouru. La
bibliothèque seule convient à une application sans Symfony.

Il requiert `tilttek/progression`, Doctrine DBAL, le répartiteur d'événements PSR, et la configuration et le conteneur
de Symfony. L'ORM, la console, Twig, le traducteur et `phil/privacy-bundle` sont facultatifs, et le bundle n'enregistre
ce qui les utilise que lorsqu'ils sont installés.

La conception suit celle de la bibliothèque : les magasins implémentent ses trois contrats, `ProgressionServices`
branche les services, `DispatchingObserver` transforme ce qu'annonce la bibliothèque en événements, et
`ProgressionExtension` est le côté Twig.

## État

| Partie | État |
|---|---|
| Configuration des pistes, des séries, des fuseaux horaires et des magasins, vérifiée à la compilation | Implémenté et testé |
| `DbalExperienceStore`, `DbalMilestoneStore`, `DbalStreakStore` et les cinq tables, avec l'écouteur de schéma | Implémenté et testé |
| La projection étiquetée pour `tilttek/activity-bundle`, le fuseau horaire du membre tiré du contexte de l'activité | Implémenté et testé |
| Événements `LevelUp`, `LevelDown`, `ReachedMilestone` et `StreakBroken` | Implémenté et testé |
| Fonctions Twig et gabarits de la barre de progression et de la série, en anglais et en français | Implémenté et testé |
| Commandes `tilttek:progression:sweep` et `tilttek:progression:show` | Implémenté et testé |
| Exportation et effacement d'un membre pour `phil/privacy-bundle` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, les tables et la projection. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Base de données](doc/fr/database.md) | Les cinq tables, leurs clés et l'insertion écrite une seule fois. |
| [Événements](doc/fr/events.md) | Les événements que transmet le bundle et comment récompenser un jalon. |
| [Gabarits](doc/fr/templates.md) | Les fonctions Twig, la barre de progression et la série, et leurs traductions. |
| [Commandes](doc/fr/commands.md) | Balayer les séries brisées et montrer un sujet. |
| [Vie privée](doc/fr/privacy.md) | Ce que contient l'exportation d'un membre et ce que son effacement retire. |
| [Points d'extension](doc/fr/extension-points.md) | Remplacer un magasin, le fuseau horaire d'un membre, l'observateur. |
| [Exceptions](doc/fr/exceptions.md) | Ce que le bundle lève. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et tester une application qui l'utilise. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Tilttek\ActivityBundle\TilttekActivityBundle::class => ['all' => true],
Tilttek\ProgressionBundle\TilttekProgressionBundle::class => ['all' => true],
```

```yaml
# config/packages/tilttek_progression.yaml
tilttek_progression:
    timezone: America/Toronto
    tracks:
        player:
            curve: { type: quadratic, base: 50, levels: 60 }
            rules: { games: { verbs: ['game.played'], experience: 20, cap: { amount: 100, period: day } } }
            milestones: { veteran: { level: 10 } }
    streaks:
        daily-play: { verbs: ['game.played'], grace: { allowed: 1, window: 7 } }
```

```twig
{{ include('@TilttekProgression/bar.html.twig', {standing: tilttek_progression_standing('member:44', 'player')}) }}
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4.5.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
