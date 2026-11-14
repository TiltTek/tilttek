<!-- phil-dev source-sha256=200357796a092a027df5b8491b153caaca6a8bd8b6c82e41863478a0d41d2b00 -->
# tilttek/dice-skins

**Apparence des dés d'un membre (skins, couleurs, matières et dés possédés) appliquée au lanceur, chiffres toujours lisibles.**

Un membre choisit l'apparence de ses dés : un skin pour tous ses dés, un autre pour son d20, ou un dé d'un ensemble
qu'il possède dans sa collection. La bibliothèque donne au lanceur, pour chaque dé qu'il affiche, l'apparence à dessiner
— la couleur des faces, la couleur des chiffres et des points, la matière, la texture — sous forme de propriétés CSS
personnalisées, d'attributs de données ou de valeurs simples pour un moteur de rendu qui lit du JSON. L'apparence ne
change jamais le hasard : elle habille seulement les dés que le serveur a lancés.

Chaque skin garde ses chiffres lisibles : son encre ressort sur ses faces à 4,5:1 au moins, le contraste que WCAG 2.2
demande du texte au niveau AA, et un skin qui n'y arrive pas est refusé quand on le définit. Un dé d'un ensemble reçoit
l'encre noire ou blanche qui ressort le plus sur sa couleur. Pour un lecteur qui demande un contraste élevé, les
chiffres restent à 7:1 au moins, sur des faces unies et sans texture, et un dé qui ne peut pas l'atteindre est affiché
en noir sur blanc.

C'est une bibliothèque PHP simple. Les skins viennent du site — huit skins de base sont fournis avec la bibliothèque, et
un site ajoute les siens à partir de JSON —, les choix des membres viennent d'un stockage que l'application fournit, et
les dés possédés viennent de `tilttek/collections`, lus par un port. Un adaptateur donne l'apparence au lanceur de `tilttek/dice-bundle`.

## État

| Partie | État |
|---|---|
| `Color`, `Material`, `TextureReference` : les couleurs avec leur luminance et leur contraste WCAG, les matières, les clés de texture | Implémenté et testé |
| `Skin`, `SkinLook`, `SkinDefinitions`, `BaseSkins` : les skins lisibles, leurs apparences par type de dé, lus à partir de JSON | Implémenté et testé |
| `DiceSkins`, `DieAppearance` : les choix des membres et l'apparence de chaque dé, en contraste standard ou élevé | Implémenté et testé |
| `Contract\SkinCatalog`, `Contract\SkinChoiceStore`, `Contract\OwnedDice`, avec des stockages en mémoire | Implémenté et testé |
| `Collections\ShelfOwnedDice` : les dés possédés lus dans `tilttek/collections` | Implémenté et testé |
| `DiceBundle\DiceSkinsAppearance` : l'apparence donnée au lanceur de `tilttek/dice-bundle` 0.2 | Implémenté et testé |
| Exceptions sous une seule interface `DiceSkinsException` | Implémenté et testé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Utilisation](doc/fr/usage.md) | Les skins, les choix, les dés possédés et l'apparence donnée au lanceur. |
| [Accessibilité](doc/fr/accessibility.md) | Le contraste des chiffres, le contraste élevé et ce que garde le moteur de rendu. |
| [Lancer avec tilttek/dice-bundle](doc/fr/dice-bundle.md) | L'adaptateur du port d'apparence du lanceur, et comment le déclarer. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application donne à la bibliothèque. |
| [Tests](doc/fr/testing.md) | Les tests de la bibliothèque, sa cible de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type de la bibliothèque, avec son rôle, et ses exceptions. |

## Démarrage rapide

```php
use Phil\Resource\ResourceReference;
use Tilttek\DiceSkins\Accessibility;
use Tilttek\DiceSkins\DiceSkins;
use Tilttek\DiceSkins\Store\FixedSkinCatalog;
use Tilttek\DiceSkins\Store\InMemorySkinChoiceStore;

$skins = new DiceSkins(FixedSkinCatalog::withBase(), new InMemorySkinChoiceStore());
$member = ResourceReference::of('member', '42');

$skins->choose($member, 'crimson');           // all their dice
$skins->choose($member, 'gold', 20);          // and their d20

$d20 = $skins->appearance($member, 20);
$d20->style();             // "--tilttek-dice-color: #d4a017; --tilttek-dice-ink: #1a1a1a"
$d20->dataAttributes();    // ['data-dice-appearance' => 'Gold', 'data-dice-sides' => '20', 'data-dice-material' => 'metal']

$skins->appearance($member, 6, Accessibility::HighContrast)->toArray();
```

## Prérequis

PHP 8.5 ou une version plus récente, `phil/resource` et `tilttek/collections` 0.1 ; `tilttek/dice-bundle` 0.2.0-alpha ou
une version plus récente pour l'apparence de son lanceur.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
