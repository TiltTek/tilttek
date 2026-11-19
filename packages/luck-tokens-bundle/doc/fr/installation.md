<!-- phil-dev source-sha256=c6aa5038cb442b5ec3d285a30b523faa40356ba583503b80c7684d60c1937faf -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4.5.
- `tilttek/luck-tokens`, les jetons eux-mêmes, et `phil/resource`, dont les références nomment les tables et les
  détenteurs.
- `psr/event-dispatcher` ; FrameworkBundle fournit le répartiteur, et son service `clock` date les entrées.
- Facultatifs : `doctrine/orm` pour l'écouteur du schéma, `symfony/lock` pour les verrous, `symfony/security-bundle`
  pour l'accès par les voters de sécurité, `phil/privacy-bundle` pour l'effacement d'un membre.

## Activer le bundle

```sh
composer require tilttek/luck-tokens-bundle
```

```php
// config/bundles.php
return [
    // …
    Tilttek\LuckTokensBundle\TilttekLuckTokensBundle::class => ['all' => true],
];
```

Configurez les tables ([Configuration](configuration.md)), puis laissez Doctrine écrire la migration des tables :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

Sans l'ORM, créez les tables à partir de `LuckTokensTables::tables()` dans une migration à vous : il les donne pour la
plateforme de la connexion, comme le décrit [Les registres dans la base de données](database.md).
