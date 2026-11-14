<!-- phil-dev source-sha256=73543868c932b73f620a6b6d40bac147cc5915b81c6ced8b9e9f3c0dbe5bf906 -->
# Utilisation

## Les skins

Un `Skin` a une clé, un libellé, le `SkinLook` de ses dés et, pour certains types de dés, un aspect propre par nombre
de faces. Un aspect est la couleur des faces, la couleur des chiffres et des points — l'encre —, un `Material` et,
s'il y en a une, une `TextureReference`.

La bibliothèque fournit huit skins de base, `BaseSkins::all()` : `ivory` — le skin par défaut —, `obsidian`, `crimson`,
`forest`, `ocean`, `amethyst`, `gold` et `high-contrast`. Un site ajoute les siens à partir de JSON, comme les écrit
`Skin::toArray()` :

```json
[
    {"key": "ember", "label": "Ember", "face": "#9b1b30", "ink": "#ffffff", "material": "pearl",
     "texture": "pearl/soft", "types": {"20": {"face": "#1b1b1f", "ink": "#f5f5f5"}}}
]
```

```php
$catalog = FixedSkinCatalog::withBase($json);        // the base skins, then the site's
$catalog = new FixedSkinCatalog(SkinDefinitions::fromJson($json));   // the site's only
```

L'aspect d'un type reprend de l'aspect du skin ce qu'il ne dit pas. Une couleur s'écrit `#rgb` ou `#rrggbb` ; une
matière est l'une de `solid`, `translucent`, `pearl`, `glitter`, `marble`, `metal`, `wood`, `stone` et `gem` ; une
texture est une clé que le moteur de rendu résout en une image qui lui est propre, jamais une adresse.
`SkinDefinitions` refuse, avec `InvalidSkin`, un champ qu'il ne connaît pas, une valeur du mauvais type, deux skins de
même clé, un aspect dont les chiffres ressortent à moins de 4,5:1, plus de 256 skins ou de 32 types par skin, et un
texte de plus de 256 Kio ou de plus de 8 niveaux de profondeur.

## Les choix

```php
$skins->choose($member, 'ember');         // for all the member's dice
$skins->choose($member, 'gold', 20);      // for their d20
$skins->choices($member);                 // the one for all their dice first, then by number of sides
$skins->clear($member, 20);               // back to the skin for all their dice
$skins->eraseMember($member);             // every choice forgotten
```

`choose()` refuse avec `SkinRefused` un skin que le catalogue n'offre pas, et avec `InvalidSkin` un nombre de faces
hors de 2 à 1000. Un skin que le site cesse d'offrir est écarté : les dés du membre retombent sur le choix suivant.

## Les dés possédés

Un membre qui possède des dés dans `tilttek/collections` en choisit un pour son nombre de faces sur son étagère, avec
`DiceShelf::choose()`. `DiceSkins::owned()` liste les dés qu'il possède, par le port `Contract\OwnedDice`, pour la page
où il choisit. Un dé échangé n'est plus affiché : l'étagère ne le donne plus.

## L'apparence d'un dé

`appearance($member, $sides)` donne le premier de ces choix :

1. le dé d'un ensemble que le membre possède et a choisi pour ce nombre de faces — sa couleur, avec l'encre noire ou
   blanche qui ressort le plus, et la matière que nomme sa collection ; un dé dont la collection ne donne pas de
   couleur prend l'aspect du niveau suivant, avec son propre nom ;
2. le skin que le membre a choisi pour ce nombre de faces ;
3. le skin que le membre a choisi pour tous ses dés ;
4. le skin par défaut du site, que le catalogue doit offrir.

Le `DieAppearance` indique d'où il vient, `$source`, et donne au moteur de rendu :

| Méthode | Donne |
|---|---|
| `cssProperties()` | `--tilttek-dice-color` pour les faces et `--tilttek-dice-ink` pour les chiffres |
| `style()` | les mêmes, comme valeur d'un attribut `style` |
| `dataAttributes()` | `data-dice-appearance`, `data-dice-sides`, `data-dice-material`, ainsi que `data-dice-texture` et `data-dice-contrast="high"` quand ils s'appliquent |
| `toArray()` | tout en valeurs simples, ratio de contraste compris, pour un moteur de rendu qui lit du JSON |

Dans Twig, les attributs passent par `html_attr`, qui les échappe, le nom du skin compris :

```twig
<span class="die" style="{{ appearance.style }}" {{ html_attr(appearance.dataAttributes) }}>{{ face }}</span>
```

Un contrôleur Stimulus qui dessine les dés lit les mêmes valeurs ; la bibliothèque ne fournit aucun script.
