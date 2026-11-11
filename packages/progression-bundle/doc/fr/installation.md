<!-- phil-dev source-sha256=fca1541e71e224509b2836964b6c0991d9f00ddb4553f50c35a63b9fa516a300 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4.5 et `doctrine/doctrine-bundle`.
- `tilttek/activity-bundle`, pour garder le journal d'activité que lit la projection.
- Facultatif : `doctrine/orm` (les tables dans le schéma généré), `symfony/console` (les commandes),
  `symfony/twig-bundle` et `symfony/translation` (les gabarits), `phil/privacy-bundle` (exportation et effacement).

## Installer

```sh
composer require tilttek/progression-bundle tilttek/activity-bundle
```

Enregistrez les deux bundles, configurez les pistes et les séries ([Configuration](configuration.md)), puis laissez
l'ORM voir les tables :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

La projection, nommée `progression`, est trouvée par `tilttek/activity-bundle` grâce à son étiquette : elle suit le
journal en direct, `tilttek:activity:catch-up progression` la rattrape, et
`tilttek:activity:rebuild progression --since=…` rejoue une période après le changement d'une règle. Planifiez
`tilttek:progression:sweep` une fois par heure pour annoncer les séries brisées.
