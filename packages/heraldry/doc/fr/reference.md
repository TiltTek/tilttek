<!-- phil-dev source-sha256=b45864fd1e8be00ce6a1630039e92813d7e76c50ed4d75a190c902bd8391136a -->
# Référence

| Type | Rôle |
|---|---|
| `Coat` | Des armoiries : champ, pièce, meubles et brisure, vérifiés ensemble |
| `Field` | Un champ uni, ou un champ divisé entre deux émaux |
| `Partition` | Comment un champ est divisé : parti, coupé, tranché, taillé, écartelé, écartelé en sautoir |
| `Tincture` | Les métaux, les couleurs et les fourrures, avec leur famille et leur contraste |
| `TinctureKind` | `Metal`, `Colour` ou `Fur` |
| `Ordinary` | Les pièces honorables, avec les meubles que chacune porte et entoure |
| `Piece` | Une pièce dans son émail |
| `Charge` | Les meubles : étoile, croissant, tourteau, annelet, losange, macle, billette, fleur de lis |
| `ChargeGroup` | De un à trois meubles d'une sorte et d'un émail, sur le champ ou sur la pièce |
| `Arrangement` | `Usual`, `InFess` ou `InPale` |
| `Brisure` | Une brisure dans son émail |
| `BrisureMark` | `Label` ou `Bendlet` |
| `ShieldShape` | `Heater`, `French`, `Iberian` ou `Lozenge` |
| `Language` | `English` ou `French` |
| `TinctureRule` | La règle de couleur, vérifiée sur un écu |
| `Violation` | Où un écu enfreint la règle, en une phrase |
| `Generator\CoatGenerator` | Des armes tirées d'un germe, qui respectent la règle |
| `Generator\Seed` | Les 32 octets dont les armes sont tirées, écrits en hexadécimal |
| `Blazon\Blazonry` | Le blason dans la langue demandée |
| `Blazon\EnglishBlazon` | Le blason en anglais |
| `Blazon\FrenchBlazon` | Le blason en français |
| `Contract\BlazonWriter` | Un rédacteur de blasons |
| `Svg\SvgRenderer` | L'image SVG accessible d'un écu |
| `Svg\Palette` | Les couleurs des émaux et des contours |
| `Exception\HeraldryException` | L'interface de chaque exception de la bibliothèque |
| `Exception\InvalidCoat` | Un écu que le modèle ne peut pas contenir |
| `Exception\InvalidSeed` | Un germe qui n'est pas de 64 chiffres hexadécimaux |
| `Exception\InvalidPalette` | Une palette que le moteur de rendu refuse |
| `Exception\InvalidDrawing` | Un préfixe d'identifiants que le moteur de rendu refuse |

`Svg\Shapes` est interne : les tracés et les emplacements du dessin.
