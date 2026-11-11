<!-- phil-dev source-sha256=f46bc139768bdd34f4d464223d62e51918c3684b311cda6cc9371e373a1a4dd6 -->
# Installation

## Prérequis

- PHP 8.5 ou plus récent, Symfony 8.2, Doctrine ORM 3.7 avec DoctrineBundle 3.
- `tilttek/dice` et `phil/resource` 0.1, installés avec le bundle.
- Facultatifs : Symfony Form pour le champ, Symfony Validator pour la contrainte, TwigBundle pour les gabarits, Symfony
  Translation pour les mots d'un lancer et les libellés des faces.

## Installer

```sh
composer require tilttek/dice-bundle
```

Enregistrez le bundle si Flex ne le fait pas :

```php
// config/bundles.php
return [
    // …
    Tilttek\DiceBundle\TilttekDiceBundle::class => ['all' => true],
];
```

## La table du journal

Le bundle associe ses entités pour Doctrine. Créez la table `tilttek_dice_roll` avec une migration :

```sh
bin/console doctrine:migrations:diff
bin/console doctrine:migrations:migrate
```

| Colonne | Type | Contient |
|---|---|---|
| `id` | `varchar(32)` | L'identifiant de rejeu, la clé primaire |
| `expression` | `text` | L'expression dans sa forme canonique |
| `seed` | `varchar(64)` | Le germe, en hexadécimal |
| `result` | `json` | Le résultat, tel que l'écrit `PoolResult::toArray()` |
| `owner` | `varchar(320)`, nul | Qui a lancé, en `ResourceReference` ; indexée |
| `rolled_at` | `datetime_immutable` | Quand |
| `rounding` | `varchar(32)`, `NegativeInfinity` par défaut | Comment les divisions du lancer ont été arrondies, par le nom du `RoundingMode` de PHP |

## La table des macros

Les macros des sites, des tables et des membres sont gardées dans `tilttek_dice_macro`, créée par la même migration :

| Colonne | Type | Contient |
|---|---|---|
| `level` | `varchar(16)` | `site`, `table` ou `member` ; fait partie de la clé primaire |
| `owner` | `varchar(320)` | Le propriétaire, en `ResourceReference` ; fait partie de la clé primaire, indexée |
| `name` | `varchar(32)` | Le nom, en minuscules ; fait partie de la clé primaire |
| `expression` | `text` | L'expression, dans la notation de la bibliothèque |

Une application qui ne garde ni lancer ni macro règle `store: memory`.
