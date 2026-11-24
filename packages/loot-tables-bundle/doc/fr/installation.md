<!-- phil-dev source-sha256=3a7a89afd69487653b254b10a395c22245d0716e75484ab9566d6972e3e1f057 -->
# Installation

## Prérequis

PHP 8.5 ou plus récent avec l'extension sodium, Symfony 8.2 avec son composant Workflow, Doctrine ORM 3.8 et
DoctrineBundle, `phil/commerce-bundle`, dont le bundle utilise le workflow de commande et les tireurs d'options, et
`tilttek/loot-tables`. Les écrans et la fiche de production sont facultatifs : le
bundle enregistre chacun quand ses paquets sont installés.

| Paquet | Pour |
|---|---|
| `symfony/form`, `symfony/options-resolver` | `PhysicalDiceType`, le formulaire des dés physiques |
| `symfony/ux-twig-component`, `symfony/twig-bundle` | `TilttekLootTables:Commitments`, les tirages de l'acheteur, et `TilttekLootTables:PhysicalDice`, le formulaire sur la page de la caisse |
| `phil/ux-kit` | Les recettes `Card` et `Badge` avec lesquelles les tirages de l'acheteur sont dessinés, par le Toolkit de Symfony UX |
| `symfony/console` | `tilttek:loot-tables:replay`, la commande qui relance un tirage |
| `symfony/translation` | Les mots du formulaire et des composants, en anglais et en français |
| `phil/paged-media` | `LootSheetPdf`, la fiche de production |

## Installer

```sh
composer require tilttek/loot-tables-bundle
```

La recette de `recipe/0.1` enregistre le bundle et copie sa configuration ; tant qu'elle n'est pas publiée dans un dépôt
de recettes, activez le bundle à la main après le noyau du commerce :

```php
// config/bundles.php
return [
    // …
    Phil\CommerceBundle\PhilCommerceBundle::class => ['all' => true],
    Tilttek\LootTablesBundle\TilttekLootTablesBundle::class => ['all' => true],
];
```

et écrivez sa configuration, `config/packages/tilttek_loot_tables.yaml`, comme l'explique
[la configuration](configuration.md).

Pour les composants, enregistrez `TwigComponentBundle`, et installez les recettes du kit UX avec lesquelles les tirages
de l'acheteur sont dessinés :

```sh
php bin/console ux:install card badge
```

## La table des tirages

Le bundle associe son entité pour Doctrine quand DoctrineBundle est installé. Créez la table `tilttek_loot_draw` avec
une migration :

```sh
php bin/console doctrine:migrations:diff
php bin/console doctrine:migrations:migrate
```

| Colonne | Contient |
|---|---|
| `id` | 32 chiffres hexadécimaux dérivés de la commande, de la ligne, de l'option et de l'unité |
| `order_number`, `line`, `option_code`, `unit` | à quelle unité de quelle option correspond le tirage ; `order_number` est indexée |
| `drawer` | le tireur que nomme l'option |
| `seed`, `commitment` | le germe secret, publié dans la preuve une fois révélé, et son SHA-256, montré avant le paiement ; `null` pour des dés physiques |
| `texts` | les entrées parmi lesquelles un texte est tiré, telles qu'elles étaient à l'engagement |
| `result` | la source et les faces, une fois révélées ou saisies |
| `proof` | la preuve publiée d'un tirage vérifiable |
| `drawn` | ce que garde la ligne de commande |
| `created_at`, `revealed_at` | quand il a été engagé ou saisi, et révélé |

Le germe d'un tirage pas encore révélé est un secret : quiconque le lit connaît le tirage avant le paiement. Gardez la
table hors de portée de ce que voient les acheteurs, comme toute table du magasin.
