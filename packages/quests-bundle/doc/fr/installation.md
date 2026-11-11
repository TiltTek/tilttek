<!-- phil-dev source-sha256=564166d5797a8a8bacd6c74e8f5032bfff0a831ace81ef0e1275c083a0cc8adb -->
# Installation

```sh
composer require tilttek/quests-bundle tilttek/activity-bundle
```

```php
// config/bundles.php
Tilttek\ActivityBundle\TilttekActivityBundle::class => ['all' => true],
Tilttek\QuestsBundle\TilttekQuestsBundle::class => ['all' => true],
```

Le bundle exige PHP 8.5 ou plus récent, Symfony 8.2, Doctrine DBAL 4.5 et un service `clock`, que FrameworkBundle
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
