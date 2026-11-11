<!-- phil-dev source-sha256=e5f3e45dae112a452dab593c355f1d22903a87527dd4c86fcbb5d6c348ab0c74 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent.
- Les extensions `ctype` et `mbstring`, qu'utilisent l'analyseur lexical et les limites, et `sodium`, dont
  `sodium_bin2hex()` écrit les germes et les identifiants en temps constant.

La bibliothèque n'a aucune dépendance Composer.

## Installer

```sh
composer require tilttek/dice
```

Rien n'est enregistré et rien n'est configuré : construisez un `DiceRoller`, ou les parties dont vous avez besoin, là
où vous les utilisez. Une application Symfony installe `tilttek/dice-bundle`, qui les construit à partir de sa
configuration.

```php
use Tilttek\Dice\DiceRoller;
use Tilttek\Dice\Limits;

$dice = new DiceRoller(new Limits(dice: 50, sides: 100));
```
