<!-- phil-dev source-sha256=9883ab3f79c02e3dc589b6a3444f8e464a0ac2410c7d7080638f6d3f2abfbf64 -->
# Installation

```sh
composer require tilttek/dice-tower
```

Le bundle exige PHP 8.5 ou une version plus récente, Symfony 8.2 et `tilttek/dice-bundle`, dont le journal garde les
jets. Enregistrez les deux :

```php
// config/bundles.php
return [
    Tilttek\DiceBundle\TilttekDiceBundle::class => ['all' => true],
    Tilttek\DiceTower\TilttekDiceTowerBundle::class => ['all' => true],
];
```

## Le journal

Avec DoctrineBundle, la table `tilttek_dice_tower_drop` est mappée : créez-la avec une migration.

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

## Qui peut regarder une table

Personne ne le peut tant que l'application n'a pas dit qui : nommez un service qui implémente `TableAccess`, voir les
[points d'extension](extension-points.md).

```yaml
# config/packages/tilttek_dice_tower.yaml
tilttek_dice_tower:
    table_access: App\Dice\TableMembers
```

## Le hub

`symfony/mercure-bundle` fournit le hub et le témoin d'autorisation des spectateurs :

```sh
composer require symfony/mercure-bundle
```

Son service de hub, `Symfony\Component\Mercure\HubInterface`, est celui sur lequel le bundle pousse. Sans lui, rien
n'est poussé : la surcouche montre les entrées de la page et relit celles qu'elle a manquées quand elle est dessinée
de nouveau.

## La route des entrées manquées

```yaml
# config/routes/tilttek_dice_tower.yaml
tilttek_dice_tower:
    resource: '@TilttekDiceTowerBundle/config/routes.php'
```

## La surcouche

La surcouche est dessinée avec la recette `Card` du kit PHIL UX, que le Symfony UX Toolkit installe, et son contrôleur
est enregistré par StimulusBundle à partir du `assets/package.json` du paquet :

```sh
composer require symfony/stimulus-bundle symfony/ux-twig-component symfony/ux-toolkit
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
```

## Paquets facultatifs

| Paquet | Ce qu'il ajoute |
|---|---|
| `symfony/mercure-bundle` | Le hub où chaque nouvelle entrée est poussée, et le témoin d'autorisation des spectateurs |
| `symfony/twig-bundle`, `symfony/ux-twig-component` | `dice_tower()` et la surcouche |
| `symfony/stimulus-bundle` | Le contrôleur qui ajoute les nouvelles entrées sur la page |
| `symfony/routing` | La route des entrées manquées |
| `symfony/messenger` | Les relais joints par un bus de messages |
