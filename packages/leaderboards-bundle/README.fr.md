<!-- phil-dev source-sha256=594ec2b5eb8bb53541ce1405a7022230a9a19bc8dcc0b3022e0e935018f24169 -->
# tilttek/leaderboards-bundle

**Des classements pour une application Symfony, tenus à jour à partir de l'activité de ses membres, chaque membre
montré seulement selon son choix.**

tilttek/leaderboards-bundle intègre `tilttek/leaderboards` à Symfony. Les classements (leurs mesures, leurs verbes et
leurs conditions de contexte, leurs périodes et leurs fuseaux horaires, leurs saisons et leurs fenêtres glissantes,
leurs égalités et leurs critères de départage, la taille de leurs instantanés, leurs ligues, leurs classements par
objet, leurs titres et la chance des équipes) s'écrivent dans la configuration et sont vérifiés à la compilation du
conteneur. Les contributions, les scores, les instantanés, les choix d'apparence des membres, ainsi que les paliers et
les divisions des ligues et les premières places prises sont gardés dans Doctrine DBAL, dans neuf tables que le bundle ajoute au schéma que génère
l'ORM, pour que les migrations de l'application les créent ; les scores peuvent plutôt vivre dans les ensembles triés
de Valkey, connecté par Symfony Cache ou par le client de l'application. La projection qui tient les scores à jour porte l'étiquette de
`tilttek/activity-bundle`, qui suit le journal à chaque geste, la rattrape et la reconstruit. Un instantané pris, une
semaine de ligue réglée et un membre qui a atteint le sommet (premier une fois la période close, ou en tête à mesure que
son score change, selon le choix du site) deviennent des événements du répartiteur de l'application, pour récompenser
les premiers et féliciter ceux qui sont montés, chacun portant la façon dont le membre a choisi de paraître ; l'écran
que le membre ouvre ensuite célèbre le sommet par la recette `Celebration` accessible du kit PHIL UX. Des fonctions et des gabarits Twig affichent un classement, un instantané, la division
d'un membre ou son écran de ligue tels qu'un membre les voit, et un classement d'équipes (l'écran de ligue et les
équipes sont construits des recettes d'un kit de l'UX Toolkit). Des commandes prennent les instantanés dus, règlent les
semaines dues, font glisser les fenêtres et affichent un classement tel que le public le voit ; Symfony Scheduler peut
exécuter les trois premières. Enfin, les scores, les lignes, le choix, les paliers, les divisions et les premières places d'un membre sont
exportés et effacés avec son compte par `phil/privacy-bundle`.

Il s'adresse à une application Symfony qui classe ses membres selon ce qu'ils font et qui ne doit pas exposer ceux qui
n'ont pas choisi d'être montrés. La bibliothèque seule convient à une application sans Symfony.

Il exige `tilttek/leaderboards`, Doctrine DBAL, le répartiteur d'événements PSR, et la configuration et le conteneur de
Symfony. L'ORM, la console, Twig, les composants Twig, le traducteur, Symfony Scheduler, Symfony Cache et
`phil/privacy-bundle` sont facultatifs, et le bundle n'enregistre ce qui s'en sert que s'ils sont installés.

La conception suit celle de la bibliothèque : les stockages implémentent ses quatre contrats, `LeaderboardsServices`
assemble les services, `DispatchingObserver` change un instantané et un règlement en événements `SnapshotTaken`,
`LeagueSettled` et `TopReached`, `LeadWatch` surveille les scores gardés pour la première place quand le site célèbre à
un changement de score, `LeaderboardsExtension` est le côté Twig et `LeaderboardsSchedule` le côté planifié.

## État

| Partie | État |
|---|---|
| Configuration des classements, des fuseaux horaires et des stockages, vérifiée à la compilation | Implémentée et testée |
| Critères de départage, fenêtres glissantes, classements par objet, titres et chance des équipes dans la configuration | Implémentés et testés |
| Les scores dans Valkey par la connexion de Symfony Cache ou par le client de l'application | Implémentés et testés |
| `DbalScoreStore`, `DbalSnapshotStore`, `DbalAppearanceStore`, `DbalLeagueStore`, `DbalLeadStore` et les neuf tables, avec l'écouteur du schéma, sur SQLite, MySQL et PostgreSQL | Implémentés et testés |
| La projection étiquetée pour `tilttek/activity-bundle` | Implémentée et testée |
| Les ligues hebdomadaires des classements qui en déclarent une | Implémentées et testées |
| Les événements `SnapshotTaken`, `LeagueSettled` et `TopReached` | Implémentés et testés |
| La célébration du sommet, sur la recette `Celebration` du kit, à la clôture de la période ou à un changement de score | Implémentée et testée |
| Fonctions Twig et gabarits de classement et de division, en anglais et en français | Implémentés et testés |
| L'écran de ligue et le classement d'équipes, construits des recettes `Card`, `Badge` et `Empty` du kit | Implémentés et testés |
| Commandes `tilttek:leaderboards:snapshot`, `tilttek:leaderboards:settle`, `tilttek:leaderboards:slide` et `tilttek:leaderboards:show` | Implémentées et testées |
| Le calendrier `tilttek_leaderboards` sur Symfony Scheduler | Implémenté et testé |
| Exportation et effacement d'un membre pour `phil/privacy-bundle` | Implémentés et testés |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, installation, les tables, la projection et les instantanés planifiés. |
| [Configuration](doc/fr/configuration.md) | Chaque option, avec un exemple. |
| [Base de données](doc/fr/database.md) | Les neuf tables, leurs clés, l'insertion écrite une seule fois et les scores dans Valkey. |
| [Événements](doc/fr/events.md) | Les événements que transmettent un instantané, une semaine réglée et un sommet atteint, et comment récompenser les premiers. |
| [Gabarits](doc/fr/templates.md) | Les fonctions Twig, les gabarits de classement, de division, de ligue, d'équipes et de célébration, les recettes du kit, les noms des membres nommés et les traductions. |
| [Commandes](doc/fr/commands.md) | Prendre les instantanés dus, régler les semaines dues, faire glisser les fenêtres et afficher un classement. |
| [Planification](doc/fr/scheduling.md) | Les instantanés, les règlements et les fenêtres dus exécutés par Symfony Scheduler ou par un *cron*. |
| [Vie privée](doc/fr/privacy.md) | Ce que contient l'exportation d'un membre et ce que retire son effacement. |
| [Points d'extension](doc/fr/extension-points.md) | Remplacer un stockage, le client Valkey, les classements, l'observateur, les écrans. |
| [Exceptions](doc/fr/exceptions.md) | Ce que le bundle lève. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et tester une application qui s'en sert. |
| [Référence](doc/fr/reference.md) | Chaque classe et chaque service du bundle. |

## Démarrage rapide

```php
// config/bundles.php
Tilttek\ActivityBundle\TilttekActivityBundle::class => ['all' => true],
Tilttek\LeaderboardsBundle\TilttekLeaderboardsBundle::class => ['all' => true],
```

```yaml
# config/packages/tilttek_leaderboards.yaml
tilttek_leaderboards:
    timezone: America/Toronto
    boards:
        games-month: { measure: count, verbs: ['game.played'], period: month }
        level: { measure: latest }
        weekly-xp: { measure: total, verbs: ['lesson.done'], period: week, league: { tiers: [bronze, silver, gold] } }
    schedule: true
```

```twig
{{ include('@TilttekLeaderboards/standing.html.twig', {standing: tilttek_leaderboards_standing('games-month', 'member:44'), names: names}) }}
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4.5.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
