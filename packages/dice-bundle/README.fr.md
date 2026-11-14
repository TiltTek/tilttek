<!-- phil-dev source-sha256=ff3e3ed8f5db74bf279b3bc68aa696c50468d957b506079ffd4d52b90585e162 -->
# tilttek/dice-bundle

**Des dés pour une application Symfony : configurés, lancés, gardés pour être rejoués, vérifiés dans les formulaires et
affichés dans les gabarits.**

tilttek/dice-bundle intègre `tilttek/dice` à Symfony. Il construit la bibliothèque à partir de la configuration : ses
limites, les dés personnalisés de l'application, les notations d'autres langues. Il lance les dés à partir d'une source
sûre, garde les lancers rejouables dans un journal Doctrine, par membre et par table, chacun annoncé par un événement,
et les rejoue. Il garde les macros d'un site, d'une table
et d'un membre, et produit des lancers vérifiables. Il fournit un champ de formulaire avec sa contrainte, des filtres et
des fonctions Twig, et un détail accessible d'un lancer. Un point d'extension habille les dés d'un membre de
l'apparence d'un jeu qu'il possède (couleur, encre, matériau, texture), et les nombres s'y lisent toujours. Le bundle
n'anime pas les dés, ne dessine pas la page du lanceur, ne décide pas qui
peut lancer et ne possède aucun jeu de dés : l'application s'en charge.

Il s'adresse aux applications Symfony qui lancent des dés pour leurs membres : une page de lanceur de dés, un forum de
jeu par messages, une fiche de personnage, une table de jeu. Une application sans Symfony prend `tilttek/dice` seul.

C'est un `AbstractBundle` sur Symfony 8.2 avec Doctrine ORM 3. Le type de formulaire, la contrainte et l'extension Twig
ne sont enregistrés que si Symfony Form, Validator et Twig sont installés. Un membre est une `ResourceReference` opaque
de `phil/resource` : le bundle ne lit ni nom, ni adresse, ni compte.

La conception est sobre : la classe du bundle lit la configuration, `DiceServices` enregistre les services, `DiceRolls`
est le service que l'application appelle, `DiceMacros` garde et résout les macros, `RecordedRoll` est une ligne du
journal, `RollRecorded` l'annonce et `DiceAppearance` est le port qui habille les dés d'un membre.

## État

| Partie | État |
|---|---|
| Configuration : limites, dés personnalisés, notations, apparence, domaine de traduction, stockage, gestionnaire d'entités | Implémentée et testée |
| `DiceRolls` : analyse, lecture de DiceParser, écriture, traduction, lancer, lancers rejouables, rejeu, lancers vérifiables et vérification, lancers d'un membre et leur effacement | Implémenté et testé |
| Journal des lancers rejouables dans Doctrine ou en mémoire : contexte d'un lancer, lancers par propriétaire, par contexte et plusieurs à la fois | Implémenté et testé |
| Événement `RollRecorded` | Implémenté et testé |
| `DiceMacros` : macros d'un site, d'une table et d'un membre, nommées par jeton, dans Doctrine ou en mémoire | Implémenté et testé |
| Point d'extension `DiceAppearance` : couleur, encre, matériau, image, texture ; une encre qui se lit à 4,5:1 quand aucune n'est donnée | Implémenté et testé |
| Contrainte `DiceExpression` et champ `DiceExpressionType` | Implémentés et testés |
| Twig : `dice_notation`, `dice_dialect`, `dice_roll`, `dice_roll_detail`, `dice_text` | Implémenté et testé |
| Dialectes ajoutés par étiquette | Implémentés et testés |

## Documentation

| Document | Ce qu'il couvre |
|---|---|
| [Installation](doc/fr/installation.md) | Prérequis, enregistrement du bundle, table du journal. |
| [Configuration](doc/fr/configuration.md) | Chaque option de `tilttek_dice`, avec sa valeur par défaut. |
| [Lancer les dés](doc/fr/rolling.md) | `DiceRolls` : lancers simples, rejouables et vérifiables, le journal et ses contextes, l'effacement des lancers d'un membre. |
| [Macros](doc/fr/macros.md) | `DiceMacros` : les rayons d'un site, d'une table et d'un membre ; définir, résoudre et effacer des macros. |
| [Formulaires et validation](doc/fr/forms.md) | `DiceExpressionType` et la contrainte `DiceExpression`. |
| [Gabarits](doc/fr/templates.md) | Les filtres et fonctions Twig, le détail d'un lancer, ses classes et ses mots. |
| [Points d'extension](doc/fr/extension-points.md) | `DiceAppearance` et l'encre lisible, les dialectes par étiquette, le port du journal, `RollRecorded`. |
| [Exceptions](doc/fr/exceptions.md) | Ce que le bundle lève. |
| [Tests](doc/fr/testing.md) | Les tests du bundle, et comment tester une application qui lance des dés. |
| [Référence](doc/fr/reference.md) | Chaque type du bundle, avec son rôle. |
| [Mise à niveau](doc/fr/upgrading.md) | De 0.1 à 0.2 : la colonne de contexte, le port du journal, l'apparence. |

## Démarrage rapide

```sh
composer require tilttek/dice-bundle
```

```yaml
# config/packages/tilttek_dice.yaml
tilttek_dice:
    custom_dice:
        coin:
            faces:
                - { id: heads, label: 'Heads', value: 1, shape: circle }
                - { id: tails, label: 'Tails', value: 0, shape: square }
```

```php
use Tilttek\DiceBundle\Application\DiceRolls;

public function __invoke(DiceRolls $dice): Response
{
    $recorded = $dice->rollReplayable('4d6kh3 + 1d{coin}');

    return $this->render('dice/roll.html.twig', ['replay' => $dice->replay($recorded->id())]);
}
```

```twig
{{ dice_roll_detail(replay.result) }}
```

## Prérequis

PHP 8.5 ou plus récent, Symfony 8.2, Doctrine ORM 3.7, `tilttek/dice` et `phil/resource` 0.1. Symfony Form, Validator,
Twig et Translation sont facultatifs.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md). Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
