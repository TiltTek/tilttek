<!-- phil-dev source-sha256=3fb02cce5b6ad7ff17dded27795482ad1a6e5dd0cde76edd2236d5e156de11ed -->
# Installation

```sh
composer require tilttek/quests-bundle tilttek/activity-bundle
```

```php
// config/bundles.php
Tilttek\ActivityBundle\TilttekActivityBundle::class => ['all' => true],
Tilttek\QuestsBundle\TilttekQuestsBundle::class => ['all' => true],
```

Le bundle exige PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4.6 et un service `clock`, que FrameworkBundle
enregistre. Installez aussi, selon vos besoins :

- `phil/almanac`, pour les quêtes qui reviennent à des dates calculées ;
- `doctrine/orm`, pour ajouter les tables au schéma généré par l'ORM, et donc à `doctrine:migrations:diff` ;
- `symfony/console`, pour les commandes ;
- `symfony/twig-bundle` et `symfony/translation`, pour les gabarits ;
- `phil/privacy-bundle`, pour exporter et effacer les quêtes d'un membre avec son compte.

## Les tables

Avec l'ORM, `doctrine:migrations:diff` écrit la migration qui crée les cinq tables décrites dans
[Base de données](database.md). Sans l'ORM, créez-les à partir de `QuestsTables::configure()`.

## La projection

`tilttek/activity-bundle` trouve la projection par son étiquette, `tilttek_activity.projection`. Avec ses projections
en direct, chaque activité enregistrée fait avancer les quêtes dans la même requête. Sinon, lancez sa commande de
rattrapage à intervalles réguliers. Après un changement de règle, reconstruisez la projection, `quests` par défaut, à
partir du moment où le changement s'applique.
