<!-- phil-dev source-sha256=9da5f7f7b2fd9bf40d18275f6d558d655c06aaba9866554055c7810681ffa2e4 -->
# tilttek/dice-probability-bundle

**Les probabilités d'expressions de dés sur une page Symfony : un tableau toujours présent, un graphique, et un
composant qui calcule pendant la saisie.**

tilttek/dice-probability-bundle intègre `tilttek/dice-probability` à Symfony. Il lit les expressions avec le
`DiceRoller` de `tilttek/dice-bundle`, ses notations, ses limites et ses dés personnalisés. Il prend dans la
configuration la profondeur des explosions, la limite de travail, les estimations et l'arrondi, et fournit
`DiceStatistics` pour les expressions écrites en texte. Dans les gabarits, des fonctions Twig donnent une distribution
ou une comparaison, et les dessinent sous forme de résumé, de graphique à barres et de tableau accessible. Le Live
Component `TilttekDiceProbability:Distribution` fait de même pendant la saisie d'une expression, et d'une seconde à lui
comparer. Les écrans sont dessinés avec les recettes du kit PHIL UX, que l'application installe avec le Symfony UX
Toolkit.

N'importe qui peut écrire dans le composant : le bundle borne donc le travail de chaque requête. Il applique les limites
de la bibliothèque, pèse les groupes qu'une estimation cherche avant qu'un seul dé soit lancé, plafonne les lignes d'un
tableau, réduit les fractions dans un travail borné, et limite le débit du composant par adresse IPv4 ou par /64 IPv6,
ce qui est activé par défaut.

## État

| Partie | État |
|---|---|
| Configuration : profondeur d'explosion, limite de travail, échantillons, germe, arrondi, bornes d'une requête | Implémenté et testé |
| `DiceStatistics` : distributions, comparaisons et événements d'expressions écrites en texte | Implémenté et testé |
| Groupes trop longs à chercher, refusés avant l'estimation, `GroupSearch` | Implémenté et testé |
| Fonctions Twig, filtre à réduction bornée, tableau et graphique, en anglais et en français | Implémenté et testé |
| Live Component, avec les refus du bundle traduits | Implémenté et testé |
| Limite de débit du Live Component par adresse IPv4 ou par /64 IPv6, activée par défaut | Implémenté et testé |
| Écrans dessinés avec les recettes du kit PHIL UX, dans le sous-répertoire du Toolkit | Implémenté et testé |
| Événements de `chance()` dans Twig et dans le composant | Non commencé |
| Langues autres que l'anglais et le français | Non commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, paquets facultatifs, enregistrement du bundle et installation des recettes du kit. |
| [Configuration](doc/fr/configuration.md) | Chaque clé, sa valeur par défaut et ce qu'elle règle ; la recherche des groupes et la limite de débit. |
| [Statistiques en PHP](doc/fr/statistics.md) | `DiceStatistics` et `Rounding`. |
| [Gabarits](doc/fr/templates.md) | Les fonctions Twig, le tableau et le graphique, leur accessibilité, et le composant. |
| [Points d'extension](doc/fr/extension-points.md) | Remplacer les gabarits, ajouter une langue, les événements de l'application. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester les pages qui montrent des probabilités. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque service du bundle. |

## Démarrage rapide

```sh
composer require tilttek/dice-probability-bundle
```

```php
// config/bundles.php
return [
    Tilttek\DiceBundle\TilttekDiceBundle::class => ['all' => true],
    Tilttek\DiceProbabilityBundle\TilttekDiceProbabilityBundle::class => ['all' => true],
];
```

```sh
php bin/console ux:install alert card chart field input radio-group table --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```twig
{{ tilttek_dice_probabilities('4d6kh3') }}
<twig:TilttekDiceProbability:Distribution expression="1d20+5" versus="2d10+4" />
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, `tilttek/dice-probability` et `tilttek/dice-bundle` ; Twig, le traducteur,
Symfony UX et les recettes du kit PHIL UX pour les gabarits et le composant, avec `symfony/rate-limiter` pour sa limite de
débit ; `ext-gmp` pour les longues fractions.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et le
[code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
