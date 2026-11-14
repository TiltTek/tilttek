<!-- phil-dev source-sha256=676f988bb66041b8e30cd1b125c72444b0a1b469bf3868c694e6e59135c9835e -->
# Lancer avec tilttek/dice-bundle

`tilttek/dice-bundle` lance les dés d'une application Symfony et demande à son port `DiceAppearance` l'apparence des
dés d'un membre. `DiceBundle\DiceSkinsAppearance` est l'adaptateur de ce port : il donne au lanceur l'apparence que
calcule `DiceSkins`, de sorte que les dés qu'un membre lance portent le skin qu'il a choisi ou le dé d'un ensemble qu'il
possède.

L'adaptateur demande `tilttek/dice-bundle` 0.2.0-alpha ou une version plus récente, dont `DieAppearance` porte la
couleur des chiffres et la clé de texture, et dont le port transmet l'accessibilité du spectateur. La bibliothèque n'exige pas le bundle : elle le suggère, et seule une
application qui le déclare charge l'adaptateur.

## Ce que reçoit le lanceur

Pour un membre et un nombre de faces, l'adaptateur retourne un `Tilttek\DiceBundle\Application\DieAppearance` :

| Argument | Valeur |
|---|---|
| `name` | Le libellé du skin, ou le nom du dé que le membre possède |
| `color` | La couleur des faces, `#rrggbb` en minuscules |
| `material` | La matière, une des valeurs de `Material` : `solid`, `translucent`, `pearl`… |
| `image` | Nul : la bibliothèque décrit les dés par des couleurs, une matière et une texture |
| `ink` | La couleur des chiffres et des points, `#rrggbb` en minuscules |
| `texture` | La clé de texture, `pearl/soft` par exemple, ou nul |

L'apparence est trouvée comme le décrit [Utilisation](usage.md) : le dé d'un ensemble que le membre possède et a choisi,
puis le skin qu'il a choisi pour ce nombre de faces, le skin qu'il a choisi pour tous ses dés, et le skin par défaut du
site. Un nombre de faces sous 2 ou au-dessus de `Skin::MAX_SIDES` donne nul, et le lanceur affiche ses dés simples.

Un skin par défaut que le catalogue n'offre pas lance `SkinRefused` : c'est une erreur de configuration, pas un choix de
membre, et l'adaptateur ne la cache pas.

## Déclarer l'adaptateur

La bibliothèque ne fournit aucun bundle : l'application déclare `DiceSkins`, son catalogue et son stockage comme
services, puis l'adaptateur, et nomme l'adaptateur dans l'option `appearance` du bundle.

```yaml
# config/services.yaml
services:
    Tilttek\DiceSkins\Store\FixedSkinCatalog:
        factory: ['Tilttek\DiceSkins\Store\FixedSkinCatalog', 'withBase']

    Tilttek\DiceSkins\DiceSkins:
        arguments:
            $catalog: '@Tilttek\DiceSkins\Store\FixedSkinCatalog'
            $choices: '@App\Dice\DoctrineSkinChoiceStore'     # your Contract\SkinChoiceStore

    Tilttek\DiceSkins\DiceBundle\DiceSkinsAppearance:
        arguments:
            $skins: '@Tilttek\DiceSkins\DiceSkins'
```

```yaml
# config/packages/tilttek_dice.yaml
tilttek_dice:
    appearance: Tilttek\DiceSkins\DiceBundle\DiceSkinsAppearance
```

Avec l'autowiring, `DiceSkinsAppearance` n'a besoin que de `DiceSkins` comme service : déclarez la classe, son catalogue
et son stockage, et nommez-la dans `tilttek_dice.appearance`.

`DiceSkins` et l'adaptateur ne gardent rien entre deux appels : une seule instance de chacun sert toutes les requêtes
d'un *worker*.

## Contraste élevé

Le lanceur transmet au port l'accessibilité du spectateur quand la page la donne
(`dice_roll_detail(result, member, locale, 'high-contrast')`), et l'adaptateur la suit : le contraste élevé pour un
spectateur qui l'a demandé, l'apparence standard pour un autre. Sans elle, l'adaptateur donne l'apparence standard,
sauf s'il est construit avec `Accessibility::HighContrast` :

```yaml
services:
    app.dice_appearance.high_contrast:
        class: Tilttek\DiceSkins\DiceBundle\DiceSkinsAppearance
        arguments:
            $skins: '@Tilttek\DiceSkins\DiceSkins'
            $accessibility: !php/enum Tilttek\DiceSkins\Accessibility::HighContrast
```

En contraste élevé, les chiffres ressortent à 7:1 au moins, la matière est `solid` et il n'y a pas de texture, comme
l'explique [Accessibilité](accessibility.md). Là où l'application dessine elle-même les dés, hors du lanceur, elle
appelle `DiceSkins::appearance()` avec l'`Accessibility` du spectateur.
