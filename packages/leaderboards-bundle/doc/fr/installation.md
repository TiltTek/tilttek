<!-- phil-dev source-sha256=d2123e62b41df18b1e239e4ea781c66ea5792e59508cb93943f34b32ffa52f11 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4.5 et `doctrine/doctrine-bundle`.
- `tilttek/activity-bundle`, pour garder le journal d'activité que lit la projection.
- Facultatifs : `doctrine/orm` (les tables dans le schéma généré), `symfony/console` (les commandes),
  `symfony/twig-bundle` et `symfony/translation` (les gabarits), `symfony/ux-twig-component` et les recettes `Card`,
  `Badge` et `Empty` d'un kit de l'UX Toolkit (l'écran de ligue et le classement d'équipes), la recette `Celebration` du
  kit PHIL UX 0.5.0-alpha ou plus récent (la célébration du sommet), `symfony/scheduler` (le calendrier),
  `symfony/cache` avec l'extension redis, Relay ou Predis (les scores dans Valkey à partir d'un DSN), ou le client
  propre à l'application, valkey-glide-php compris, `phil/privacy-bundle` (exportation et effacement).

## Installer

```sh
composer require tilttek/leaderboards-bundle tilttek/activity-bundle
```

Enregistrez les deux bundles, configurez les classements ([Configuration](configuration.md)), puis laissez l'ORM voir
les tables :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

La projection, nommée `leaderboards`, est trouvée par `tilttek/activity-bundle` grâce à son étiquette : elle suit le
journal en direct, `tilttek:activity:catch-up leaderboards` la rattrape, et
`tilttek:activity:rebuild leaderboards --since=…` rejoue une période après la modification d'un classement. Activez le
calendrier, ou exécutez `tilttek:leaderboards:snapshot`, `tilttek:leaderboards:settle` et `tilttek:leaderboards:slide`
à partir d'un *cron*, pour figer les périodes terminées, régler les semaines des ligues et faire glisser les fenêtres
([Planification](scheduling.md)).

Pour l'écran de ligue, installez les recettes du kit ([Gabarits](templates.md#the-recipes-of-the-kit)) :

```sh
bin/console ux:install card badge empty --kit=github.com/parler-haut-interagir-librement/ux-kit
```
