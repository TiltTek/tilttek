# Installation

```sh
composer require tilttek/accessible-dice
```

The bundle needs PHP 8.5 or later, Symfony 8.2 and `tilttek/dice-bundle`, whose custom dice and limits it uses.
Register both:

```php
// config/bundles.php
return [
    Tilttek\DiceBundle\TilttekDiceBundle::class => ['all' => true],
    Tilttek\AccessibleDice\TilttekAccessibleDiceBundle::class => ['all' => true],
];
```

## The form

The form is a Twig template; its controller is registered by StimulusBundle from the package's `assets/package.json`,
with its stylesheet, `@tilttek/accessible-dice/styles/accessible-dice.css`:

```sh
composer require symfony/twig-bundle symfony/stimulus-bundle symfony/translation
```

With AssetMapper, the controller and its stylesheet are imported by the application's `controllers.json`; check that
both are enabled there after the package is installed.

## Optional packages

| Package | What it adds |
|---|---|
| `symfony/twig-bundle` | `accessible_dice()` and the form |
| `symfony/stimulus-bundle` | The controller: the expression shown, the announcements, reading aloud |
| `symfony/translation` | The words of the form and the announcements in the reader's language, and the labels of the faces |
| `tilttek/dice-tower` | A dice tower whose rolls the form announces |
