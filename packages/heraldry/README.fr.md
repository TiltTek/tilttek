<!-- phil-dev source-sha256=5c9641e3e342faaf284972a423c5fa516a1dcc3c97ba3baeb70fad22020b246b -->
# tilttek/heraldry

**Des armoiries d'abord comme modèle : générées à partir d'un germe, blasonnées en anglais et en français, dessinées en SVG.**

Des armoiries sont tenues comme une structure — un champ uni ou divisé, au plus une pièce honorable, au plus un groupe
de meubles sur le champ ou sur la pièce, et au plus une brisure — et tout le reste s'écrit à partir d'elle.
`CoatGenerator` tire des armes d'un germe de 32 octets que le serveur prend dans sa source sécuritaire : le même germe
tire les mêmes armes sur n'importe quel serveur, et chaque écu tiré respecte la règle de couleur. `Blazonry` écrit le
blason en anglais (« Azure, a fess Or between three mullets argent ») et en français (« D’azur à la fasce d’or
accompagnée de trois étoiles d’argent »), chacun avec son ordre, ses articles et ses accords. `SvgRenderer` dessine les
armes avec des formes dessinées pour le paquet — aucune image matricielle, aucune police —, une image accessible avec un
titre et le blason pour description, qu'un assainisseur SVG strict garde entière.

Le paquet s'adresse à un jeu qui donne ses armes à un personnage, à une maison ou à une guilde, à un générateur de monde
qui a besoin de bannières, ou à un livre qui les décrit. C'est une bibliothèque PHP simple, sans dépendances.

## État

| Partie | État |
|---|---|
| Le modèle : `Coat`, `Field`, `Partition`, `Tincture`, `Ordinary`, `Piece`, `Charge`, `ChargeGroup`, `Arrangement`, `Brisure` | Implémenté et testé |
| `TinctureRule` et `Violation` : la règle de couleur vérifiée sur n'importe quel écu | Implémenté et testé |
| `Generator\CoatGenerator` et `Generator\Seed` : des armes semées et reproductibles qui respectent la règle | Implémenté et testé |
| `Blazon\Blazonry`, `Blazon\EnglishBlazon`, `Blazon\FrenchBlazon` : le blason écrit en anglais et en français | Implémenté et testé |
| `Svg\SvgRenderer`, `Svg\Palette` : le dessin SVG, quatre formes d'écu et les fourrures | Implémenté et testé |
| La lecture d'un blason écrit pour le remettre dans le modèle | Pas commencé |

## Documentation

| Document | Contenu |
|---|---|
| [Installation](doc/fr/installation.md) | Les prérequis, l'installation et le câblage. |
| [Le modèle](doc/fr/model.md) | Les champs, les partitions, les émaux, les pièces, les meubles, les brisures, et ce qu'un écu peut contenir. |
| [La règle de couleur](doc/fr/tincture-rule.md) | Métal, couleur et fourrure ; où la règle lie et où elle ne lie pas. |
| [Générer des armes](doc/fr/generating.md) | Les germes, la reproductibilité, et ce que le générateur tire, et à quelle fréquence. |
| [Le blason](doc/fr/blazon.md) | L'ordre et la grammaire des blasons anglais et français. |
| [Le dessin](doc/fr/drawing.md) | L'image SVG, son accessibilité, ses identifiants, la palette et l'assainisseur. |
| [Points d'extension](doc/fr/extension-points.md) | Ce qu'une application peut remplacer ou ajouter. |
| [Tests](doc/fr/testing.md) | Les tests du paquet, ses cibles de fuzzing et ses bancs d'essai. |
| [Référence](doc/fr/reference.md) | Chaque type public, avec son rôle. |

## Démarrage rapide

```php
use Tilttek\Heraldry\Blazon\Blazonry;
use Tilttek\Heraldry\Generator\CoatGenerator;
use Tilttek\Heraldry\Generator\Seed;
use Tilttek\Heraldry\Language;
use Tilttek\Heraldry\Svg\SvgRenderer;

$seed = Seed::generate();                          // keep $seed->hex() to draw the same arms again
$coat = new CoatGenerator()->generate($seed);

new Blazonry()->write($coat);                      // for example "Purpure, on a bend sinister Or a lozenge azure"
new Blazonry()->write($coat, Language::French);    // « De pourpre à la barre d’or chargée d’un losange d’azur »
$svg = new SvgRenderer()->render($coat);           // an accessible <svg> element
```

## Prérequis

PHP 8.5 ou une version plus récente, avec `mbstring` et `sodium`.

## Contribuer

Les contributions sont les bienvenues sur le monodépôt de PHIL : lisez le [guide de contribution](CONTRIBUTING.md) et
le [code de conduite](CODE_OF_CONDUCT.md), en anglais. Signalez une vulnérabilité en privé, comme l'explique la
[politique de sécurité](SECURITY.md), jamais dans un ticket public.

## Licence

MIT, © Philippe Gamache, doing business as Le Gnome Archiviste; a Tilttek project. Voir [LICENSE](LICENSE).
