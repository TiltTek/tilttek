<!-- phil-dev source-sha256=656aa4b5ff7f9a5f0f9739331fdce0ebc7ec2f2be3b1fbeb8161506767623b5f -->
# tilttek/dice-tower

**La tour de dés partagée d'une table : chaque jet est vu par tous les joueurs de la table, dans l'ordre, au fil de sa chute.**

tilttek/dice-tower est un bundle Symfony construit sur `tilttek/dice-bundle`. Un jet du journal des dés est déposé
dans la tour d'une table ; la tour garde où il est tombé, dans un journal à elle, et pousse la nouvelle entrée sur un
sujet Mercure privé de la table, auquel seuls les spectateurs que l'application autorise s'abonnent. Un spectateur
absent lit ce qu'il a manqué dans le journal, par position : rien n'est gardé en mémoire d'une requête à l'autre. Les
entrées portent des identifiants et le résultat du jet, jamais le nom d'une personne. Une surcouche Twig et Stimulus,
dessinée avec le kit PHIL UX, montre la tour et ajoute chaque nouveau jet ; des relais portent chaque entrée vers
d'autres canaux, par des adaptateurs qui sont des paquets à part.

## État

| Partie | État |
|---|---|
| Le journal des tours, dans Doctrine ou en mémoire, ses positions données par la base de données | Implémenté et testé |
| `DiceTower` : jets déposés, entrées lues dans l'ordre, entrées manquées relues, effacement d'un lanceur | Implémenté et testé |
| Le sujet privé de chaque table sur un hub Mercure, et l'autorisation de ses spectateurs | Implémenté et testé |
| La route des entrées manquées, pour les spectateurs autorisés seulement | Implémenté et testé |
| `dice_tower()`, la surcouche dessinée avec le kit PHIL UX et son contrôleur Stimulus, en anglais et en français | Implémenté et testé |
| Les relais, immédiats ou par un bus de messages | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, la route, le hub et les recettes du kit. |
| [Configuration](doc/fr/configuration.md) | Chaque clé, sa valeur par défaut et ce qu'elle règle. |
| [La tour en PHP](doc/fr/tower.md) | Lancer et déposer, lire les entrées, la charge utile, la vie privée et les processus de longue durée. |
| [La surcouche](doc/fr/overlay.md) | `dice_tower()`, la surcouche, son contrôleur Stimulus et ses événements. |
| [Points d'extension](doc/fr/extension-points.md) | Qui peut regarder une table, les relais vers d'autres canaux, les éditeurs, les gabarits et les traductions. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui utilise une tour. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque service du bundle. |

## Démarrage rapide

```sh
composer require tilttek/dice-tower symfony/mercure-bundle
php bin/console ux:install card --kit=github.com/parler-haut-interagir-librement/ux-kit
```

```php
// config/bundles.php
return [
    Tilttek\DiceBundle\TilttekDiceBundle::class => ['all' => true],
    Tilttek\DiceTower\TilttekDiceTowerBundle::class => ['all' => true],
];
```

```yaml
# config/packages/tilttek_dice_tower.yaml
tilttek_dice_tower:
    table_access: App\Dice\TableMembers

# config/routes/tilttek_dice_tower.yaml
tilttek_dice_tower:
    resource: '@TilttekDiceTowerBundle/config/routes.php'
```

```php
$tower->roll(ResourceReference::of('table', '7'), '1d20+5', ResourceReference::of('member', '42'));
```

```twig
{{ dice_tower('table:7', {'member:42': 'Aelis'}) }}
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2, `tilttek/dice-bundle`, Doctrine ORM pour le journal et un hub
Mercure pour les entrées en direct ; Twig, Symfony UX et les recettes du kit PHIL UX pour la surcouche.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
