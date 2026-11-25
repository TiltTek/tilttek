<!-- phil-dev source-sha256=caa02240311011c143c2c0f573030c0db4e1d7da0fe24e46eca881fa9e529a44 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, `tilttek/dice-probability` et `tilttek/dice-bundle`, dont le `DiceRoller` lit les
expressions avec les notations, les limites et les dés personnalisés de l'application. Twig, le traducteur et Symfony
UX sont facultatifs :

| Paquet | Pour |
|---|---|
| `symfony/twig-bundle`, `symfony/ux-twig-component` | Les fonctions Twig, le filtre, le tableau et le graphique |
| `symfony/ux-live-component` | Le composant qui calcule les probabilités pendant la saisie |
| `symfony/rate-limiter` | La limite de débit du composant, activée par défaut : nécessaire avec `symfony/ux-live-component` sauf si elle est désactivée |
| `symfony/translation` | Les mots du tableau, du graphique et du composant, en anglais et en français |
| `ext-gmp` | Les fractions dont les termes ont plus de 18 chiffres, écrites sous leur forme irréductible |

Symfony UX 3.8.1 ou plus récent (TwigComponent 3.8) et Twig 3.31.1 ou plus récent, pour leurs correctifs de sécurité : les
versions plus anciennes sont refusées.

## Installer

```sh
composer require tilttek/dice-probability-bundle
```

Enregistrez les deux bundles, `TilttekDiceBundle` en premier :

```php
// config/bundles.php
return [
    // …
    Tilttek\DiceBundle\TilttekDiceBundle::class => ['all' => true],
    Tilttek\DiceProbabilityBundle\TilttekDiceProbabilityBundle::class => ['all' => true],
];
```

Sans `TilttekDiceBundle`, le conteneur refuse de compiler et l'indique. Pour les gabarits, enregistrez
`TwigComponentBundle` ; pour le composant, aussi `LiveComponentBundle` et `StimulusBundle`, ainsi que les routes des
Live Components, comme l'explique leur documentation.

## Les recettes du kit UX

Le tableau, le graphique et le composant sont dessinés avec des recettes du kit PHIL UX, que le Symfony UX Toolkit
installe dans l'application, rien n'étant produit depuis `vendor/` :

```sh
php bin/console ux:install alert card chart field input radio-group table --kit=github.com/parler-haut-interagir-librement/ux-kit
```

Les recettes deviennent les composants propres de l'application, mis en forme par phil-ux, la feuille de style de
`phil/ux-css` ; une application peut les modifier. Le bundle les trouve sous le sous-répertoire des composants de la
configuration `ux_toolkit`, ou de `kit_subdirectory`, comme l'explique la [configuration](configuration.md).
