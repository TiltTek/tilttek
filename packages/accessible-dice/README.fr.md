<!-- phil-dev source-sha256=090db6499ed6a46d0316b3836e526bae7942116ef00905272b3bf3bf86f27bb7 -->
# tilttek/accessible-dice

**Des dés que tout le monde peut lancer : grandes cibles, faces énoncées et décrites, résultats annoncés aux lecteurs d'écran.**

tilttek/accessible-dice est un bundle Symfony construit sur `tilttek/dice-bundle`. Il dessine un formulaire de dés dont
chaque dé est un grand choix, dont les dés personnalisés ont chaque face décrite par son libellé, sa forme et sa
valeur — jamais par sa couleur seule —, et dont le résultat est annoncé dans une région dynamique polie, par une phrase
qui dit chaque dé. Le formulaire envoie le choix lui-même, et le serveur construit l'expression à partir de lui, de
sorte qu'il lance de la même façon avec ou sans son script. Quand une tour de dés de `tilttek/dice-tower` est sur la
même page, chacun de ses jets est aussi annoncé. Rien ne bouge pour un spectateur qui a demandé un mouvement réduit, et
rien n'est lu à voix haute à moins que le spectateur ne le demande.

## État

| Partie | État |
|---|---|
| Les dés offerts, standards et personnalisés, et l'expression d'un choix, vérifiée | Implémenté et testé |
| Les faces des dés personnalisés décrites par libellé, forme et valeur, en anglais et en français | Implémenté et testé |
| La phrase avec laquelle un jet est annoncé | Implémenté et testé |
| `accessible_dice()` et son formulaire : grandes cibles, région dynamique polie, mouvement réduit, aucun son par défaut | Implémenté et testé |
| Le contrôleur Stimulus : l'expression montrée, les jets d'une tour de dés annoncés, la lecture à voix haute sur demande | Implémenté et testé, dans un navigateur |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'enregistrement du bundle, le contrôleur et sa feuille de style. |
| [Configuration](doc/fr/configuration.md) | Chaque clé, sa valeur par défaut et ce qu'elle règle. |
| [Le formulaire](doc/fr/form.md) | `accessible_dice()`, le choix envoyé, son lancer sur le serveur et l'annonce du résultat. |
| [Accessibilité](doc/fr/accessibility.md) | Ce que le formulaire fait pour les lecteurs d'écran, les claviers, le mouvement et le son. |
| [Points d'extension](doc/fr/extension-points.md) | Les gabarits, les styles, les traductions et l'écoute d'autres dés. |
| [Tests](doc/fr/testing.md) | Les tests du bundle et sa suite de navigateur. |
| [Référence](doc/fr/reference.md) | Chaque type et chaque service du bundle. |

## Démarrage rapide

```sh
composer require tilttek/accessible-dice
```

```php
// config/bundles.php
return [
    Tilttek\DiceBundle\TilttekDiceBundle::class => ['all' => true],
    Tilttek\AccessibleDice\TilttekAccessibleDiceBundle::class => ['all' => true],
];
```

```twig
{{ accessible_dice(path('roll'), result: result) }}
```

```php
$expression = $accessibleDice->expression($request->request->all('dice'));
$result = $diceRolls->roll($expression);
```

## Prérequis

PHP 8.5 ou une version plus récente, Symfony 8.2 et `tilttek/dice-bundle` ; Twig, le traducteur et StimulusBundle pour
le formulaire.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
